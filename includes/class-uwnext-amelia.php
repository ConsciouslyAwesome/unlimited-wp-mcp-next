<?php
/** Optional native Amelia adapter. No Amelia code or credentials are bundled. */
defined('ABSPATH') || exit;

final class UWNext_Amelia {
    const PROFILE = '9.10';
    const PREFIX = 'AmeliaBooking\\Application\\Commands\\Booking\\Appointment\\';

    public static function supported() {
        return defined('AMELIA_VERSION') && AMELIA_VERSION === self::PROFILE
            && defined('AMELIA_PATH') && class_exists(self::PREFIX.'UpdateAppointmentCommand');
    }

    public static function register() {
        if (!self::supported()) { return; }
        $permission = array(__CLASS__, 'permission');
        foreach (array('get-appointment','preview-appointment','reschedule-appointment','update-appointment') as $operation) {
            $read = in_array($operation, array('get-appointment','preview-appointment'), true);
            $args = self::operation_args($operation);
            uwnext_register_internal_route(UWNext_NS, '/amelia/'.$operation, array(
                'methods'=>$read?'GET':'POST', 'permission_callback'=>$permission, 'args'=>$args,
                'callback'=>function($request) use ($operation) { return self::handle($operation, $request); },
            ));
        }
    }

    public static function operation_args($operation) {
        $read=in_array($operation,array('get-appointment','preview-appointment'),true);
            $args = array('appointment_id'=>array('type'=>'integer','minimum'=>1,'required'=>true));
            if ($operation !== 'get-appointment') {
                $args += array(
                    'expected_state'=>array('type'=>'string','pattern'=>'^[a-f0-9]{64}$','required'=>true),
                    'new_start'=>array('type'=>'string','pattern'=>'^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$'),
                    'timezone'=>array('type'=>'string','minLength'=>1,'maxLength'=>100),
                    'notify_participants'=>array('type'=>'boolean','default'=>true),
                );
                if ($operation !== 'reschedule-appointment') {
                    $args['internal_notes'] = array('type'=>'string','maxLength'=>10000);
                } else { $args['new_start']['required']=true; $args['timezone']['required']=true; }
                if (!$read) { $args['dry_run']=array('type'=>'boolean','default'=>true); }
            }
        return $args;
    }

    /** Only these fixed aliases may share the outer Unlimited approval gate. */
    public static function ability_operation($name,$args) {
        if ($name !== 'ability_run' || !self::supported()) { return null; }
        $map=array('uwnext-amelia/get-appointment'=>'get-appointment',
            'uwnext-amelia/preview-appointment'=>'preview-appointment',
            'uwnext-amelia/reschedule-appointment'=>'reschedule-appointment',
            'uwnext-amelia/update-appointment'=>'update-appointment');
        return $map[$args['name']??'']??null;
    }

    public static function register_category() {
        if (self::supported() && function_exists('wp_register_ability_category')) {
            wp_register_ability_category('uwnext-amelia',array('label'=>'Amelia appointment management',
                'description'=>'Native appointment previews and approved updates.'));
        }
    }

    public static function register_abilities() {
        if (!self::supported() || !function_exists('wp_register_ability')) { return; }
        foreach (array('get-appointment','preview-appointment','reschedule-appointment','update-appointment') as $operation) {
            $properties=self::operation_args($operation);$required=array();
            foreach ($properties as $key=>&$property) {
                if (!empty($property['required'])) { $required[]=$key; }
                unset($property['required']);
            }
            unset($property);
            wp_register_ability('uwnext-amelia/'.$operation,array(
                'label'=>ucwords(str_replace('-',' ',$operation)),
                'description'=>'Amelia 9.10 native adapter. Use ability_run through Unlimited Next. Mutations require exact approval and expected_state; dry_run defaults true.',
                'category'=>'uwnext-amelia',
                'input_schema'=>array('type'=>'object','properties'=>$properties,'required'=>$required,'additionalProperties'=>false),
                'permission_callback'=>array(__CLASS__,'permission'),
                'execute_callback'=>function($input) use($operation) {
                    foreach (self::operation_args($operation) as $key=>$arg) {
                        if (!array_key_exists($key,$input) && array_key_exists('default',$arg)) { $input[$key]=$arg['default']; }
                    }
                    $request=new WP_REST_Request('POST');$request->set_body_params($input);
                    return self::handle($operation,$request);
                },
                'meta'=>array('annotations'=>array('readonly'=>in_array($operation,array('get-appointment','preview-appointment'),true))),
            ));
        }
    }

