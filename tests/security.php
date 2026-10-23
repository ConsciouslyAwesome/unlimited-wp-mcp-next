<?php
// Included by integration.php with the active WordPress fixture and assertion helpers.
$destroy=wp_insert_post(array('post_title'=>'Disposable deletion target','post_status'=>'draft','post_author'=>$admin->ID));
$command='post delete '.$destroy.' --force';
$preview=data(tool('cli_run',array('command'=>$command,'confirm_write'=>true)));
check(($preview['code']??'')==='approval_required' && get_post($destroy)!==null,'destructive CLI preview does not execute');
$aid=$preview['data']['approval_id'];UWNext_Admin::approve($aid,$admin->ID);
$executed=tool('cli_run',array('command'=>$command,'confirm_write'=>true,'approval_id'=>$aid));
check(empty($executed['result']['isError']) && get_post($destroy)===null,'destructive CLI approval executes once');
// Credential material stays out of option values: lookup keys are SHA-256 digests.
global $wpdb;
$stored=$wpdb->get_col("SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE 'uwnext_%'");
check(!str_contains(implode('',$stored),$token),'no plaintext bearer token in database');
$reg=new WP_REST_Request('POST','/');$reg->set_header('content-type','application/json');$reg->set_body(wp_json_encode(array('client_name'=>'Fixture OAuth','redirect_uris'=>array('https://client.example/callback'),'token_endpoint_auth_method'=>'none')));
$registered=UWNext_Auth::register_client($reg);$client=$registered->get_data()['client_id']??'';
check($registered->get_status()===201 && !!UWNext_Auth::client($client),'OAuth public client registration');
check(!UWNext_Auth::client($client.'x'),'tampered client registration rejected');
check(!UWNext_Auth::valid_redirect('javascript:alert(1)') && !UWNext_Auth::valid_redirect('https://client.example/callback#fragment') && !UWNext_Auth::valid_redirect('http://example.com/callback'),'unsafe OAuth redirects rejected');
$verifier=bin2hex(random_bytes(32));$challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');
$params=array('client_id'=>$client,'redirect_uri'=>'https://client.example/callback','response_type'=>'code','code_challenge_method'=>'S256','code_challenge'=>$challenge,'resource'=>UWNext_Auth::endpoint(),'scope'=>'wordpress offline_access','state'=>'fixture');
check(!!UWNext_Auth::validate_authorization($params),'OAuth authorization validation');
$bad=$params;$bad['redirect_uri']='https://attacker.example/callback';check(!UWNext_Auth::validate_authorization($bad),'OAuth redirect exact match');
$bad=$params;$bad['code_challenge_method']='plain';check(!UWNext_Auth::validate_authorization($bad),'OAuth S256 required');
$bad=$params;$bad['resource']='https://other.example/mcp';check(!UWNext_Auth::validate_authorization($bad),'OAuth resource binding');
$oauth_conn=UWNext_Auth::create_connection($admin->ID,'Fixture OAuth');$code=bin2hex(random_bytes(48));
$code_row=array('connection'=>$oauth_conn,'client'=>$client,'redirect'=>$params['redirect_uri'],'challenge'=>$challenge,'resource'=>UWNext_Auth::endpoint(),'expires'=>time()+120,'refresh'=>true);
UWNext_Store::put('code',$code,$code_row);
function exchange($p) { $r=new WP_REST_Request('POST','/');$r->set_body_params($p);return UWNext_Auth::token($r); }
$exchange=array('grant_type'=>'authorization_code','client_id'=>$client,'code'=>$code,'redirect_uri'=>$params['redirect_uri'],'resource'=>UWNext_Auth::endpoint(),'code_verifier'=>$verifier);
$bad=$exchange;$bad['code_verifier']=str_repeat('z',64);check(exchange($bad)->get_status()===400,'wrong PKCE verifier rejected');
$good=exchange($exchange);$oauth=$good->get_data();check($good->get_status()===200 && isset($oauth['refresh_token']),'OAuth authorization-code exchange');
check(exchange($exchange)->get_status()===400,'authorization code is single use');
check(rpc('tools/list',array(),$oauth['access_token'])->get_status()===200,'OAuth access token works');
$refresh=array('grant_type'=>'refresh_token','client_id'=>$client,'resource'=>UWNext_Auth::endpoint(),'refresh_token'=>$oauth['refresh_token']);
$rotated=exchange($refresh);check($rotated->get_status()===200 && $rotated->get_data()['refresh_token']!==$oauth['refresh_token'],'refresh token rotates');
check(exchange($refresh)->get_status()===400,'refresh replay rejected');
check(rpc('tools/list',array(),$rotated->get_data()['access_token'])->get_status()===401,'refresh replay revokes connection family');
$expired_token=bin2hex(random_bytes(48));UWNext_Store::put('access',$expired_token,array('connection'=>$conn,'user'=>$admin->ID,'resource'=>UWNext_Auth::endpoint(),'expires'=>time()-1));check(rpc('tools/list',array(),$expired_token)->get_status()===401,'expired access token rejected');
// Actual core Application Password authentication, including the core hook.
define('REST_REQUEST',true);
$created=WP_Application_Passwords::create_new_application_password($admin->ID,array('name'=>'UWNext test'));
if(!is_wp_error($created)) {
    list($password,$item)=$created;
    $user=wp_authenticate_application_password(null,$admin->user_login,$password);wp_set_current_user($admin->ID);
    $header='Basic '.base64_encode($admin->user_login.':'.$password);
    check($user instanceof WP_User && rpc('tools/list',array(),null,array('authorization'=>$header))->get_status()===200,'WordPress Application Password compatibility');
    WP_Application_Passwords::delete_application_password($admin->ID,$item['uuid']);
    check(rpc('tools/list',array(),null,array('authorization'=>$header))->get_status()===401,'Application Password revocation');
} else { check(false,'WordPress Application Password fixture creation'); }
UWNext_Auth::$app=null;
$_SERVER['HTTPS']='off';check(rpc('tools/list')->get_status()===403,'unencrypted transport refused');$_SERVER['HTTPS']='on';
// Host exceptions become bounded errors, without reflecting exception text.
$saved=UWNext_Tools::$routes['/site-info']['GET']['callback'];
UWNext_Tools::$routes['/site-info']['GET']['callback']=function(){throw new RuntimeException('simulated-host-secret');};
$failure=tool('site_info');check((data($failure)['code']??'')==='host_failure' && !str_contains(wp_json_encode($failure),'simulated-host-secret'),'host failure is safe and explicit');
UWNext_Tools::$routes['/site-info']['GET']['callback']=$saved;
// Structured protocol failures, real REST dispatch authentication, and output shape.
$r=new WP_REST_Request('POST','/'.UWNext_NS.'/mcp');$r->set_header('authorization','Bearer '.$token);$r->set_header('content-type','application/json');$r->set_header('accept','application/json, text/event-stream');$r->set_body('{');
check(UWNext_MCP::handle($r)->get_data()['error']['code']===-32700,'malformed JSON rejected');
$r->set_body('[]');check(UWNext_MCP::handle($r)->get_data()['error']['code']===-32600,'JSON-RPC batch rejected');
$r->set_body(str_repeat(' ',1048577));check(UWNext_MCP::handle($r)->get_status()===413,'oversized request safety limit');
$r->set_method('GET');check(UWNext_MCP::handle($r)->get_status()===405,'GET transport rejected');
$anonymous=new WP_REST_Request('POST','/'.UWNext_NS.'/mcp');$res=rest_do_request($anonymous);check($res->get_status()===401,'unauthenticated real REST dispatch denied');
$opaque=UWNext_Store::encrypt(array('password'=>'not-logged','operation'=>'fixture'));check(UWNext_Store::decrypt($opaque)['operation']==='fixture' && !str_contains($opaque,'not-logged'),'approval arguments encrypted');
$opaque[20]=$opaque[20]==='a'?'b':'a';check(UWNext_Store::decrypt($opaque)===false,'approval encryption rejects tampering');
