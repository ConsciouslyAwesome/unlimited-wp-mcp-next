<?php
/** In-memory connection diagnostics only; no HTTP, credentials, or WordPress database. */
error_reporting(E_ALL);set_error_handler(function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
define('ABSPATH',__DIR__.'/');define('UWNext_NS','unlimited-wp-mcp-next/v1');define('UWNext_VERSION','2.0.1');
$url='https://fixture.example.com';$ssl=true;$admin=true;$multi=false;$super=false;$user=7;$app=false;$options=array();$http=null;
class WP_Error {}
class WP_REST_Response {public $data,$status,$headers;function __construct($data,$status=200,$headers=array()){$this->data=$data;$this->status=$status;$this->headers=$headers;}}
class UWNext_Auth {public static $connection='grant';static function site_binding(){return site_url();}static function endpoint(){return rest_url(UWNext_NS.'/mcp');}static function metadata(){return array('issuer'=>rest_url(UWNext_NS.'/oauth'),'token_endpoint'=>rest_url(UWNext_NS.'/oauth/token'));}}
function add_action(...$a){}function register_rest_route(...$a){}
function is_ssl(){global $ssl;return $ssl;}function site_url(){global $url;return $url;}
function rest_url($path=''){return site_url().'/wp-json/'.$path;}
function get_bloginfo($key){return 'fixture-core';}
function wp_parse_url($url,$part){return parse_url($url,$part);}
function is_user_logged_in(){return get_current_user_id()>0;}function current_user_can(...$args){global $admin;return $admin;}
function get_current_user_id(){global $user;return $user;}function wp_get_current_user(){return (object)array('ID'=>get_current_user_id());}
function is_multisite(){global $multi;return $multi;}function is_super_admin(){global $super;return $super;}
function wp_is_application_passwords_available_for_user($u){global $app;return $app;}
function get_option($k){global $options;return $options[$k]??false;}function update_option($k,$v,$autoload=false){global $options;$options[$k]=$v;return true;}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES);}
function wp_remote_get(...$args){global $http;return $http;}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_remote_retrieve_response_code($r){return $r['code'];}function wp_remote_retrieve_header($r,$k){return $r['headers'][$k]??'';}
function wp_remote_retrieve_body($r){return $r['body'];}
require dirname(__DIR__).'/includes/local-connection.php';
$passed=0;function check($v,$label){global $passed;if(!$v)throw new Exception('FAIL '.$label);$passed++;echo "PASS $label\n";}
$report=UWNext_Connection::local_report();
check($report[0]['status']==='passed','HTTPS passes for secure canonical WordPress URL');
check($report[2]['status']==='passed','site administrator capability reported');
check($report[3]['status']==='limited'&&str_contains($report[3]['summary'],'does not require'),'disabled Application Passwords do not block Unlimited OAuth');
$ssl=false;check(UWNext_Connection::local_report()[0]['status']==='failed','insecure WordPress request fails HTTPS readiness');$ssl=true;
$admin=false;check(UWNext_Connection::local_report()[2]['status']==='limited','non-administrator receives limited account warning');$admin=true;
$multi=true;check(UWNext_Connection::local_report()[2]['status']==='limited','multisite administrator warning requires super admin');$multi=false;
$_SERVER['PHP_AUTH_USER']='gate';check(UWNext_Connection::local_report()[4]['status']==='failed','Basic password gate visible to browser is identified');unset($_SERVER['PHP_AUTH_USER']);
check(str_contains(UWNext_Connection::warnings_html(),'gateway verifies a site read after authorization'),'authorization warning does not claim remote verification');
$challenge=str_repeat('a',64);
$request=new class($challenge) implements ArrayAccess {
    private $challenge;function __construct($v){$this->challenge=$v;}function get_header($k){return 'Unlimited-Loopback '.$this->challenge;}
    function offsetExists(mixed $k):bool{return $k==='challenge';}function offsetGet(mixed $k):mixed{return $this->challenge;}
    function offsetSet(mixed $k,mixed $v):void{}function offsetUnset(mixed $k):void{}
};
$response=UWNext_Connection::public_readiness($request);
check($response->data['challenge']===$challenge&&$response->data['authorization_seen']===true,'nonce echo and header probe disclose no credential');
check(str_contains($response->headers['Cache-Control'],'no-store'),'readiness response is explicitly uncacheable');
UWNext_Connection::note_read(array('isError'=>false));check(UWNext_Connection::current_read()['state']==='read served','authenticated local read is observed without claiming gateway receipt');
$url='https://clone.example.com';check(UWNext_Connection::current_read()===null,'copied observation rejected on cloned WordPress URL');$url='https://fixture.example.com';
$user=8;check(UWNext_Connection::current_read()===null,'copied observation rejected for another account');$user=7;
UWNext_Auth::$connection='other';check(UWNext_Connection::current_read()===null,'observation cannot cross connections');UWNext_Auth::$connection='grant';
$key=array_key_first($options);$options[$key]['observed_at']=time()-301;check(UWNext_Connection::current_read()===null,'old local read cannot report current connection state');
$http=array('code'=>200,'body'=>json_encode(array('namespaces'=>array('wp/v2',UWNext_NS))));check(UWNext_Connection::rest_report()['status']==='passed','REST loopback verifies required namespaces');
$http=array('code'=>401,'headers'=>array('www-authenticate'=>'Basic realm=staging'),'body'=>'gate');check(UWNext_Connection::rest_report()['status']==='failed','REST loopback distinguishes Basic password gate');
$http=array('code'=>403,'body'=>json_encode(array('code'=>'rest_disabled')));check(UWNext_Connection::rest_report()['status']==='limited','restricted anonymous REST is advisory for OAuth');
$http=array('code'=>200,'body'=>'<html>maintenance</html>');check(UWNext_Connection::rest_report()['status']==='failed','HTML interception produces maintenance/security guidance');
$http=new WP_Error();check(UWNext_Connection::rest_report()['status']==='unknown','loopback transport failure is not mistaken for a diagnosis');
echo "RESULT $passed connection checks passed\n";