    public static function permission() {
        return current_user_can('manage_options') ? true : self::error('capability_denied','WordPress administration permission is required.',403);
    }

    private static function error($code,$message,$status=409) {
        return new WP_Error($code,$message,array('status'=>$status));
    }

    private static function container() {
        if (!self::supported()) { throw new RuntimeException('unsupported_amelia'); }
        // Fresh services bind the actual authenticated WP user on every request.
        $container = require AMELIA_PATH.'/src/Infrastructure/ContainerConfig/container.php';
        $permission = $container->getPermissionsService();
        if (!$permission->currentUserCanRead('appointments') || !$permission->currentUserCanWrite('appointments')) {
            throw new RuntimeException('amelia_permission_denied');
        }
        return $container;
    }

    private static function dispatch($container,$name,$id,$fields) {
        // The installed licence supplies the command map. Never construct a handler directly.
        $class = self::PREFIX.$name.'Command';
        $command = new $class(array('id'=>$id));
        $command->setPermissionService($container->getPermissionsService());
        $command->setUserApplicationService($container->getUserApplicationService());
        foreach ($fields as $key=>$value) { $command->setField($key,$value); }
        return $container->getCommandBus()->handle($command);
    }

    /** Reject DST gaps/folds instead of silently selecting or normalizing an instant. */
    public static function local_time($value,$zone) {
        if (!in_array($zone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true) && $zone !== 'UTC') {
            return self::error('invalid_timezone','Use an IANA timezone.',400);
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D',$value)) {
            return self::error('invalid_datetime','Use YYYY-MM-DD HH:MM.',400);
        }
        $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i',$value,new DateTimeZone('UTC'));
        if (!$wall || $wall->format('Y-m-d H:i') !== $value) { return self::error('invalid_datetime','Invalid calendar date or time.',400); }
        $tz = new DateTimeZone($zone); $offsets=array(); $matches=array();
        foreach ($tz->getTransitions($wall->getTimestamp()-172800,$wall->getTimestamp()+172800) ?: array() as $transition) {
            $offsets[$transition['offset']]=true;
        }
        foreach (array_keys($offsets) as $offset) {
            $instant = $wall->setTimestamp($wall->getTimestamp()-$offset)->setTimezone($tz);
            if ($instant->format('Y-m-d H:i') === $value) { $matches[$instant->getTimestamp()]=$instant; }
        }
        if (count($matches)!==1) { return self::error('ambiguous_or_nonexistent_time','Choose an unambiguous local time outside the daylight saving transition.',400); }
        return reset($matches);
    }

    private static function fingerprint($raw) {
        return UWNext_Store::digest('amelia_appointment_state',$raw);
    }

    private static function summary($appointment,$container) {
        $raw=$appointment->toArray();
        $service=$container->get('application.bookable.service')->getAppointmentService($raw['serviceId'],$raw['providerId']);
        $utc=new DateTimeZone('UTC');
        $start=clone $appointment->getBookingStart()->getValue();
        $end=clone $appointment->getBookingEnd()->getValue();
        $bookings=array();
        foreach ($raw['bookings'] as $booking) {
            $bookings[]=array('booking_id'=>$booking['id'],'customer_id'=>$booking['customerId'],'status'=>$booking['status']);
        }
        return array('appointment_id'=>$raw['id'],'start'=>$start->format(DATE_ATOM),'end'=>$end->format(DATE_ATOM),
            'start_utc'=>$start->setTimezone($utc)->format(DATE_ATOM),'timezone'=>$appointment->getBookingStart()->getValue()->getTimezone()->getName(),
            'employee_id'=>$raw['providerId'],'service_id'=>$raw['serviceId'],'location_id'=>$raw['locationId']??null,
            'status'=>$raw['status'],'bookings'=>$bookings,'duration_seconds'=>$end->getTimestamp()-$start->getTimestamp(),
            'buffer_before_seconds'=>$service->getTimeBefore()?$service->getTimeBefore()->getValue():0,
            'buffer_after_seconds'=>$service->getTimeAfter()?$service->getTimeAfter()->getValue():0,
            'calendar_sync'=>'not_verified','expected_state'=>self::fingerprint($raw));
    }

    private static function load($container,$id) {
        // Native command checks ownership and authorization before repository use.
        $result=self::dispatch($container,'GetAppointment',$id,array('id'=>$id));
        if ($result->getResult() !== \AmeliaBooking\Application\Commands\CommandResult::RESULT_SUCCESS) {
            throw new RuntimeException('appointment_unavailable');
        }
        return $container->get('domain.booking.appointment.repository')->getById($id);
    }

