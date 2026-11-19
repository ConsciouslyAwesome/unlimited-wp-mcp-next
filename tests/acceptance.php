<?php
// Integrated checks in the disposable WordPress fixture; no live requests.
check(UWNext_VERSION==='2.0.1','acceptance: runtime reports 2.0.1');
check(!UWNext_Approval_Bypass::is_on(),'acceptance: integrated bypass defaults OFF');
$bypass_response=apply_filters('rest_post_dispatch',rpc('tools/list'),$server,new WP_REST_Request('POST','/'.UWNext_NS.'/mcp'));
check(($bypass_response->get_headers()[UWNext_Approval_Bypass::HEADER]??null)==='0','acceptance: valid bypass header reports OFF');
check((bool)preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D',UWNext_Approval_Bypass::HEADER),'acceptance: bypass header is an HTTP token');
$browser_expiry=time()+3600;
$browser_session=WP_Session_Tokens::get_instance($admin->ID)->create($browser_expiry);
$_COOKIE[LOGGED_IN_COOKIE]=wp_generate_auth_cookie($admin->ID,$browser_expiry,'logged_in',$browser_session);
$bypass_post=array('_wpnonce'=>wp_create_nonce(UWNext_Approval_Bypass::ACTION),'enabled'=>'1');
check(true===UWNext_Approval_Bypass::save_from_request($bypass_post),'acceptance: actual WordPress cookie and nonce permit administrator enable');
$bypass_response=apply_filters('rest_post_dispatch',rpc('tools/list'),$server,new WP_REST_Request('POST','/'.UWNext_NS.'/mcp'));
check(($bypass_response->get_headers()[UWNext_Approval_Bypass::HEADER]??null)==='1','acceptance: valid bypass header reports ON');
$bypass_run=tool('cli_run',array('command'=>'post list --format=json'));
check(empty($bypass_run['result']['isError']),'acceptance: bypass executes native read command through MCP');
$bypass_events=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}uwnext_events WHERE status='bypassed'",ARRAY_A);
check(count($bypass_events)===1&&$bypass_events[0]['operation']==='cli_run'&&$bypass_events[0]['connection']===$conn,'acceptance: integrated bypass receipt binds actual connection');
$bypass_refused=tool('cli_run',array('command'=>'option update uwnext_bypass_approvals 1'));
check(!empty($bypass_refused['result']['isError']),'acceptance: MCP cannot write bypass option even while bypass enabled');
UWNext_Auth::$connection=$conn;
check(is_wp_error(UWNext_Approval_Bypass::save_from_request(array('_wpnonce'=>$bypass_post['_wpnonce']))),'acceptance: MCP context cannot disable bypass despite valid browser cookie');
UWNext_Auth::$connection='';
check(true===UWNext_Approval_Bypass::save_from_request(array('_wpnonce'=>$bypass_post['_wpnonce']))&&!UWNext_Approval_Bypass::is_on(),'acceptance: actual administrator browser disables bypass');
unset($_COOKIE[LOGGED_IN_COOKIE]);
WP_Session_Tokens::get_instance($admin->ID)->destroy($browser_session);
$after_bypass=data(tool('cli_run',array('command'=>'post list --format=json')));
check(($after_bypass['code']??'')==='approval_required','acceptance: disabling bypass restores exact-operation gate');

// Exercise core's real update offer assembly; mock both core and Unlimited feeds.
$update_http=static function($pre,$args,$url){
    if($url===UWNext_Update_Source::API) {
        return array('headers'=>array(),'response'=>array('code'=>200,'message'=>'OK'),'body'=>wp_json_encode(array('tag_name'=>'v2.0.2','draft'=>false,'prerelease'=>false,'assets'=>array(array('name'=>'unlimited-wp-mcp-next.zip','state'=>'uploaded','size'=>1000,'browser_download_url'=>UWNext_Update_Source::REPOSITORY.'/releases/download/v2.0.2/unlimited-wp-mcp-next.zip')))));
    }
    if(str_contains($url,'api.wordpress.org/plugins/update-check/'))return array('headers'=>array(),'response'=>array('code'=>200,'message'=>'OK'),'body'=>wp_json_encode(array('plugins'=>array(),'no_update'=>array(),'translations'=>array())));
    return $pre;
};
add_filter('pre_http_request',$update_http,1,3);
$was_installing=wp_installing();wp_installing(false);
delete_site_transient(UWNext_Update_Source::CACHE);delete_site_transient('update_plugins');
wp_update_plugins();
$core_offer=get_site_transient('update_plugins')->response[UWNext_PLUGIN_BASENAME]??null;
check(is_object($core_offer)&&$core_offer->new_version==='2.0.2'&&$core_offer->package===UWNext_Update_Source::REPOSITORY.'/releases/download/v2.0.2/unlimited-wp-mcp-next.zip','acceptance: real core update transient contains Unlimited asset');
remove_filter('pre_http_request',$update_http,1);
// No actual updater is invoked; delete the synthetic offer after inspection.
delete_site_transient(UWNext_Update_Source::CACHE);delete_site_transient('update_plugins');
wp_installing($was_installing);
