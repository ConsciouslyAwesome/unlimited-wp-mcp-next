<?php
// This fixture loads local core, a distinct content directory and a separate DB.
$core=rtrim(str_replace('\\','/',$argv[1]),'/').'/';
$fixture=rtrim(str_replace('\\','/',$argv[2]),'/');
$_SERVER['HTTPS']='on'; $_SERVER['HTTP_HOST']='fixture.test'; $_SERVER['SERVER_NAME']='fixture.test';
$_SERVER['SERVER_PORT']='443'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
define('ABSPATH',$core); define('WP_CONTENT_DIR',$fixture.'/wp-content'); define('WP_CONTENT_URL','https://fixture.test/wp-content');
define('DB_DIR',$fixture.'/database/'); define('DB_NAME','next_fixture'); define('DB_USER','fixture'); define('DB_PASSWORD',''); define('DB_HOST','localhost');
foreach(array('AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT') as $key) { define($key,hash('sha256','next-generation-local-fixture-'.$key)); }
$table_prefix='next_';
define('WP_HOME','https://fixture.test'); define('WP_SITEURL','https://fixture.test'); define('WP_ENVIRONMENT_TYPE','local');
define('WP_INSTALLING',true);
define('WP_DEBUG',true); define('WP_DEBUG_DISPLAY',false); define('WP_DEBUG_LOG',$fixture.'/debug.log');
define('DISABLE_WP_CRON',true); define('WP_HTTP_BLOCK_EXTERNAL',true); define('FS_METHOD','direct');
require ABSPATH.'wp-settings.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php'; require_once ABSPATH.'wp-admin/includes/plugin.php';
if(!is_blog_installed()) { wp_install('Next Fixture','next_admin','fixture@example.invalid',false,'',bin2hex(random_bytes(24))); }
$network=0;
add_filter('pre_http_request',function()use(&$network){$network++;return new WP_Error('fixture_no_network','Fixture denies all outbound HTTP.');});
require WP_PLUGIN_DIR.'/unlimited-wp-mcp-next/unlimited-wp-mcp-next.php';
uwnext_activate();
foreach(array('UWNext_REST','UWNext_Elementor','UWNext_Beaver','UWNext_Breakdance','UWNext_Bricks','UWNext_Preview','UWNext_Live_Reload','UWNext_Fields','UWNext_Edit_Affordance','UWNext_Builder_Login') as $class) { $class::instance(); }
$admin=get_user_by('login','next_admin');wp_set_current_user($admin->ID);
$server=rest_get_server();
$passed=0;$failed=0;
function check($value,$label) { global $passed,$failed; if($value){$passed++;echo "PASS $label\n";}else{$failed++;echo "FAIL $label\n";} }
$conn=UWNext_Auth::create_connection($admin->ID,'Local fixture');$token=UWNext_Auth::issue($conn,'manual',false)['access_token'];
function rpc($method,$params=array(),$credential=null) {
    global $token;
    $request=new WP_REST_Request('POST','/'.UWNext_NS.'/mcp');
    $request->set_body(wp_json_encode(array('jsonrpc'=>'2.0','id'=>1,'method'=>$method,'params'=>(object)$params)));
    foreach(array('content-type'=>'application/json','accept'=>'application/json, text/event-stream','authorization'=>'Bearer '.($credential??$token),'mcp-protocol-version'=>'2025-11-25') as $k=>$v){$request->set_header($k,$v);}
    return rest_do_request($request);
}
function tool($name,$args=array(),$credential=null) { return rpc('tools/call',array('name'=>$name,'arguments'=>(object)$args),$credential)->get_data(); }
function data($response) { return json_decode($response['result']['content'][0]['text']??'null',true); }
check(isset($server->get_routes()['/'.UWNext_NS.'/mcp']),'own MCP endpoint registered');
foreach(array('/file/write','/cli/run-approved','/authorize','/builder-login') as $route) {
    check(!isset($server->get_routes()['/'.UWNext_NS.$route])&&!isset($server->get_routes()['/uwnext/v1'.$route]),'handler not publicly routable: '.$route);
}
$list=rpc('tools/list')->get_data();
check(count($list['result']['tools']??array())>35,'catalog populated');
file_put_contents($fixture.'/catalog.json',wp_json_encode($list));
$site=tool('site_info');check(empty($site['result']['isError'])&&isset(data($site)['site_name']),'authenticated site read');
check(rpc('tools/list',array(),str_repeat('0',96))->get_status()===401,'invalid token refused');
$fixture_post_id=wp_insert_post(array('post_title'=>'Next fixture','post_content'=>'Original fixture body','post_status'=>'draft','post_author'=>$admin->ID));
$args=array('target_type'=>'post','post_id'=>$fixture_post_id,'field'=>'post_title','old_content'=>'Next fixture','new_content'=>'Changed fixture');
$first=data(tool('content_edit',$args));check(($first['code']??'')==='approval_required','mutation stops at local approval');
check(get_post($fixture_post_id)->post_title==='Next fixture','unapproved call makes no content change');
check(!UWNext_Tools::executing(),'private execution context closed');
$receipt=tool('operation_receipt',array('operation_id'=>$first['data']['approval_id']??str_repeat('0',48)));
check(empty($receipt['result']['isError']),'connection-scoped receipt available');
check(is_wp_error(UWNext_Op_Proof::verify(new WP_REST_Request(),'unused','unused')),'hosted proof cannot authorize locally');
$cli=data(tool('cli_run',array('command'=>'option get blogname')));check(($cli['code']??'')==='approval_required','CLI uses local approval');
$snippet=data(tool('code_snippet',array('action'=>'list')));check(($snippet['code']??'')!=='uwnext_op_proof_missing','snippet capability no longer depends on hosted proof');
check($network===0,'smoke operations perform zero outbound HTTP requests');

