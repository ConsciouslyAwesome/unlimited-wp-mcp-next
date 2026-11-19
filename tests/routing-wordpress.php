<?php
/** Test-only stdio bridge to real local WordPress; no listener or outbound HTTP. */
$input=json_decode(stream_get_contents(STDIN),true,64,JSON_THROW_ON_ERROR);
$workspace=realpath(dirname(__DIR__).'/..');$runtime=realpath($workspace.'/.runtime');
$fixture=realpath($argv[1]);
if(!$fixture||dirname($fixture)!==$runtime||!str_starts_with(basename($fixture),'routing-acceptance-'))throw new RuntimeException('Not an isolated routing fixture.');
$site=$input['site_url'];$home=$input['home_url'];$request_url=$input['request']['url']??$site.'/';
$url=parse_url($request_url);
$_SERVER['HTTPS']='on';$_SERVER['HTTP_HOST']=$url['host'];$_SERVER['SERVER_NAME']=$url['host'];$_SERVER['SERVER_PORT']='443';
$_SERVER['REQUEST_URI']=($url['path']??'/').(isset($url['query'])?'?'.$url['query']:'');$_SERVER['REQUEST_METHOD']=$input['request']['method']??'GET';$_SERVER['REMOTE_ADDR']='127.0.0.1';
parse_str($url['query']??'',$_GET);
define('ABSPATH',str_replace('\\','/',$runtime.'/wordpress/'));
define('WP_CONTENT_DIR',$runtime.'/next-generation-fixture/wp-content');define('WP_CONTENT_URL',$home.'/wp-content');
define('DB_DIR',$fixture.'/database/');define('DB_NAME','routing_fixture');define('DB_USER','fixture');define('DB_PASSWORD','');define('DB_HOST','localhost');
foreach(array('AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT') as $key)define($key,hash('sha256','routing-fixture-'.$fixture.'-'.$key));
$table_prefix='route_';
define('WP_HOME',$home);define('WP_SITEURL',$site);define('WP_INSTALLING',true);define('WP_ENVIRONMENT_TYPE','local');
define('WP_DEBUG',true);define('WP_DEBUG_DISPLAY',false);define('WP_DEBUG_LOG',$fixture.'/debug.log');
define('DISABLE_WP_CRON',true);define('WP_HTTP_BLOCK_EXTERNAL',true);define('FS_METHOD','direct');
ob_start();
require ABSPATH.'wp-settings.php';require_once ABSPATH.'wp-admin/includes/upgrade.php';require_once ABSPATH.'wp-admin/includes/plugin.php';
add_filter('pre_http_request',static function(){return new WP_Error('routing_fixture_no_network','No outbound HTTP in routing acceptance.');});
if(!is_blog_installed())wp_install('Routing Fixture','route_admin','route@example.invalid',false,'',bin2hex(random_bytes(24)));
update_option('permalink_structure',$input['pretty']?'/%postname%/':'');$wp_rewrite->init();
require dirname(__DIR__).'/unlimited-wp-mcp-next.php';uwnext_activate();
foreach(array('UWNext_Approval_Bypass','UWNext_Self_Update','UWNext_Update_Source','UWNext_REST','UWNext_Elementor','UWNext_Beaver','UWNext_Breakdance','UWNext_Bricks','UWNext_Preview','UWNext_Live_Reload','UWNext_Fields','UWNext_Edit_Affordance','UWNext_Builder_Login') as $class)$class::instance();
$admin=get_user_by('login','route_admin');$server=rest_get_server();
$operation=$input['operation']??'request';
if($operation==='consent') {
    // Substitute only the human browser action. Native signed-client validation,
    // PKCE, code consumption, token/refresh handling and MCP below are real code.
    wp_set_current_user($admin->ID);parse_str(parse_url($input['authorization_url'],PHP_URL_QUERY),$params);
    $client=UWNext_Auth::validate_authorization($params);
    if(!$client)throw new RuntimeException('Native WordPress rejected authorization.');
    $connection=UWNext_Auth::create_connection($admin->ID,$client['name']);$code=bin2hex(random_bytes(48));
    UWNext_Store::put('code',$code,array('connection'=>$connection,'client'=>$params['client_id'],'redirect'=>$params['redirect_uri'],'challenge'=>$params['code_challenge'],'resource'=>UWNext_Auth::endpoint(),'expires'=>time()+120,'refresh'=>true));
    $body=array('code'=>$code,'state'=>$params['state'],'iss'=>UWNext_Auth::client_issuer($client));$status=200;$headers=array();
} elseif($operation==='approve') {
    wp_set_current_user($admin->ID);$body=array('approved'=>UWNext_Admin::approve($input['approval_id'],$admin->ID));$status=200;$headers=array();
} else {
    $path=$url['path']??'/';$home_path=rtrim(parse_url($home,PHP_URL_PATH)??'','/');$site_path=rtrim(parse_url($site,PHP_URL_PATH)??'','/');$route=null;
    // Model the configured web-server entry points, without making HTTP calls.
    if($input['pretty']&&str_starts_with($path,$home_path.'/wp-json/'))$route=substr($path,strlen($home_path.'/wp-json'));
    if(isset($_GET['rest_route'])&&in_array($path,array($home_path.'/',$home_path.'/index.php',$site_path.'/',$site_path.'/index.php'),true))$route=$_GET['rest_route'];
    if($route===null){$body=array('code'=>'rest_no_route');$status=404;$headers=array();}
    else {
        wp_set_current_user(0);$req=new WP_REST_Request($_SERVER['REQUEST_METHOD'],$route);$req->set_query_params($_GET);
        foreach($input['request']['headers']??array() as $key=>$value)$req->set_header($key,$value);
        $req->set_body($input['request']['body']??'');
        if(str_starts_with((string)$req->get_header('content-type'),'application/x-www-form-urlencoded')){parse_str($req->get_body(),$params);$req->set_body_params($params);}
        $result=rest_do_request($req);$body=$result->get_data();$status=$result->get_status();$headers=$result->get_headers();
    }
}
ob_end_clean();
echo json_encode(array('status'=>$status,'headers'=>$headers,'body'=>$body),JSON_THROW_ON_ERROR);