    /** Match the native administrator slot grid as well as overlap validation. */
    private static function grid_available($container,$raw,$candidate) {
        // Group/extras/custom-duration slot inputs need separate acceptance. Fail closed.
        if (count($raw['bookings'])!==1 || !empty($raw['bookings'][0]['extras']) ||
            (int)($raw['bookings'][0]['persons']??1)!==1 || !empty($raw['bookings'][0]['duration'])) {
            return self::error('unsupported_slot_profile','Group, extras and custom-duration rescheduling require a separately verified slot profile. No change was made.');
        }
        $start=$candidate->getBookingStart()->getValue();
        $result=self::dispatch($container,'GetTimeSlots',$raw['id'],array(
            'serviceId'=>$raw['serviceId'],'providerIds'=>array($raw['providerId']),
            'locationId'=>$raw['locationId']??null,'excludeAppointmentId'=>$raw['id'],
            'page'=>'appointments','persons'=>1,'group'=>false,'extras'=>array(),'prebooked'=>array(),
            'startDateTime'=>$start->format('Y-m-d').' 00:00',
            'endDateTime'=>$start->format('Y-m-d').' 23:59',
            'queryTimeZone'=>$start->getTimezone()->getName(),'timeZone'=>null,'structured'=>false,
        ));
        if ($result->getResult() !== \AmeliaBooking\Application\Commands\CommandResult::RESULT_SUCCESS) {
            return self::error('slot_grid_unverified','Amelia could not verify the administrator slot grid. No change was made.');
        }
        $data=$result->getData();
        return isset($data['slots'][$start->format('Y-m-d')][$start->format('H:i')]);
    }

    private static function prepare($request,$container) {
        $old=self::load($container,(int)$request['appointment_id']); $raw=$old->toArray();
        if (!hash_equals(self::fingerprint($raw),(string)$request['expected_state'])) {
            return self::error('appointment_state_changed','The appointment changed. Retrieve it and review a fresh preview.');
        }
        if (!in_array($raw['status'],array('approved','pending'),true)) {
            return self::error('unsupported_appointment_status','Only approved or pending appointments can be edited.');
        }
        if ($request['notify_participants'] === false) {
            return self::error('unsupported_notification_policy','Amelia 9.10 may still notify employees when participant notifications are disabled. No change was made.');
        }
        // Preserve every booking and server-owned field; never accept a replacement payload from the client.
        $fields=array_intersect_key($raw,array_flip(array('id','bookings','bookingStart','notifyParticipants','createPaymentLinks','internalNotes','serviceId','providerId','locationId','lessonSpace')));
        $fields['removedBookings']=array(); $fields['notifyParticipants']=true;
        $fields['utc']=false;
        $candidate=\AmeliaBooking\Domain\Factory\Booking\Appointment\AppointmentFactory::create($raw);
        if ($request['new_start'] !== null) {
            if (!$request['timezone']) { return self::error('timezone_required','A timezone is required with new_start.',400); }
            $instant=self::local_time($request['new_start'],$request['timezone']);
            if (is_wp_error($instant)) { return $instant; }
            $nativeZone=$old->getBookingStart()->getValue()->getTimezone();
            $instant=$instant->setTimezone($nativeZone);
            $fields['bookingStart']=$instant->format('Y-m-d H:i:s');
            $candidate->setBookingStart(new \AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue(DateTime::createFromImmutable($instant)));
            $duration=$container->get('application.booking.appointment.service')->getAppointmentLengthTime($candidate,
                $container->get('application.bookable.service')->getAppointmentService($raw['serviceId'],$raw['providerId']));
            $candidate->setBookingEnd(new \AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue(DateTime::createFromImmutable($instant->modify('+'.$duration.' seconds'))));
        } elseif ($request['timezone'] !== null) { return self::error('start_required','timezone requires new_start.',400); }
        if ($request['internal_notes'] !== null) { $fields['internalNotes']=$request['internal_notes']; }
        if ($request['new_start'] === null && $request['internal_notes'] === null) { return self::error('empty_patch','Provide a start time or internal notes.',400); }
        // Retain the existing ID for native self-conflict handling; verify exclusion on staging.
        $available=$request['new_start'] === null || $container->get('application.booking.appointment.service')->canBeBooked($candidate,false,null,null);
        if (!$available) { return self::error('time_slot_unavailable','Amelia rejected the requested time. No change was made.'); }
        if ($request['new_start'] !== null) {
            $grid=self::grid_available($container,$raw,$candidate);
            if (is_wp_error($grid)) { return $grid; }
            if (!$grid) { return self::error('time_slot_off_grid','Choose a start offered by the native administrator slot grid. No change was made.'); }
        }
        $preview=array('before'=>self::summary($old,$container),'after'=>self::summary($candidate,$container),
            'notify_participants'=>true,'internal_notes_changed'=>array_key_exists('internalNotes',$fields) && ($fields['internalNotes']!==($raw['internalNotes']??null)),
            'affected_bookings'=>self::summary($old,$container)['bookings'],'availability'=>'available');
        return array('old'=>$raw,'fields'=>$fields,'preview'=>$preview);
    }