// Exercise a real local mutation through the simulated browser transition.
// These are fixture-only approvals, never production or connector approval IDs.
$aid=$first['data']['approval_id'];
check(UWNext_Admin::approve($aid,$admin->ID),'fixture browser approves exact content change');
$wrong=$args;$wrong['new_content']='Different change';$wrong['approval_id']=$aid;
check((data(tool('content_edit',$wrong))['code']??'')==='approval_invalid','changed arguments refused');
$args['approval_id']=$aid;
$write=tool('content_edit',$args);
check(empty($write['result']['isError'])&&get_post($fixture_post_id)->post_title==='Changed fixture','approved content change executes');
check((data(tool('content_edit',$args))['code']??'')==='approval_invalid','spent local approval refused');
$receipt=data(tool('operation_receipt',array('operation_id'=>$aid)));
check(in_array('completed',array_column($receipt,'status'),true),'completed operation receipt recorded');
$other=UWNext_Auth::create_connection($admin->ID,'Other local connection');
$other_token=UWNext_Auth::issue($other,'manual',false)['access_token'];
check(data(tool('operation_receipt',array('operation_id'=>$aid),$other_token))===array(),'same-user different-connection receipt isolation');
UWNext_Auth::revoke($other);
check(rpc('tools/list',array(),$other_token)->get_status()===401,'revoked local connection refused');
// Restore the main request's authentication context before direct helper calls.
rpc('tools/list');
$rest=tool('rest_api_read',array('path'=>'/wp/v2/posts/'.$fixture_post_id,'parameters'=>array('context'=>'edit')));
check(empty($rest['result']['isError'])&&(data($rest)['id']??0)===$fixture_post_id,'native WordPress REST read orchestration');
$restwrite=data(tool('rest_api_write',array('path'=>'/wp/v2/posts/'.$fixture_post_id,'parameters'=>array('title'=>'Not approved'),'method'=>'POST')));
check(($restwrite['code']??'')==='approval_required'&&get_post($fixture_post_id)->post_title==='Changed fixture','REST mutation gated before native dispatch');
foreach(array('/unlimited-wp-mcp-next/v1/mcp','/wp/v2/posts?x=y','/wp/v2/../settings','https://example.invalid/wp-json/wp/v2/posts','/wp/v2/%70osts') as $path) {
    $value=tool('rest_api_read',array('path'=>$path));
    check(isset($value['error'])||!empty($value['result']['isError']),'REST path rejects control-plane recursion or malformed path');
}
foreach(array('uwnext_access_fake','uwnext_write_lock','uwmcp_access_fake','wpvibe_op_proof_key') as $name) {
    $value=data(tool('content_search',array('target_type'=>'option','option_name'=>$name,'pattern'=>'fixture')));
    check(($value['code']??'')==='blocked_option','security options unavailable through content handler: '.$name);
}
check(!in_array('op_proof_v2',data(tool('site_info'))['features'],true),'site info does not advertise hosted proof support');
check(!UWNext_Tools::executing(),'execution context resets after fixture writes');
check($network===0,'expanded integration performs zero outbound HTTP requests');
echo "RESULT $passed passed, $failed failed\n";
exit($failed?1:0);
