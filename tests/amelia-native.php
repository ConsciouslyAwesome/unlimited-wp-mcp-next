<?php
/** Adapter orchestration doubles. These do not certify live Amelia integrations. */
require __DIR__.'/amelia.php';
class NativeResult {
    const RESULT_SUCCESS='success';
    function __construct(public $result='success') {}
    function getResult() { return $this->result; }
}
class NativeCommand {
    public $fields=array();
    function __construct(public $args) {}
    function setPermissionService($value) {}
    function setUserApplicationService($value) {}
    function setField($key,$value) { $this->fields[$key]=$value; }
}
foreach (array('GetAppointment','UpdateAppointment','UpdateAppointmentNote') as $name) {
    class_alias(NativeCommand::class,'AmeliaBooking\\Application\\Commands\\Booking\\Appointment\\'.$name.'Command');
}
class_alias(NativeResult::class,'AmeliaBooking\\Application\\Commands\\CommandResult');
class Value { function __construct(public $value) {} function getValue() { return $this->value; } }
class_alias(Value::class,'AmeliaBooking\\Domain\\ValueObjects\\DateTime\\DateTimeValue');
class NativeAppointment {
    function __construct(public $raw) {}
    function toArray() { return $this->raw; }
    function getBookingStart() { return new Value(new DateTime($this->raw['bookingStart'],new DateTimeZone('America/New_York'))); }
    function getBookingEnd() { return new Value(new DateTime($this->raw['bookingEnd'],new DateTimeZone('America/New_York'))); }
    function setBookingStart($value) { $this->raw['bookingStart']=$value->getValue()->format('Y-m-d H:i:s'); }
    function setBookingEnd($value) { $this->raw['bookingEnd']=$value->getValue()->format('Y-m-d H:i:s'); }
}
class NativeFactory { static function create($raw) { return new NativeAppointment($raw); } }
class_alias(NativeFactory::class,'AmeliaBooking\\Domain\\Factory\\Booking\\Appointment\\AppointmentFactory');
class NativeService {
    function getAppointmentService(...$args) { return $this; }
    function getTimeBefore() { return new Value(0); }
    function getTimeAfter() { return new Value(900); }
    function getAppointmentLengthTime(...$args) { return 3600; }
    function canBeBooked(...$args) { return $GLOBALS['available']; }
    function currentUserCanRead(...$args) { return $GLOBALS['nativePermission']; }
    function currentUserCanWrite(...$args) { return $GLOBALS['nativePermission']; }
}
class NativeRepository { function getById($id) { if ($id!==102) { throw new RuntimeException('missing'); } return new NativeAppointment($GLOBALS['raw']); } }
class NativeBus {
    function handle($command) {
        if (!isset($command->fields['id']) && isset($command->fields['bookingStart'])) {
            throw new RuntimeException('Missing full patch');
        }
        if (isset($command->fields['bookingStart'])) {
            $GLOBALS['mutations']++;
            $GLOBALS['raw']['bookingStart']=$command->fields['bookingStart'];
            $GLOBALS['raw']['bookingEnd']=(new DateTime($command->fields['bookingStart']))->modify('+1 hour')->format('Y-m-d H:i:s');
            if ($GLOBALS['partial']) { return new NativeResult('error'); }
            if ($GLOBALS['tamper']) { $GLOBALS['raw']['bookings'][0]['customerId']=999; }
        }
        if (isset($command->fields['internalNotes']) && !isset($command->fields['bookingStart'])) { $GLOBALS['mutations']++; }
        if (isset($command->fields['internalNotes'])) { $GLOBALS['raw']['internalNotes']=$command->fields['internalNotes']; }
        return new NativeResult();
    }
}
class NativeEvents { function emit($name,$result) { check($name==='AppointmentEdited','Native event name');$GLOBALS['emissions']++;if($GLOBALS['eventFailure'])throw new RuntimeException('calendar failed'); } }
class NativeContainer {
    function getPermissionsService() { return new NativeService(); }
    function getUserApplicationService() { return new NativeService(); }
    function getCommandBus() { return new NativeBus(); }
    function getEventBus() { return new NativeEvents(); }
    function get($key) { return $key==='domain.booking.appointment.repository'?new NativeRepository():new NativeService(); }
}
class UWNext_Store { static function digest($name,$raw) { return hash('sha256',json_encode(array($GLOBALS['site'],$name,$raw))); } }
class UWNext_Tools { static function executing() { return $GLOBALS['executing']; } }
function current_user_can(...$args) { return $GLOBALS['wpPermission']; }
$root=sys_get_temp_dir().'/uwnext-amelia-'.bin2hex(random_bytes(6));
mkdir($root.'/src/Infrastructure/ContainerConfig',0777,true);
file_put_contents($root.'/src/Infrastructure/ContainerConfig/container.php','<?php return new NativeContainer();');
define('AMELIA_PATH',$root);define('AMELIA_VERSION','9.10');
function reset_native() {
    $GLOBALS['raw']=array('id'=>102,'bookingStart'=>'2026-12-11 09:00:00','bookingEnd'=>'2026-12-11 10:00:00','internalNotes'=>'original',
        'serviceId'=>12,'providerId'=>1,'locationId'=>4,'status'=>'approved','notifyParticipants'=>true,'bookings'=>array(
            array('id'=>6,'customerId'=>7,'status'=>'approved'),array('id'=>8,'customerId'=>9,'status'=>'approved')));
    foreach(array('available','nativePermission','wpPermission','executing') as $key) $GLOBALS[$key]=true;
    foreach(array('partial','tamper','eventFailure') as $key) $GLOBALS[$key]=false;
    $GLOBALS['mutations']=$GLOBALS['emissions']=0;$GLOBALS['site']='https://fixture.test';
}
function lookup() { return UWNext_Amelia::handle('get-appointment',array('appointment_id'=>102)); }
function change_args() { return array('appointment_id'=>102,'expected_state'=>lookup()['expected_state'],'new_start'=>'2026-12-07 09:00','timezone'=>'America/New_York','notify_participants'=>true,'internal_notes'=>null); }
try {
    reset_native();check(UWNext_Amelia::supported(),'Inspected profile enabled');
    $args=change_args();$preview=UWNext_Amelia::handle('preview-appointment',$args);
    check($mutations===0 && $emissions===0,'Preview has no writes or events');
    check(count($preview['affected_bookings'])===2,'Group preview identifies every booking');
    check($preview['after']['start_utc']==='2026-12-07T14:00:00+00:00','Preview UTC verified');
    check($preview['after']['buffer_after_seconds']===900,'Preview preserves native buffer');
    $out=UWNext_Amelia::handle('reschedule-appointment',$args);
    check(!is_wp_error($out) && $out['appointment_update']==='verified' && $mutations===1 && $emissions===1,'One native update and event');
    check($raw['id']===102 && array_column($raw['bookings'],'id')===array(6,8),'Identities preserved');
    check(is_wp_error(UWNext_Amelia::handle('reschedule-appointment',$args)) && $mutations===1,'Stale request does not write twice');
    reset_native();$args=change_args();$executing=false;
    check(is_wp_error(UWNext_Amelia::handle('reschedule-appointment',$args)) && $mutations===0,'Direct mutation without execution gate denied');
    reset_native();$args=change_args();$available=false;
    check(is_wp_error(UWNext_Amelia::handle('reschedule-appointment',$args)) && $mutations===0,'Unavailable native slot denied');
    reset_native();$args=change_args();$args['notify_participants']=false;
    check(is_wp_error(UWNext_Amelia::handle('reschedule-appointment',$args)) && $mutations===0,'Unsupported silent notification policy denied');
    reset_native();$args=change_args();$nativePermission=false;
    check(is_wp_error(UWNext_Amelia::handle('reschedule-appointment',$args)) && $mutations===0,'Native authorization denied');
    reset_native();$args=change_args();$wpPermission=false;
    check(is_wp_error(UWNext_Amelia::handle('reschedule-appointment',$args)) && $mutations===0,'WP authorization denied');
    reset_native();$args=change_args();$site='https://other.test';
    check(is_wp_error(UWNext_Amelia::handle('reschedule-appointment',$args)) && $mutations===0,'State digest cannot cross sites');
    reset_native();$args=change_args();$args['new_start']=$args['timezone']=null;$args['internal_notes']='revised';
    $out=UWNext_Amelia::handle('update-appointment',$args);
    check(!is_wp_error($out) && $mutations===1 && $emissions===0 && $out['notifications']==='not_requested','Notes-only edit avoids notification and calendar event');
    foreach(array('partial','eventFailure','tamper') as $failure) {
        reset_native();$args=change_args();$GLOBALS[$failure]=true;
        $out=UWNext_Amelia::handle('reschedule-appointment',$args);
        check(is_wp_error($out) && in_array($out->get_error_code(),array('amelia_operation_uncertain','amelia_verification_uncertain'),true),'Uncertain failure '.$failure);
        check($mutations===1,'No automatic retry '.$failure);
    }
    echo "PASS Amelia orchestration: $checks cumulative checks\n";
} finally {
    unlink($root.'/src/Infrastructure/ContainerConfig/container.php');
    foreach(array('/src/Infrastructure/ContainerConfig','/src/Infrastructure','/src','') as $suffix)rmdir($root.$suffix);
}