    public static function preview($request) {
        try { $prepared=self::prepare($request,self::container()); return is_wp_error($prepared)?$prepared:$prepared['preview']; }
        catch (Throwable $error) { return self::error('amelia_preview_failed','Amelia could not authorize or prepare this appointment.',400); }
    }

    public static function handle($operation,$request) {
        if (is_wp_error($permission=self::permission())) { return $permission; }
        $started=false;
        try {
            $container=self::container();
            if ($operation === 'get-appointment') { return self::summary(self::load($container,(int)$request['appointment_id']),$container); }
            $prepared=self::prepare($request,$container);
            if (is_wp_error($prepared)) { return $prepared; }
            if ($operation === 'preview-appointment' || $request['dry_run'] === true) { return $prepared['preview']; }
            if (!UWNext_Tools::executing()) { return self::error('approval_required','Use the exact-operation MCP approval workflow.'); }
            $started=true;
            $notesOnly=$request['new_start'] === null;
            $result=self::dispatch($container,$notesOnly?'UpdateAppointmentNote':'UpdateAppointment',(int)$request['appointment_id'],
                $notesOnly?array('internalNotes'=>$prepared['fields']['internalNotes']):$prepared['fields']);
            if ($result->getResult() !== \AmeliaBooking\Application\Commands\CommandResult::RESULT_SUCCESS) {
                return self::error('amelia_operation_uncertain','Amelia returned a non-success result after execution began. Inspect the appointment and receipt before retrying.');
            }
            // Same event emitted by UpdateAppointmentController, once, after successful dispatch.
            if (!$notesOnly) { $container->getEventBus()->emit('AppointmentEdited',$result); }
            $after=self::load($container,(int)$request['appointment_id']); $raw=$after->toArray();
            if (!self::preserved($prepared['old'],$raw) || $raw['bookingStart'] !== $prepared['fields']['bookingStart']
                || ($raw['internalNotes']??null) !== ($prepared['fields']['internalNotes']??null)) {
                return self::error('amelia_verification_uncertain','The resulting appointment requires reconciliation. Do not repeat this write.');
            }
            return array('appointment_update'=>'verified','appointment'=>self::summary($after,$container),
                'calendar_sync'=>$notesOnly?'not_requested':'native_processing_invoked_not_verified',
                'notifications'=>$notesOnly?'not_requested':'native_processing_invoked_delivery_not_verified',
                'deferred_processing'=>'not_verified');
        } catch (Throwable $error) {
            return self::error($started?'amelia_operation_uncertain':'amelia_operation_rejected',
                $started?'Execution began but its outcome is uncertain. Inspect the appointment and receipt before retrying.':'Amelia could not authorize or prepare this operation.');
        }
    }

    public static function preserved($before,$after) {
        foreach (array('id','serviceId','providerId','locationId','status','parentId','googleCalendarEventId','outlookCalendarEventId','appleCalendarEventId') as $key) {
            if (($before[$key]??null)!==($after[$key]??null)) { return false; }
        }
        $relationships=function($raw) {
            $rows=array();
            foreach ($raw['bookings'] as $booking) {
                $payments=array();
                foreach ($booking['payments']??array() as $payment) { $payments[]=array_intersect_key($payment,array_flip(array('id','customerBookingId','packageCustomerId','status','amount','parentId','transactionId'))); }
                usort($payments,function($a,$b){return $a['id']<=>$b['id'];});
                $rows[$booking['id']]=array('customerId'=>$booking['customerId'],'status'=>$booking['status'],
                    'package'=>$booking['packageCustomerService']??null,'extras'=>$booking['extras']??array(),'payments'=>$payments);
            }
            ksort($rows);return $rows;
        };
        return $relationships($before)===$relationships($after);
    }
}
add_action('rest_api_init',array('UWNext_Amelia','register'));

add_action('wp_abilities_api_categories_init',array('UWNext_Amelia','register_category'));
add_action('wp_abilities_api_init',array('UWNext_Amelia','register_abilities'));
