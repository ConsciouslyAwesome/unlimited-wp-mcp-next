<?php
defined('ABSPATH') || exit;
final class UWNext_Auth {
    public static $app=null;
    public static $connection='';
    public static function endpoint() { return rest_url(UWNext_NS.'/mcp'); }
    // Registration site binding must survive discovery-issuer changes.
    public static function site_binding() { return untrailingslashit(site_url()); }
    public static function issuer() {
        $url=untrailingslashit(rest_url(UWNext_NS.'/oauth'));
        // OAuth issuers cannot contain a query. Plain permalinks retain legacy discovery.
        return wp_parse_url($url,PHP_URL_QUERY)!==null ? self::site_binding() : $url;
    }
    public static function client_issuer($client) { return $client['issuer']??self::site_binding(); }
    public static function secure() { return is_ssl(); }
    public static function error($code='invalid_token',$status=401) { return new WP_Error($code,'Authentication failed.',array('status'=>$status)); }
    public static function authenticate($request) {
        self::$connection='';
        if(!self::secure()) { return self::error('https_required',403); }
        $header=$request->get_header('authorization');
        if(is_string($header) && preg_match('/^Bearer ([a-f0-9]{96})$/D',$header,$m)) {
            $token=UWNext_Store::get('access',$m[1]);
            if(!$token || $token['expires']<=time() || !hash_equals(self::endpoint(),$token['resource'])) { return self::error(); }
            $conn=UWNext_Store::get('connection',$token['connection']);
            if(!$conn || empty($conn['active']) || (int)$conn['user']!==(int)$token['user'] || !get_user_by('id',$token['user'])) { return self::error(); }
            wp_set_current_user($token['user']); self::$connection=$token['connection'];
            return current_user_can('read') ? true : self::error('access_denied',403);
        }
        // Only a core-authenticated Application Password, never an ordinary cookie.
        if(self::$app && get_current_user_id()===(int)self::$app['user'] && stripos((string)$header,'Basic ')===0) {
            $item=WP_Application_Passwords::get_user_application_password(self::$app['user'],self::$app['uuid']);
            if(!$item || !current_user_can('read')) { return self::error(); }
            self::$connection='app:'.self::$app['user'].':'.self::$app['uuid']; return true;
        }
        return self::error();
    }
    public static function create_connection($user,$label) {
        $id=UWNext_Store::id();
        UWNext_Store::put('connection',$id,array('id'=>$id,'user'=>(int)$user,'label'=>sanitize_text_field($label),'active'=>true,'created'=>time()));
        return $id;
    }
    public static function issue($connection,$client='manual',$refresh=true) {
        $conn=UWNext_Store::get('connection',$connection);
        if(!$conn || !$conn['active']) { return self::error(); }
        $access=bin2hex(random_bytes(48));
        $ttl='manual'===$client ? DAY_IN_SECONDS*30 : 3600;
        $data=array('connection'=>$connection,'user'=>$conn['user'],'client'=>$client,'resource'=>self::endpoint(),'expires'=>time()+$ttl);
        UWNext_Store::put('access',$access,$data);
        $result=array('access_token'=>$access,'token_type'=>'Bearer','expires_in'=>$ttl,'scope'=>$refresh?'wordpress offline_access':'wordpress');
        if($refresh) { $secret=bin2hex(random_bytes(48)); $data['expires']=time()+DAY_IN_SECONDS*30; UWNext_Store::put('refresh',$secret,$data); $result['refresh_token']=$secret; }
        return $result;
    }
    public static function revoke($id) {
        $conn=UWNext_Store::get('connection',$id);
        if($conn) { $conn['active']=false; update_option(UWNext_Store::key('connection',$id),$conn,false); }
        UWNext_Store::log('connection','revoked');
    }
    public static function metadata($legacy=false) {
        return array('issuer'=>$legacy?self::site_binding():self::issuer(),'authorization_endpoint'=>admin_url('admin-post.php?action=uwnext_authorize'),'token_endpoint'=>rest_url(UWNext_NS.'/oauth/token'),'registration_endpoint'=>rest_url(UWNext_NS.($legacy?'/oauth/register':'/oauth/register-path')),'revocation_endpoint'=>rest_url(UWNext_NS.'/oauth/revoke'),'response_types_supported'=>array('code'),'grant_types_supported'=>array('authorization_code','refresh_token'),'code_challenge_methods_supported'=>array('S256'),'token_endpoint_auth_methods_supported'=>array('none'),'scopes_supported'=>array('wordpress','offline_access'),'authorization_response_iss_parameter_supported'=>true);
    }
    public static function resource_metadata() {
        // Prefer the REST issuer; advertise the exact legacy issuer for cached 0.1.3 clients.
        $issuers=array_values(array_unique(array(self::issuer(),self::site_binding())));
        return array('resource'=>self::endpoint(),'authorization_servers'=>$issuers,'scopes_supported'=>array('wordpress'),'bearer_methods_supported'=>array('header'));
    }
    public static function well_known() {
        $path=wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
        $sitepath=(string)wp_parse_url(site_url(),PHP_URL_PATH);
        if(in_array($path,array('/.well-known/oauth-authorization-server'.$sitepath,$sitepath.'/.well-known/oauth-authorization-server'),true)) { nocache_headers(); wp_send_json(self::metadata(true)); }
        $issuerpath=(string)wp_parse_url(self::issuer(),PHP_URL_PATH);
        if(in_array($path,array('/.well-known/oauth-authorization-server'.$issuerpath,'/.well-known/openid-configuration'.$issuerpath),true)) { nocache_headers(); wp_send_json(self::metadata()); }
        if($path==='/.well-known/oauth-protected-resource'.wp_parse_url(self::endpoint(),PHP_URL_PATH) || $path===$sitepath.'/.well-known/oauth-protected-resource') { nocache_headers(); wp_send_json(self::resource_metadata()); }
    }
    public static function register() {
        foreach(array('register'=>'register_client','register-path'=>'register_path_client','token'=>'token','revoke'=>'revoke_token') as $path=>$method) {
            register_rest_route(UWNext_NS,'/oauth/'.$path,array('methods'=>'POST','callback'=>array(__CLASS__,$method),'permission_callback'=>function() { return self::secure() ? true : self::error('https_required',403); }));
        }
        register_rest_route(UWNext_NS,'/oauth/resource',array('methods'=>'GET','callback'=>function(){return new WP_REST_Response(self::resource_metadata(),200,array('Cache-Control'=>'no-store'));},'permission_callback'=>'__return_true'));
        register_rest_route(UWNext_NS,'/oauth/\.well-known/openid-configuration',array('methods'=>'GET','callback'=>function(){return new WP_REST_Response(self::metadata(),200,array('Cache-Control'=>'no-store'));},'permission_callback'=>'__return_true'));
    }
    private static function b64($data) { return rtrim(strtr(base64_encode($data),'+/','-_'),'='); }
    public static function client($id) {
        if(!is_string($id) || strlen($id)>6000 || count(explode('.',$id))!==2) { return false; }
        list($body,$sig)=explode('.',$id);
        if(!hash_equals(hash_hmac('sha256',$body,wp_salt('secure_auth')),$sig)) { return false; }
        $data=json_decode(base64_decode(strtr($body,'-_','+/'),true),true);
        if(!is_array($data) || ($data['site']??'')!==self::site_binding()) { return false; }
        // Missing issuer denotes only the signed 0.1.3 format. No arbitrary aliases.
        if(array_key_exists('issuer',$data) && $data['issuer']!==self::issuer()) { return false; }
        return $data;
    }
    public static function valid_redirect($url) {
        if(!is_string($url) || strlen($url)>1500 || preg_match('/[\x00-\x20\x7f]/',$url)) { return false; }
        $p=wp_parse_url($url);
        return $p && empty($p['fragment']) && empty($p['user']) && empty($p['pass']) && !empty($p['host']) && (($p['scheme']??'')==='https' || (($p['scheme']??'')==='http' && in_array($p['host'],array('127.0.0.1','[::1]'),true)));
    }
    private static function oauth_error($code,$status=400) { return new WP_REST_Response(array('error'=>$code),$status,array('Cache-Control'=>'no-store','Pragma'=>'no-cache')); }
    public static function register_path_client($request) { return self::register_client($request,true); }
    public static function register_client($request,$path_issuer=false) {
        if(strlen((string)$request->get_body())>8192) { return self::oauth_error('invalid_client_metadata'); }
        $p=$request->get_json_params(); $uris=$p['redirect_uris']??null;
        if(!is_array($uris) || count($uris)<1 || count($uris)>3 || !in_array($p['token_endpoint_auth_method']??'none',array('none'),true)) { return self::oauth_error('invalid_client_metadata'); }
        foreach($uris as $uri) { if(!self::valid_redirect($uri)) { return self::oauth_error('invalid_redirect_uri'); } }
        // Self-authenticating registration avoids unauthenticated database growth and remote metadata fetches.
        $data=array('site'=>self::site_binding(),'redirect_uris'=>array_values($uris),'name'=>substr(sanitize_text_field($p['client_name']??'MCP client'),0,100),'nonce'=>UWNext_Store::id());
        if($path_issuer) { $data['issuer']=self::issuer(); }
        $body=self::b64(wp_json_encode($data)); $id=$body.'.'.hash_hmac('sha256',$body,wp_salt('secure_auth'));
        return new WP_REST_Response(array('client_id'=>$id,'client_name'=>$data['name'],'redirect_uris'=>$uris,'token_endpoint_auth_method'=>'none','grant_types'=>array('authorization_code','refresh_token'),'response_types'=>array('code')),201,array('Cache-Control'=>'no-store'));
    }
    public static function validate_authorization($p) {
        $client=self::client($p['client_id']??null);
        if(!$client || !in_array($p['redirect_uri']??'', $client['redirect_uris'],true) || ($p['response_type']??'')!=='code' || ($p['code_challenge_method']??'')!=='S256' || !is_string($p['code_challenge']??null) || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$p['code_challenge']) || ($p['resource']??'')!==self::endpoint()) { return false; }
        $scope=explode(' ',(string)($p['scope']??'wordpress'));
        if(array_diff($scope,array('wordpress','offline_access')) || !in_array('wordpress',$scope,true) || strlen((string)($p['state']??''))>1000) { return false; }
        return $client;
    }
    public static function authorize() {
        nocache_headers(); header('Referrer-Policy: no-referrer'); header('X-Frame-Options: DENY');
        if(!self::secure()) { wp_die('HTTPS is required.',403); }
        if(!is_user_logged_in()) { auth_redirect(); exit; }
        if(!current_user_can('read') || !empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['PHP_AUTH_USER'])) { wp_die('Use your WordPress browser login.',403); }
        $p=wp_unslash($_SERVER['REQUEST_METHOD']==='POST' ? $_POST : $_GET);
        $p['scope']=$p['scope']??'wordpress';$p['state']=$p['state']??'';
        foreach(array('client_id','redirect_uri','response_type','code_challenge','code_challenge_method','resource','scope','state') as $key) { if(isset($p[$key]) && !is_string($p[$key])) { wp_die('Invalid authorization request.',400); } }
        $client=self::validate_authorization($p); if(!$client) { wp_die('Invalid OAuth request. No redirect was made.',400); }
        $bound=hash('sha256',wp_json_encode(UWNext_Store::canonical(array_intersect_key($p,array_flip(array('client_id','redirect_uri','code_challenge','resource','scope','state'))))));
        if($_SERVER['REQUEST_METHOD']==='POST') {
            check_admin_referer('uwnext_oauth_'.$bound);
            if(($p['decision']??'')!=='allow') { wp_die('Connection declined.'); }
            $conn=self::create_connection(get_current_user_id(),$client['name']);
            $code=bin2hex(random_bytes(48));
            UWNext_Store::put('code',$code,array('connection'=>$conn,'client'=>$p['client_id'],'redirect'=>$p['redirect_uri'],'challenge'=>$p['code_challenge'],'resource'=>self::endpoint(),'expires'=>time()+120,'refresh'=>str_contains($p['scope']??'','offline_access')));
            UWNext_Store::log('oauth','consented');
            // Exact redirect is validated against signed registration; external redirect is intentional.
            wp_redirect(add_query_arg(array('code'=>$code,'state'=>$p['state']??'','iss'=>self::client_issuer($client)),$p['redirect_uri']),302,'Unlimited WP MCP'); exit;
        }
        echo '<!doctype html><meta charset="utf-8"><title>Connect Unlimited WP MCP</title><main style="max-width:720px;margin:4em auto;font:18px system-ui"><h1>Connect '.esc_html($client['name']).'</h1><p>This app requests WordPress management access as <strong>'.esc_html(wp_get_current_user()->user_login).'</strong>. Your WordPress capabilities apply. All writes require a separate browser approval.</p><p>Return address: <code>'.esc_html($p['redirect_uri']).'</code></p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        foreach(array('client_id','redirect_uri','response_type','code_challenge','code_challenge_method','resource','scope','state') as $key) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($p[$key]??'').'">'; }
        echo '<input type="hidden" name="action" value="uwnext_authorize">'; wp_nonce_field('uwnext_oauth_'.$bound);
        echo '<button name="decision" value="allow">Allow connection</button> <button name="decision" value="deny">Decline</button></form></main>'; exit;
    }
    public static function token($request) {
        if(strlen((string)$request->get_body())>16384) { return self::oauth_error('invalid_request'); }
        $p=$request->get_body_params();
        foreach($p as $v) { if(!is_string($v)) { return self::oauth_error('invalid_request'); } }
        if(!self::client($p['client_id']??null) || ($p['resource']??'')!==self::endpoint()) { return self::oauth_error('invalid_client'); }
        $kind=($p['grant_type']??'')==='authorization_code' ? 'code' : (($p['grant_type']??'')==='refresh_token' ? 'refresh' : '');
        if(!$kind) { return self::oauth_error('unsupported_grant_type'); }
        $secret=$p[$kind==='code'?'code':'refresh_token']??'';
        if(!preg_match('/^[a-f0-9]{96}$/D',$secret)) { return self::oauth_error('invalid_grant'); }
        $row=UWNext_Store::get($kind,$secret);
        if(!$row && $kind==='refresh') {
            $spent=UWNext_Store::get('spent_refresh',$secret);
            if($spent && hash_equals($spent['client'],$p['client_id'])) { self::revoke($spent['connection']); }
        }
        if(!$row || $row['expires']<=time() || !hash_equals($row['client'],$p['client_id']) || $row['resource']!==self::endpoint()) { return self::oauth_error('invalid_grant'); }
        if($kind==='code') {
            $verifier=$p['code_verifier']??'';
            if(!preg_match('/^[A-Za-z0-9._~-]{43,128}$/D',$verifier) || !hash_equals($row['challenge'],self::b64(hash('sha256',$verifier,true))) || ($p['redirect_uri']??'')!==$row['redirect']) { return self::oauth_error('invalid_grant'); }
        }
        if(!UWNext_Store::consume($kind,$secret,$row)) { return self::oauth_error('invalid_grant'); }
        if($kind==='refresh') { UWNext_Store::put('spent_refresh',$secret,$row); }
        $out=self::issue($row['connection'],$row['client'],$kind==='refresh' || !empty($row['refresh']));
        return is_wp_error($out) ? self::oauth_error('invalid_grant') : new WP_REST_Response($out,200,array('Cache-Control'=>'no-store','Pragma'=>'no-cache'));
    }
    public static function revoke_token($request) {
        $secret=$request->get_param('token');
        if(is_string($secret) && preg_match('/^[a-f0-9]{96}$/D',$secret)) {
            $row=UWNext_Store::get('access',$secret) ?: UWNext_Store::get('refresh',$secret);
            if($row) { self::revoke($row['connection']); }
        }
        return new WP_REST_Response(null,200,array('Cache-Control'=>'no-store'));
    }
}
