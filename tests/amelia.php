<?php
/** Standalone guard tests; no Amelia installation, network or customer records. */
define('ABSPATH',__DIR__.'/');
class WP_Error {
    private $code,$message,$data;
    function __construct($code,$message,$data=array()) { $this->code=$code;$this->message=$message;$this->data=$data; }
    function get_error_code() { return $this->code; }
    function get_error_message() { return $this->message; }
    function get_error_data() { return $this->data; }
}
function add_action(...$args) {}
function is_wp_error($value) { return $value instanceof WP_Error; }
require dirname(__DIR__).'/includes/class-uwnext-amelia.php';
$checks=0;
function check($condition,$name) { global $checks; if (!$condition) { throw new RuntimeException($name); } $checks++; }
check(!UWNext_Amelia::supported(),'Absent Amelia is unsupported');
$time=UWNext_Amelia::local_time('2026-12-07 09:00','America/New_York');
check(!is_wp_error($time) && $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i')==='2026-12-07 14:00','Winter UTC conversion');
$time=UWNext_Amelia::local_time('2026-07-07 09:00','America/New_York');
check(!is_wp_error($time) && $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i')==='2026-07-07 13:00','Summer UTC conversion');
foreach (array(
    array('2026-03-08 02:30','America/New_York'),
    array('2026-11-01 01:30','America/New_York'),
    array('2026-10-04 02:15','Australia/Lord_Howe'),
    array('2026-02-30 10:00','UTC'),
    array('2026-12-07 25:00','UTC'),
    array('2026-12-07 09:00','-05:00'),
    array('2026-12-07 09:00','bogus'),
    array('tomorrow','UTC'),
) as $case) { check(is_wp_error(UWNext_Amelia::local_time(...$case)),'Reject invalid or ambiguous time '.implode(' ',$case)); }
check(!is_wp_error(UWNext_Amelia::local_time('2026-12-07 09:00','UTC')),'UTC accepted');
check(!is_wp_error(UWNext_Amelia::local_time('2026-12-07 09:00','Asia/Kathmandu')),'Fractional timezone accepted');
$before=array('id'=>102,'serviceId'=>12,'providerId'=>1,'locationId'=>4,'status'=>'approved','googleCalendarEventId'=>'fixture',
    'bookings'=>array(array('id'=>6,'customerId'=>7,'status'=>'approved','payments'=>array(array('id'=>8,'customerBookingId'=>6,'amount'=>20,'status'=>'paid','dateTime'=>'old')))));
$after=$before;$after['bookingStart']='new';$after['bookings'][0]['payments'][0]['dateTime']='new';
check(UWNext_Amelia::preserved($before,$after),'Native payment date changes permitted');
foreach (array('id','serviceId','providerId','locationId','status','googleCalendarEventId') as $key) {
    $changed=$after;$changed[$key]='changed';check(!UWNext_Amelia::preserved($before,$changed),'Identity invariant '.$key);
}
$changed=$after;$changed['bookings'][0]['customerId']=99;check(!UWNext_Amelia::preserved($before,$changed),'Customer identity protected');
$changed=$after;$changed['bookings'][0]['payments'][0]['amount']=21;check(!UWNext_Amelia::preserved($before,$changed),'Payment amount protected');
$changed=$after;$changed['bookings'][]=array('id'=>9,'customerId'=>11,'status'=>'approved');check(!UWNext_Amelia::preserved($before,$changed),'Additional booking detected');
$changed=$after;$changed['bookings']=array();check(!UWNext_Amelia::preserved($before,$changed),'Missing booking detected');
echo "PASS Amelia guards: $checks checks\n";
