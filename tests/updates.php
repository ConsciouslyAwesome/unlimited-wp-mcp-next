<?php
/** Isolated WordPress API doubles: no live HTTP, database, or plugin replacement. */
error_reporting(E_ALL);
set_error_handler(function($level,$message,$file,$line){ throw new ErrorException($message,0,$level,$file,$line); });
define('ABSPATH',__DIR__.'/');
define('UWNext_NAME','Unlimited WP MCP Next');
define('UWNext_VERSION','2.0.1');
define('UWNext_PLUGIN_DIR',dirname(__DIR__).'/');
define('UWNext_PLUGIN_BASENAME','unlimited-wp-mcp-next/unlimited-wp-mcp-next.php');
define('HOUR_IN_SECONDS',3600);define('MINUTE_IN_SECONDS',60);
define('DISALLOW_FILE_MODS',true); // Even a redeemed test token cannot modify files.
$options=$site_options=$transients=$site_transients=$hooks=$routes=array();$http_calls=0;$http_response=null;$fs='direct';
class WP_Error {
    public $code,$message;
    function __construct($code,$message,$data=null){$this->code=$code;$this->message=$message;}
    function get_error_message(){return $this->message;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function __($s,$domain=null){return $s;}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);}
function add_action($hook,$callback,$priority=10,$args=1){add_filter($hook,$callback,$priority,$args);}
function add_filter($hook,$callback,$priority=10,$args=1){global $hooks;$hooks[$hook][]=array($callback,$priority,$args);}
function get_option($k,$default=false){global $options;return $options[$k]??$default;}
function update_option($k,$v,$autoload=null){global $options;$options[$k]=$v;return true;}
function get_site_option($k,$default=false){global $site_options;return $site_options[$k]??$default;}
function update_site_option($k,$v){global $site_options;$site_options[$k]=$v;return true;}
function get_transient($k){global $transients;return $transients[$k]??false;}
function set_transient($k,$v,$ttl){global $transients;$transients[$k]=$v;}
function get_site_transient($k){global $site_transients;return $site_transients[$k]['value']??false;}
function set_site_transient($k,$v,$ttl){global $site_transients;$site_transients[$k]=array('value'=>$v,'ttl'=>$ttl);}
function wp_remote_get($url,$args){global $http_calls,$http_response;$http_calls++;return $http_response;}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function get_filesystem_method(){global $fs;return $fs;}
function register_rest_route($ns,$route,$args){global $routes;$routes[$ns.$route]=$args;}
function plugin_basename($path){return UWNext_PLUGIN_BASENAME;}
function wp_json_encode($data,$flags=0){return json_encode($data,$flags);}
function rest_ensure_response($data){return new class($data){public $data,$status=200;function __construct($data){$this->data=$data;}function set_status($status){$this->status=$status;}};}
function wp_update_plugins(){} // Offers are mocked in the same cache core supplies.
function get_plugins(){return array(UWNext_PLUGIN_BASENAME=>array('Name'=>UWNext_NAME,'Version'=>'2.0.1'));}
require dirname(__DIR__).'/includes/trait-uwnext-request-detach.php';
require dirname(__DIR__).'/includes/class-uwnext-self-update.php';
require dirname(__DIR__).'/includes/class-uwnext-update-source.php';
require dirname(__DIR__).'/includes/cli/trait-uwnext-cli-plugin.php';
class Test_Update extends UWNext_Self_Update {
    public $finish=null,$reachable=true,$posted=null;
    public function __construct(){}
    protected function finish_request_function(){return $this->finish;}
    protected function probe_loopback(){return $this->reachable;}
    protected function post_loopback($route,array $body){$this->posted=array($route,$body);}
}
class Test_CLI {
    use UWNext_CLI_Plugin;
    public function update($flags=array(),$confirm=false){return $this->handle_plugin_update(array('unlimited-wp-mcp-next'),$flags,$confirm);}
    private function reject_unknown_flags(...$args){return null;}
    private function error_result($message){return array('error'=>$message);}
    private function success_result($result,...$args){return $result;}
}
$passed=0;
function check($ok,$label){global $passed;if(!$ok){throw new Exception('FAIL '.$label);} $passed++;echo 'PASS '.$label."\n";}
function release_response($changes=array()){
    $tag='v2.0.2';$base=UWNext_Update_Source::REPOSITORY;
    return array_replace(array('tag_name'=>$tag,'draft'=>false,'prerelease'=>false,'body'=>'<script>alert(1)</script>',
        'assets'=>array(array('name'=>'unlimited-wp-mcp-next.zip','state'=>'uploaded','size'=>1234,
        'browser_download_url'=>$base.'/releases/download/'.$tag.'/unlimited-wp-mcp-next.zip'))),$changes);
}
function offer($data,$status=200){global $site_transients,$http_response;unset($site_transients[UWNext_Update_Source::CACHE]);$http_response=array('status'=>$status,'body'=>is_array($data)?json_encode($data):$data);return UWNext_Update_Source::instance()->update_offer(false,array(),UWNext_PLUGIN_BASENAME,array());}
$source=UWNext_Update_Source::instance();
check(isset($hooks['update_plugins_github.com'],$hooks['plugins_api']),'source registers core update and details hooks');
$sentinel=(object)array('untouched'=>true);
check($source->update_offer($sentinel,array(),'vibe-ai/vibe-ai.php',array())===$sentinel&&$http_calls===0,'WPVibe and other plugin update offers remain untouched');
$good=offer(release_response());
check($good['version']==='2.0.2'&&$good['slug']==='unlimited-wp-mcp-next','stable release produces core update metadata');
check($good['package']===UWNext_Update_Source::REPOSITORY.'/releases/download/v2.0.2/unlimited-wp-mcp-next.zip','package is the Unlimited plugin asset');
$calls=$http_calls;$source->update_offer(false,array(),UWNext_PLUGIN_BASENAME,array());
check($calls===$http_calls&&$site_transients[UWNext_Update_Source::CACHE]['ttl']===3600,'successful metadata is cached per network for one hour');
$info=$source->plugin_information(false,'plugin_information',(object)array('slug'=>'unlimited-wp-mcp-next'));
check($info->version==='2.0.2'&&str_contains($info->sections['changelog'],'&lt;script&gt;'),'plugin details use the same release and escape notes');
check($source->plugin_information($sentinel,'plugin_information',(object)array('slug'=>'vibe-ai'))===$sentinel,'other plugin detail requests remain untouched');
foreach(array(array('draft'=>true),array('prerelease'=>true),array('tag_name'=>'v2.1.0-rc1'),array('tag_name'=>'../../main'),array('assets'=>array())) as $bad){check(false===offer(release_response($bad)),'reject draft/prerelease/invalid tag/missing asset '.json_encode($bad));}
$bad=release_response();$bad['assets'][0]['browser_download_url']='https://github.com/awesomemotive/wpvibe-ai-mcp/releases/download/v2.0.2/unlimited-wp-mcp-next.zip';
check(false===offer($bad),'foreign repository package rejected');
$bad=release_response();$bad['assets'][0]['state']='new';check(false===offer($bad),'incomplete upload rejected');
check(false===offer('not JSON'),'malformed metadata gives no update offer');
check(false===offer(array('message'=>'rate limited'),403),'rate limit gives no update offer');
$calls=$http_calls;$source->update_offer(false,array(),UWNext_PLUGIN_BASENAME,array());
check($calls===$http_calls&&$site_transients[UWNext_Update_Source::CACHE]['ttl']===300,'failed metadata is cached for five minutes');
unset($site_transients[UWNext_Update_Source::CACHE]);$http_response=new WP_Error('transport','no network');
check(false===$source->update_offer(false,array(),UWNext_PLUGIN_BASENAME,array()),'transport errors fail closed');
$updater=UWNext_Self_Update::instance();$updater->register_routes();
check(isset($routes['unlimited-wp-mcp-next/v1/self-update/health'],$routes['unlimited-wp-mcp-next/v1/self-update/run']),'loopback routes use only Unlimited namespace');
$site_options['auto_update_plugins']=array('other/plugin.php',123,UWNext_PLUGIN_BASENAME);
check(UWNext_Self_Update::auto_update_list()===array('other/plugin.php',UWNext_PLUGIN_BASENAME),'network auto-update list retains upstream string filtering');
UWNext_Self_Update::set_auto_update_list(array('other/plugin.php'));
check($site_options['auto_update_plugins']===array('other/plugin.php'),'auto-update changes use network site option');
$test=new Test_Update();$fs='ftpext';
check(is_wp_error($test->schedule_self_update('2.0.1','2.0.2'))&&empty($options),'filesystem credentials required before scheduling');$fs='direct';
$test->reachable=false;
check(is_wp_error($test->schedule_self_update('2.0.1','2.0.2'))&&empty($options),'unreachable loopback does not schedule');$test->reachable=true;
$state=$test->schedule_self_update('2.0.1','2.0.2','2.0.2');
check($state['status']==='scheduled'&&$state['method']==='loopback'&&$state['expect_version']==='2.0.2','loopback schedule retains exact version assertion');
$raw=$test->posted[1]['token'];
check($test->posted[0]==='unlimited-wp-mcp-next/v1/self-update/run'&&hash_equals($state['token_hash'],hash('sha256',$raw))&&!str_contains(json_encode($options),$raw),'loopback stores only a token hash and sends token to its own endpoint');
check(is_wp_error($test->schedule_self_update('2.0.1','2.0.2')),'fresh pending update cannot be rescheduled');
check(is_wp_error($test->handle_run_endpoint(array('token'=>'wrong')))&&$options[UWNext_Self_Update::OPTION]['status']==='scheduled','invalid token cannot start update');
$options[UWNext_Self_Update::OPTION]['scheduled_at']=time()-901;
check(is_wp_error($test->handle_run_endpoint(array('token'=>$raw))),'expired loopback token rejected');
$options[UWNext_Self_Update::OPTION]=$state;
$response=$test->handle_run_endpoint(array('token'=>$raw));
check($response->status===202&&$options[UWNext_Self_Update::OPTION]['status']==='failed'&&!isset($options[UWNext_Self_Update::OPTION]['token_hash']),'valid loopback token is consumed before file-policy failure');
check(is_wp_error($test->handle_run_endpoint(array('token'=>$raw))),'consumed loopback token cannot be replayed');
$options[UWNext_Self_Update::OPTION]=$state;
$options[UWNext_Self_Update::OPTION]['scheduled_at']=time()-601;
$state=$test->schedule_self_update('2.0.1','2.0.2');
check($state['status']==='scheduled','stale pending update permits a fresh schedule');
$options=array();$test->finish='mock_finish';
$state=$test->schedule_self_update('2.0.1','2.0.2','2.0.2');
check($state['method']==='request'&&isset($hooks['rest_pre_serve_request']),'response-detach path retains upstream rest hook');
$test->run_self_update($state);
check($options[UWNext_Self_Update::OPTION]['status']==='failed'&&str_contains($options[UWNext_Self_Update::OPTION]['error'],'DISALLOW_FILE_MODS'),'execution respects WordPress file-modification policy');
$site_transients['update_plugins']['value']=(object)array('response'=>array(UWNext_PLUGIN_BASENAME=>(object)array('new_version'=>'2.0.2','slug'=>'unlimited-wp-mcp-next')));
$cli=new Test_CLI();$options=array();$preview=$cli->update();
check(!empty($preview['requires_confirmation'])&&empty($options),'CLI update preview still requires approval and does not schedule');
$preview=$cli->update(array('dry_run'=>true));
check($preview['dry_run']&&$preview['updates_available'][0]['new_version']==='2.0.2'&&empty($options),'CLI dry run reports release without scheduling');
check(isset($cli->update(array('expect_version'=>'2.0.3'),true)['error'])&&empty($options),'CLI refuses mismatched expected version before scheduling');
echo 'RESULT '.$passed." checks passed\n";
