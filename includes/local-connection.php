<?php
/** Local readiness/observations adapted from pinned GPL WPVibe 1.20.5. No vendor services. */
defined('ABSPATH') || exit;
final class UWNext_Connection {
    public static function register() {
        register_rest_route(UWNext_NS,'/connection/preflight',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'public_readiness'),'args'=>array('challenge'=>array('type'=>'string','required'=>true,'pattern'=>'^[a-f0-9]{64}$'))));
    }
    public static function public_readiness($request) {
        return new WP_REST_Response(array('plugin'=>'unlimited-wp-mcp-next','plugin_version'=>UWNext_VERSION,'wp_version'=>get_bloginfo('version'),'site_url'=>UWNext_Auth::site_binding(),'challenge'=>$request['challenge'],'authorization_seen'=>hash_equals('Unlimited-Loopback '.$request['challenge'],(string)$request->get_header('authorization')),'https'=>is_ssl()&&wp_parse_url(site_url(),PHP_URL_SCHEME)==='https','oauth_available'=>extension_loaded('openssl'),'oauth'=>UWNext_Auth::metadata()),200,array('Cache-Control'=>'no-store, private, max-age=0','Pragma'=>'no-cache','Vary'=>'Authorization'));
    }
    public static function local_report() {
        $https=is_ssl()&&wp_parse_url(site_url(),PHP_URL_SCHEME)==='https';
        $admin=is_user_logged_in()&&current_user_can('manage_options')&&(!is_multisite()||is_super_admin());
        $app=function_exists('wp_is_application_passwords_available_for_user')&&wp_is_application_passwords_available_for_user(wp_get_current_user());
        $basic=!empty($_SERVER['PHP_AUTH_USER'])||stripos((string)($_SERVER['HTTP_AUTHORIZATION']??''),'Basic ')===0;
        return array(
            array('stage'=>'WordPress HTTPS','status'=>$https?'passed':'failed','summary'=>$https?'WordPress uses HTTPS.':'Enable a valid HTTPS certificate and confirm WordPress Address in Settings > General.'),
            array('stage'=>'OAuth authorization','status'=>extension_loaded('openssl')?'passed':'failed','summary'=>extension_loaded('openssl')?'Local OAuth encryption is available.':'Enable PHP OpenSSL before connecting.'),
            array('stage'=>'Current WordPress account','status'=>$admin?'passed':'limited','summary'=>$admin?'This account is a site administrator.':'This account has limited permissions. For full access use an administrator; on multisite use a super administrator.'),
            array('stage'=>'Application Password availability','status'=>$app?'passed':'limited','summary'=>$app?'Application Passwords are available; no credential has been tested.':'Application Passwords are unavailable for this account. Unlimited OAuth does not require them; only direct Application Password connections are affected.'),
            array('stage'=>'HTTP Basic Auth','status'=>$basic?'failed':'not_checked','summary'=>$basic?'A Basic Authorization credential is present on this browser request. Use the WordPress login and allow the gateway through any staging password gate.':'No Basic credential is visible on this browser request. This does not prove that the gateway can pass a password gate.'),
        );
    }
    public static function warnings_html() {
        $html='<section><h2>Connection readiness</h2><p>These local checks do not prove a remote authenticated connection. The gateway verifies a site read after authorization.</p>';
        foreach(self::local_report() as $row){$html.='<p><strong>'.esc_html($row['stage']).': '.esc_html($row['status']).'</strong> — '.esc_html($row['summary']).'</p>';}
        return $html.'</section>';
    }
    public static function note_read($result) {
        if(empty(UWNext_Auth::$connection))return;
        $id=hash('sha256',UWNext_Auth::$connection);
        update_option('uwnext_connection_read_'.$id,array('site'=>UWNext_Auth::site_binding(),'resource'=>UWNext_Auth::endpoint(),'user'=>get_current_user_id(),'connection'=>UWNext_Auth::$connection,'observed_at'=>time(),'state'=>empty($result['isError'])?'read served':'failing'),false);
    }
    public static function current_read() {
        if(empty(UWNext_Auth::$connection))return null;
        return self::observation_for(UWNext_Auth::$connection,get_current_user_id());
    }
    public static function observation_for($connection,$user) {
        $row=get_option('uwnext_connection_read_'.hash('sha256',$connection));
        return is_array($row)&&$row['site']===UWNext_Auth::site_binding()&&$row['resource']===UWNext_Auth::endpoint()&&$row['user']===$user&&$row['connection']===$connection&&$row['observed_at']<=time()&&$row['observed_at']>time()-300?$row:null;
    }
    public static function rest_report() {
        $response=wp_remote_get(rest_url(),array('timeout'=>8,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>262144));
        if(is_wp_error($response))return array('status'=>'unknown','summary'=>'The site could not reach its own REST API over HTTPS. This does not identify the remote gateway result. Ask the host to inspect loopback HTTPS.');
        $code=(int)wp_remote_retrieve_response_code($response);$www=wp_remote_retrieve_header($response,'www-authenticate');
        $body=json_decode(wp_remote_retrieve_body($response),true);
        if($code===401&&stripos(is_array($www)?implode(',',$www):(string)$www,'basic')!==false)return array('status'=>'failed','summary'=>'HTTP Basic Auth or a staging password gate blocks REST before WordPress. Ask the host to allow the Unlimited gateway through the gate.');
        if($code===200&&is_array($body)&&isset($body['namespaces']))return array('status'=>array_diff(array('wp/v2',UWNext_NS),$body['namespaces'])?'failed':'passed','summary'=>'REST returned JSON; the wp/v2 and Unlimited namespaces must both be present.');
        return array('status'=>is_array($body)&&in_array($code,array(401,403),true)?'limited':'failed','summary'=>is_array($body)?'Anonymous REST access is restricted. Confirm that Unlimited OAuth endpoints remain available.':'REST returned a web page instead of JSON. Check maintenance mode, security plugins, CDN/firewall and cache exclusions for /wp-json/.');
    }
}
add_action('rest_api_init',array('UWNext_Connection','register'));
