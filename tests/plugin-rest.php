<?php
// An extension can register a GET route that writes; HTTP method is not proof.
register_rest_route('next-fixture/v1','/side-effect',array('methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_options');},'callback'=>function(){update_option('next_fixture_effect','executed');return array('status'=>'executed');}));
delete_option('next_fixture_effect');
$extension_path='/next-fixture/v1/side-effect';
$extension_read=tool('rest_api_read',array('path'=>$extension_path));
check(!empty($extension_read['result']['isError'])&&!get_option('next_fixture_effect'),'extension GET cannot bypass mutation approval through read orchestration');
$extension_args=array('path'=>$extension_path,'method'=>'GET');
$extension_gate=data(tool('rest_api_write',$extension_args));$extension_id=$extension_gate['data']['approval_id']??'';
check(($extension_gate['code']??'')==='approval_required'&&!get_option('next_fixture_effect'),'extension GET reaches the local approval gate before execution');
UWNext_Admin::approve($extension_id,$admin->ID);$extension_args['approval_id']=$extension_id;
$extension_result=tool('rest_api_write',$extension_args);
check(empty($extension_result['result']['isError'])&&get_option('next_fixture_effect')==='executed','approved extension GET remains available through native REST permissions');
