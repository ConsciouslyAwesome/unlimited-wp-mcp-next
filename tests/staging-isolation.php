<?php
/** Pure policy tests for the staging-only helper; no WordPress, network or DB. */
define('ABSPATH',__DIR__.'/');
function add_action(...$args) {}
require __DIR__.'/staging-isolation/uwnext-amelia-staging-isolation.php';
$passed=0;
function check($condition,$message) { global $passed;if(!$condition){throw new RuntimeException($message);}$passed++; }
$stage='https://booking.fractalillumination.com';
check(UWNext_Amelia_Staging_Isolation::allowed($stage,$stage),'Named stage accepted');
foreach(array('https://booking.consciouslyawesome.com','http://booking.fractalillumination.com','https://booking.fractalillumination.com.attacker.invalid','https://booking.fractalillumination.com:8443') as $other) {
    check(!UWNext_Amelia_Staging_Isolation::allowed($other,$stage),'Home mismatch rejected');
    check(!UWNext_Amelia_Staging_Isolation::allowed($stage,$other),'Site mismatch rejected');
}
$original=array('general'=>array('timeSlotLength'=>3600),'appointments'=>array('buffer'=>900),'weekSchedule'=>array('monday'=>'09:00'),
    'notifications'=>array('mailService'=>'smtp','smsSignedIn'=>true,'smsApiToken'=>'fixture-token','whatsAppEnabled'=>true,'whatsAppAccessToken'=>'fixture-wa'),
    'webHooks'=>array(array('url'=>'https://fixture.invalid')),'zoom'=>array('enabled'=>true),'resources'=>array('capacity'=>1));
$snapshot=$original;$isolated=UWNext_Amelia_Staging_Isolation::settings($original);
check($original===$snapshot,'Original configuration is not mutated');
foreach(array('general','appointments','weekSchedule','resources') as $key) {check($isolated[$key]===$snapshot[$key],'Scheduling preserved: '.$key);}
check($isolated['notifications']['mailService']==='wp_mail','Use guarded mail path');
check($isolated['notifications']['smsSignedIn']===false && $isolated['notifications']['smsApiToken']==='','SMS disabled');
check($isolated['notifications']['whatsAppEnabled']===false && $isolated['notifications']['whatsAppAccessToken']==='','WhatsApp disabled');
check($isolated['webHooks']===array(),'Webhooks disabled');
foreach(array('googleCalendar','outlookCalendar','appleCalendar','zoom','lessonSpace','mailchimp') as $name) {check($isolated[$name]['enabled']===false,'Integration disabled: '.$name);}
try {UWNext_Amelia_Staging_Isolation::settings(array());throw new RuntimeException('Malformed shape was accepted');}catch(RuntimeException $error){check($error->getMessage()==='Unsupported Amelia settings shape.','Malformed settings fail closed');}
echo "PASS staging isolation policy: $passed checks\n";
