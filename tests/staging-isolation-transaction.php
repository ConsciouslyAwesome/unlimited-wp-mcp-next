<?php
/** Fixed-purpose isolation/recovery doubles with the real encrypted store. No network. */
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('AMELIA_VERSION','9.10');
$options=$events=array();$can=true;$site='https://booking.fractalillumination.com';
function add_action(...$args) {} function add_filter(...$args) {}
function current_user_can(...$args) { return $GLOBALS['can']; } function is_multisite() { return false; }
function get_option($name,$default=false) { return $GLOBALS['options'][$name]??$default; }
function add_option($name,$value,...$args) { if(isset($GLOBALS['options'][$name]))return false;$GLOBALS['options'][$name]=$value;return true; }
function update_option($name,$value,...$args) { $GLOBALS['options'][$name]=$value;return true; }
function site_url() { return $GLOBALS['site']; } function get_current_user_id() { return 1; }
function wp_json_encode($value) { return json_encode($value); } function wp_salt($scheme) { return 'synthetic-fixture-salt'; }
function plugin_basename($path) { return 'helper/helper.php'; } function wp_cache_flush() {}
class UWNext_Auth { static $connection='fixture'; }
class IsolationDB {
    public $prefix='fixture_',$options='fixture_options',$last_error='',$tables=array(),$saved=null,$failAt='',$badEngine=false,$writes=0;
    function prepare($sql,...$values) { foreach($values as $value){$sql=preg_replace('/%s/',"'".addslashes($value)."'",$sql,1);}return $sql; }
    function get_var($sql) { return $this->badEngine?'MyISAM':'InnoDB'; }
    function get_results($sql,$format) {
        preg_match('/ FROM ([a-z0-9_]+)/i',$sql,$match);$rows=$this->tables[$match[1]]??null;
        if($rows===null){$this->last_error='fixture query failed';return null;}
        if(str_contains($sql,'COUNT(*)')) {
            if(str_contains($sql,"status='enabled'"))$rows=array_filter($rows,static function($row){return $row['status']==='enabled';});
            elseif(str_contains($sql,'googleCalendarId IS NOT NULL'))$rows=array_filter($rows,static function($row){unset($row['id']);return count(array_filter($row,static function($value){return $value!==null;}))>0;});
            return array(array('n'=>count($rows)));
        }
        return $rows;
    }
    function query($sql) {
        if($this->failAt && str_contains($sql,$this->failAt)) { $this->last_error='synthetic private failure';return false; }
        if($sql==='START TRANSACTION') { $this->saved=array($this->tables,$GLOBALS['options']);return 0; }
        if($sql==='COMMIT') { $this->saved=null;return 0; }
        if($sql==='ROLLBACK') { if($this->saved){[$this->tables,$GLOBALS['options']]=$this->saved;}$this->saved=null;$this->last_error='';return 0; }
        if(preg_match('/^DELETE FROM ([a-z0-9_]+)$/i',$sql,$match)) { $this->tables[$match[1]]=array();$this->writes++;return 1; }
        if(str_starts_with($sql,'UPDATE fixture_amelia_users ')) {
            foreach($this->tables['fixture_amelia_users'] as &$row){foreach($row as $key=>&$value){if($key!=='id')$value=null;}}unset($row,$value);$this->writes++;return 1;
        }
        if(str_starts_with($sql,'UPDATE fixture_amelia_notifications ')) {foreach($this->tables['fixture_amelia_notifications'] as &$row)$row['status']='disabled';unset($row);$this->writes++;return 1;}
        throw new RuntimeException('Unexpected fixture statement');
    }
    function insert($table,$row) {if(str_ends_with($table,'uwnext_events')){$GLOBALS['events'][]=$row;return 1;}$this->tables[$table][]=$row;return 1;}
    function update($table,$data,$where) {foreach($this->tables[$table] as &$row){if($row['id']===$where['id'])$row=array_merge($row,$data);}unset($row);return 1;}
}
require dirname(__DIR__).'/includes/local-store.php';
require __DIR__.'/staging-isolation/uwnext-amelia-staging-isolation.php';
function reset_fixture() {
    $GLOBALS['wpdb']=new IsolationDB();$GLOBALS['can']=true;$GLOBALS['site']='https://booking.fractalillumination.com';
    $GLOBALS['options']=array('home'=>$GLOBALS['site'],'siteurl'=>$GLOBALS['site'],
        'active_plugins'=>array('ameliabooking/ameliabooking.php','unlimited-wp-mcp-next/unlimited-wp-mcp-next.php','post-smtp/postman-smtp.php','helper/helper.php'),
        'amelia_settings'=>json_encode(array('notifications'=>array('smsSignedIn'=>true,'smsApiToken'=>'synthetic-sms-secret'),'webHooks'=>array('fixture'),'weekSchedule'=>array('monday'=>'09:00'))));
    $GLOBALS['wpdb']->tables=array('fixture_amelia_users'=>array(array('id'=>1,'googleCalendarId'=>'fixture-calendar','outlookCalendarId'=>null,'appleCalendarId'=>null,'employeeAppleCalendar'=>null,'zoomUserId'=>'fixture-zoom')),
        'fixture_amelia_notifications'=>array(array('id'=>1,'status'=>'enabled'),array('id'=>2,'status'=>'disabled')),
        'fixture_amelia_providers_to_google_calendar'=>array(array('id'=>1,'token'=>'synthetic-google-secret')),
        'fixture_amelia_providers_to_outlook_calendar'=>array(array('id'=>2,'token'=>'synthetic-outlook-secret')),
        'fixture_amelia_mobile_devices'=>array(array('id'=>3,'expoPushToken'=>'synthetic-push-secret')));
}
$passed=0;function check($condition,$message){global $passed;if(!$condition)throw new RuntimeException($message);$passed++;}
reset_fixture();$originalTables=$wpdb->tables;$originalOptions=$options;
UWNext_Amelia_Staging_Isolation::isolate();$report=UWNext_Amelia_Staging_Isolation::report();
check($report['guards_enabled'] && array_sum($report['counts'])===0,'All cloned connections, push tokens and notifications isolated');
check($report['sms_signed_in']===false && $report['whatsapp_enabled']===false && $report['configured_webhooks']===0,'Transport flags disabled');
check(!in_array('post-smtp/postman-smtp.php',$report['active_plugins'],true),'Other integration plugins are paused');
$backup=get_option(UWNext_Amelia_Staging_Isolation::BACKUP);
foreach(array('synthetic-google-secret','synthetic-sms-secret','synthetic-outlook-secret','synthetic-push-secret') as $secret) {
    check(!str_contains($backup,$secret),'Backup does not contain plaintext credentials');
    check(!str_contains(json_encode($report),$secret),'Status never returns credentials');
}
check(UWNext_Store::decrypt($backup)['settings']===$originalOptions['amelia_settings'],'Real encrypted backup round trip');
check(!str_contains(json_encode($events),'synthetic-google-secret'),'Audit never logs credentials');
try {UWNext_Amelia_Staging_Isolation::isolate();throw new RuntimeException('Backup overwritten');}catch(RuntimeException $error){check(str_contains($error->getMessage(),'already exists'),'Existing backup is preserved');}
UWNext_Amelia_Staging_Isolation::restore();
check($wpdb->tables===$originalTables,'Recovery restores connection and notification records exactly');
check($options['active_plugins']===$originalOptions['active_plugins'] && $options['amelia_settings']===$originalOptions['amelia_settings'],'Recovery restores configuration and active plugins');
check(!get_option(UWNext_Amelia_Staging_Isolation::ENABLED),'Only explicit recovery ends isolation');
reset_fixture();$original=$wpdb->tables;$wpdb->failAt='UPDATE fixture_amelia_notifications';
try {UWNext_Amelia_Staging_Isolation::isolate();throw new RuntimeException('Failure was ignored');}catch(RuntimeException $error){check(str_contains($error->getMessage(),'database operation failed'),'Database errors fail closed');}
check($wpdb->tables===$original,'Partial database changes roll back');
check((bool)get_option(UWNext_Amelia_Staging_Isolation::ENABLED) && (bool)get_option(UWNext_Amelia_Staging_Isolation::BACKUP),'Failure retains guards and encrypted recovery data');
reset_fixture();$wpdb->badEngine=true;
try {UWNext_Amelia_Staging_Isolation::isolate();throw new RuntimeException('Loose engine accepted');}catch(RuntimeException $error){check(str_contains($error->getMessage(),'InnoDB'),'Nontransactional engine rejected');}
check($wpdb->writes===0 && !get_option(UWNext_Amelia_Staging_Isolation::BACKUP),'Engine rejection precedes all changes');
reset_fixture();$options['siteurl']='https://booking.consciouslyawesome.com';
try {UWNext_Amelia_Staging_Isolation::isolate();throw new RuntimeException('Wrong site accepted');}catch(RuntimeException $error){check(str_contains($error->getMessage(),'named single-site'),'Production hostname rejected');}
check($wpdb->writes===0,'Wrong site performs no writes');
echo "PASS staging isolation and recovery: $passed checks\n";
