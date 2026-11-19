<?php
/** Execute restored recovery branches with core doubles; never replaces a plugin. */
error_reporting(E_ALL);
set_error_handler(static function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
$runtime=realpath(dirname(__DIR__).'/../.runtime');
if(!$runtime)throw new RuntimeException('Local test runtime is missing.');
$fixture=$runtime.'/updater-recovery-doubles';
if(!is_dir($fixture.'/wp-admin/includes'))mkdir($fixture.'/wp-admin/includes',0777,true);
foreach(array('misc','admin','class-wp-upgrader') as $name)file_put_contents($fixture.'/wp-admin/includes/'.$name.'.php',"<?php // Empty test-only core include; APIs are doubled below.\n");
define('ABSPATH',$fixture.'/');define('WP_PLUGIN_DIR',$fixture.'/plugins');
define('UWNext_PLUGIN_BASENAME','unlimited-wp-mcp-next/unlimited-wp-mcp-next.php');define('UWNext_VERSION','2.0.1');define('MINUTE_IN_SECONDS',60);
class WP_Error {
    private $code,$message;
    function __construct($code,$message){$this->code=$code;$this->message=$message;}
    function get_error_code(){return $this->code;}function get_error_message(){return $this->message;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function __($s,...$rest){return $s;}
function add_action(...$args){}function add_filter(...$args){}function remove_filter(...$args){}function remove_action(...$args){}function has_action(...$args){return false;}
function get_filesystem_method(){return 'direct';}function get_plugins(){return array();}function wp_update_plugins(){}
function get_site_transient($key){return (object)array('response'=>array(UWNext_PLUGIN_BASENAME=>(object)array('new_version'=>'2.0.2')));}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,...$rest){$GLOBALS['options'][$key]=$value;return true;}
function is_multisite(){return $GLOBALS['network'];}function is_main_site(...$args){return false;}function get_main_network_id(){return 1;}function get_main_site_id(...$args){return 1;}
function switch_to_blog($id){$GLOBALS['switches']++;}function restore_current_blog(){$GLOBALS['restores']++;}
function is_plugin_active($plugin){return $GLOBALS['active'];}function is_plugin_active_for_network($plugin){return $GLOBALS['network'];}
function activate_plugin($plugin,$redirect,$network,$silent){$GLOBALS['activation']=array($network,$silent);if($GLOBALS['mode']==='activation-fails')return new WP_Error('activation_failed','Test activation failure');$GLOBALS['active']=true;return null;}
function get_plugin_data(...$args){return array('Version'=>$GLOBALS['disk']);}
function admin_url($path){return 'https://fixture.test/wp-admin/'.$path;}function home_url($path){return 'https://fixture.test/';}
function apply_filters($hook,$value,...$args){return $value;}function wp_remote_get(...$args){return array();}
class UWNext_Change_Tracker {static function mark($args){$GLOBALS['marked']=true;}}
class WP_Upgrader {
    static function create_lock($name){if($GLOBALS['locked'])return false;$GLOBALS['locked']=true;return true;}
    static function release_lock($name){$GLOBALS['locked']=false;}
}
class WP_Automatic_Updater {
    function is_disabled(){return $GLOBALS['mode']==='fallback';}function is_vcs_checkout($dir){return false;}
    function update($type,$item){
        if($GLOBALS['mode']==='throw'){file_put_contents(ABSPATH.'.maintenance','test');$GLOBALS['active']=false;throw new RuntimeException('Test interrupted upgrader');}
        $GLOBALS['active']=false;
        if($GLOBALS['mode']==='rollback')return new WP_Error('plugin_update_fatal_error_rollback_successful','Test fatal check rolled back');
        $GLOBALS['disk']='2.0.2';
        if($GLOBALS['mode']==='rollback-failed')return new WP_Error('plugin_update_fatal_error_rollback_failed','Test rollback failed');
        return array('updated'=>true);
    }
}
class Automatic_Upgrader_Skin {}
class Plugin_Upgrader {
    function __construct($skin){}
    function bulk_upgrade($plugins){$GLOBALS['disk']='2.0.2';return array(UWNext_PLUGIN_BASENAME=>array('updated'=>true));}
}
require dirname(__DIR__).'/includes/trait-uwnext-request-detach.php';
require dirname(__DIR__).'/includes/class-uwnext-self-update.php';
$updater=UWNext_Self_Update::instance();$passed=0;
function check($ok,$label){global $passed;if(!$ok)throw new RuntimeException('FAIL '.$label);$passed++;echo 'PASS '.$label."\n";}
function reset_recovery($mode,$network=false){
    $GLOBALS['options']=array();$GLOBALS['disk']='2.0.1';$GLOBALS['active']=true;$GLOBALS['locked']=false;$GLOBALS['network']=$network;$GLOBALS['mode']=$mode;$GLOBALS['marked']=false;$GLOBALS['activation']=null;$GLOBALS['switches']=$GLOBALS['restores']=0;
}
$state=array('from_version'=>'2.0.1','to_version'=>'2.0.2','expect_version'=>'2.0.2');
foreach(array('success','rollback','rollback-failed','throw','fallback','activation-fails') as $mode){
    reset_recovery($mode,true);$ok=$updater->run_self_update($state);$result=get_option(UWNext_Self_Update::OPTION);
    if(in_array($mode,array('success','fallback','activation-fails'),true))check($ok&&$result['status']==='success'&&$result['to_version']==='2.0.2','update reports fresh disk version: '.$mode);
    if($mode==='rollback')check(!$ok&&$result['rolled_back']&&$disk==='2.0.1','fatal-check rollback recorded with old disk version');
    if($mode==='rollback-failed')check(!$ok&&$result['rollback_failed']&&$result['disk_version']==='2.0.2','failed rollback outranks changed disk version');
    if($mode==='throw')check(!$ok&&$result['status']==='failed'&&!file_exists(ABSPATH.'.maintenance'),'interrupted update clears its own maintenance marker');
    if($mode==='activation-fails')check(!$result['still_active']&&!empty($result['warning']),'failed reactivation produces explicit recovery warning');
    elseif($mode!=='fallback')check($active&&$activation===array(true,true),'network activation restored silently: '.$mode);
    check(!$locked&&$switches===$restores&&$switches>0,'network updater lock and site context released: '.$mode);
}
reset_recovery('success');$locked=true;
check(!$updater->run_self_update($state)&&$locked&&$disk==='2.0.1','existing core update lock prevents overlap without releasing another lock');
reset_recovery('success');$wrong=$state;$wrong['expect_version']='2.0.3';
check(!$updater->run_self_update($wrong)&&$disk==='2.0.1'&&!$marked,'expected version rechecked before execution');

// Simulate shutdown recovery without killing PHP or touching actual plugin files.
reset_recovery('success',true);$active=false;$locked=true;
file_put_contents(ABSPATH.'.maintenance','test');
$options[UWNext_Self_Update::OPTION]=array('status'=>'running');
$guard=new ReflectionProperty(UWNext_Self_Update::class,'run_guard');
$guard->setValue($updater,array('from'=>'2.0.1','to'=>'2.0.2','maintenance_before'=>false,'was_active'=>true,'was_network'=>true));
$recover=new ReflectionMethod(UWNext_Self_Update::class,'recover_interrupted_run');$recover->invoke($updater);
check($active&&!$locked&&!file_exists(ABSPATH.'.maintenance')&&get_option(UWNext_Self_Update::OPTION)['status']==='failed','shutdown recovery restores activation, lock and owned maintenance state');
file_put_contents(ABSPATH.'.maintenance','preexisting');
$guard->setValue($updater,array('from'=>'2.0.1','to'=>'2.0.2','maintenance_before'=>true,'was_active'=>true,'was_network'=>true));$recover->invoke($updater);
check(file_exists(ABSPATH.'.maintenance'),'recovery preserves maintenance marker owned by someone else');
unlink(ABSPATH.'.maintenance');
echo "RESULT $passed updater recovery checks passed\n";
