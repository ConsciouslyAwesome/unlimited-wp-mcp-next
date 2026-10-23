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
add_filter('pre_http_request',function($pre)use(&$network){if(false!==$pre){return $pre;}$network++;return new WP_Error('fixture_no_network','Fixture denies all outbound HTTP.');});
require WP_PLUGIN_DIR.'/unlimited-wp-mcp-next/unlimited-wp-mcp-next.php';
uwnext_activate();
foreach(array('UWNext_REST','UWNext_Elementor','UWNext_Beaver','UWNext_Breakdance','UWNext_Bricks','UWNext_Preview','UWNext_Live_Reload','UWNext_Fields','UWNext_Edit_Affordance','UWNext_Builder_Login') as $class) { $class::instance(); }
$admin=get_user_by('login','next_admin');wp_set_current_user($admin->ID);
uwnext_legacy_template_api();
wpvibe_field_register('post','legacy_fixture',array('type'=>'text','label'=>'Legacy fixture'));
$server=rest_get_server();
$passed=0;$failed=0;
function check($value,$label) { global $passed,$failed; if($value){$passed++;echo "PASS $label\n";}else{$failed++;echo "FAIL $label\n";} }
check(isset(UWNext_Fields::instance()->get_fields('post')['legacy_fixture']),'legacy authored themes retain field registration');
uwnext_legacy_template_api();
check(function_exists('uwmcp_setting_register')&&function_exists('wpvibe_field_group_register'),'legacy template APIs register without redeclaration');
$conn=UWNext_Auth::create_connection($admin->ID,'Local fixture');$token=UWNext_Auth::issue($conn,'manual',false)['access_token'];
function rpc($method,$params=array(),$credential=null,$headers=array()) {
    global $token;
    $request=new WP_REST_Request('POST','/'.UWNext_NS.'/mcp');
    $request->set_body(wp_json_encode(array('jsonrpc'=>'2.0','id'=>1,'method'=>$method,'params'=>(object)$params)));
    foreach(array_merge(array('content-type'=>'application/json','accept'=>'application/json, text/event-stream','authorization'=>'Bearer '.($credential??$token),'mcp-protocol-version'=>'2025-11-25'),$headers) as $k=>$v){$request->set_header($k,$v);}
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
$sql_args=array('command'=>'db query "SELECT 1 AS fixture_value"');
$sql_gate=data(tool('cli_run',$sql_args));
check(($sql_gate['code']??'')==='approval_required','raw SQL now reaches own exact-operation approval');
$sql_id=$sql_gate['data']['approval_id']??'';
check(UWNext_Admin::approve($sql_id,$admin->ID),'fixture browser approves SQL read');
$sql_args['approval_id']=$sql_id;$sql_result=tool('cli_run',$sql_args);
check(empty($sql_result['result']['isError'])&&str_contains(data($sql_result)['stdout']??'','fixture_value'),'approved SQL read executes through retained native handler');
foreach(array('db query "DELETE FROM {prefix}options"','db query "DROP TABLE {prefix}users"','db query "UPDATE {prefix}usermeta SET meta_value=1"','db query "DELETE FROM {prefix}uwnext_events"','option get uwnext_write_lock','option update uwmcp_write_lock fake') as $command) {
    $value=data(tool('cli_run',array('command'=>$command)));
    check(isset($value['code'])&&$value['code']!=='approval_required','protected SQL/CLI state refused before approval');
}
$search=data(tool('cli_run',array('command'=>'search-replace fixture changed {prefix}posts --dry-run')));
check(($search['code']??'')==='approval_required','search-replace now reaches own approval');
global $wpdb;
$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}migration_fixture (id INTEGER PRIMARY KEY, value TEXT)");
$wpdb->query("DELETE FROM {$wpdb->prefix}migration_fixture");
$wpdb->insert($wpdb->prefix.'migration_fixture',array('id'=>1,'value'=>'before'));
$sql_write_args=array('command'=>'db query "UPDATE {prefix}migration_fixture SET value=\'after\' WHERE id=1"');
$sql_write_gate=data(tool('cli_run',$sql_write_args));
check(($sql_write_gate['code']??'')==='approval_required','SQL row mutation requires local approval');
check($wpdb->get_var("SELECT value FROM {$wpdb->prefix}migration_fixture WHERE id=1")==='before','SQL preview does not mutate fixture row');
$sql_write_id=$sql_write_gate['data']['approval_id']??'';
check(UWNext_Admin::approve($sql_write_id,$admin->ID),'fixture browser approves exact SQL mutation');
$sql_write_args['approval_id']=$sql_write_id;$sql_write_result=tool('cli_run',$sql_write_args);
check(empty($sql_write_result['result']['isError'])&&$wpdb->get_var("SELECT value FROM {$wpdb->prefix}migration_fixture WHERE id=1")==='after','approved SQL mutation executes native handler');
$dry_args=array('command'=>'search-replace Changed Replaced {prefix}posts --dry-run');
$dry_gate=data(tool('cli_run',$dry_args));$dry_id=$dry_gate['data']['approval_id']??'';
check(UWNext_Admin::approve($dry_id,$admin->ID),'fixture browser approves search-replace dry run');
$dry_args['approval_id']=$dry_id;$dry_result=tool('cli_run',$dry_args);
check(empty($dry_result['result']['isError'])&&get_post($fixture_post_id)->post_title==='Changed fixture','native search-replace dry run leaves content unchanged');
$job_args=array('command'=>'db query "UPDATE {prefix}migration_fixture SET value=\'background\' WHERE id=1"','detach'=>true);
$job_gate=data(tool('cli_run',$job_args));$job_id=$job_gate['data']['approval_id']??'';
check(($job_gate['code']??'')==='approval_required'&&UWNext_Admin::approve($job_id,$admin->ID),'detached command requires exact fixture approval');
$job_args['approval_id']=$job_id;$queued=data(tool('cli_run',$job_args));
check(($queued['status']??'')==='queued'&&$wpdb->get_var("SELECT value FROM {$wpdb->prefix}migration_fixture WHERE id=1")==='after','queue returns before executing mutation');
check(is_array(UWNext_Store::get('job',$job_id)),'queue authority stored independently of consumed approval');
do_action('uwnext_job',$job_id);
check($wpdb->get_var("SELECT value FROM {$wpdb->prefix}migration_fixture WHERE id=1")==='background','own cron worker executes approved native command');
$job_events=data(tool('operation_receipt',array('operation_id'=>$job_id)));
check(in_array('queued',array_column($job_events,'status'),true)&&in_array('completed',array_column($job_events,'status'),true),'background operation has queued and completed receipts');
do_action('uwnext_job',$job_id);
$job_again=data(tool('operation_receipt',array('operation_id'=>$job_id)));
check(count($job_again)===count($job_events)&&!UWNext_Store::get('job',$job_id),'background job is single-use with no automatic retry');
$job_connection=UWNext_Auth::create_connection($admin->ID,'Revoked job fixture');$job_token=UWNext_Auth::issue($job_connection,'manual',false)['access_token'];
$revoked_args=array('command'=>'db query "UPDATE {prefix}migration_fixture SET value=\'revoked\' WHERE id=1"','detach'=>true);
$revoked_gate=data(tool('cli_run',$revoked_args,$job_token));$revoked_id=$revoked_gate['data']['approval_id']??'';
UWNext_Admin::approve($revoked_id,$admin->ID);$revoked_args['approval_id']=$revoked_id;tool('cli_run',$revoked_args,$job_token);
UWNext_Auth::revoke($job_connection);do_action('uwnext_job',$revoked_id);
check($wpdb->get_var("SELECT value FROM {$wpdb->prefix}migration_fixture WHERE id=1")==='background','revocation before background execution prevents mutation');
rpc('tools/list');
require __DIR__.'/security.php';
rpc('tools/list');
require __DIR__.'/theme.php';
require __DIR__.'/file-search.php';
require __DIR__.'/file-list.php';
require __DIR__.'/file-inspection.php';
require __DIR__.'/file-compile-sources.php';
foreach(UWNext_Resources::SKILLS as $skill) {
    $guidance=data(tool('load_skill',array('name'=>$skill)));
    check(!empty($guidance['guidance'])&&str_contains($guidance['authorization']??'','browser approval'),'independent local workflow: '.$skill);
}
$unknown_skill=tool('load_skill',array('name'=>'../wp-config.php'));
check(isset($unknown_skill['error'])||!empty($unknown_skill['result']['isError']),'workflow loader refuses arbitrary paths');
$provider_calls=0;$provider_status=200;
$provider_mock=function($pre,$options,$url)use(&$provider_calls,&$provider_status){
    if(!str_starts_with($url,'https://api.openverse.org/v1/images/?')){return $pre;}
    $provider_calls++;
    check(($options['redirection']??null)===0,'independent image provider cannot redirect requests');
    return array('response'=>array('code'=>$provider_status),'headers'=>array('retry-after'=>'60'),'body'=>wp_json_encode(array('page_count'=>1,'results'=>array(array('id'=>'fixture-image','title'=>'<b>Fixture</b>','url'=>'https://images.example.com/image.jpg','license'=>'cc0','creator'=>'Fixture Artist','license_url'=>'https://creativecommons.org/publicdomain/zero/1.0/','attribution'=>'Fixture Artist, CC0')))));
};
add_filter('pre_http_request',$provider_mock,1,3);
$images=data(tool('search_images',array('query'=>'fixture query','limit'=>1)));
check(($images['provider']??'')==='Openverse'&&($images['results'][0]['license']??'')==='cc0'&&($images['results'][0]['creator']??'')==='Fixture Artist','independent image search retains license and attribution metadata');
$before_provider=$provider_calls;$provider_status=429;
$rate_limited=data(tool('search_images',array('query'=>'fixture rate-limit query')));
check(($rate_limited['code']??'')==='image_provider_rate_limited'&&$provider_calls===$before_provider+1,'provider rate limit is propagated without retry or spoofing');
remove_filter('pre_http_request',$provider_mock,1);
foreach(array(6=>'dns',7=>'connection',28=>'timeout',35=>'tls',60=>'tls') as $curl=>$category) {
    $transport_calls=0;
    $transport_mock=function($pre,$options,$url)use($curl,&$transport_calls){
        if(!str_starts_with($url,'https://api.openverse.org/v1/images/?')) {return $pre;}
        $transport_calls++;
        return new WP_Error('http_request_failed','cURL error '.$curl.': private-host-secret');
    };
    add_filter('pre_http_request',$transport_mock,1,3);
    $transport=data(tool('search_images',array('query'=>'fixture transport')));
    check(($transport['data']['failure_kind']??'')===$category&&$transport_calls===1&&!str_contains(wp_json_encode($transport),'private-host-secret'),'image transport category '.$category.' is safe and does not retry (curl '.$curl.')');
    remove_filter('pre_http_request',$transport_mock,1);
}
require __DIR__.'/plugin-rest.php';
foreach(array('http_request_not_executed'=>'blocked','unrecognized_transport'=>'transport') as $code=>$category) {
    $transport_calls=0;
    $transport_mock=function($pre,$options,$url)use($code,&$transport_calls){
        if(!str_starts_with($url,'https://api.openverse.org/v1/images/?')) {return $pre;}
        $transport_calls++;
        return new WP_Error($code,'private-host-secret');
    };
    add_filter('pre_http_request',$transport_mock,1,3);
    $transport=data(tool('search_images',array('query'=>'fixture transport')));
    check(($transport['data']['failure_kind']??'')===$category&&$transport_calls===1&&!str_contains(wp_json_encode($transport),'private-host-secret'),'image transport category '.$category.' is safe and does not retry');
    remove_filter('pre_http_request',$transport_mock,1);
}
echo "RESULT $passed passed, $failed failed\n";
exit($failed?1:0);
