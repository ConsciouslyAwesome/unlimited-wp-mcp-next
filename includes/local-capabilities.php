<?php
/** Independent replacements for hosted REST and Abilities orchestration. */
defined('ABSPATH') || exit;

final class UWNext_Capabilities {
    public static function register() {
        $permission=function(){return current_user_can('manage_options');};
        $rest_args=array(
            'path'=>array('type'=>'string','required'=>true,'pattern'=>'^/[A-Za-z0-9_-]+/.*$'),
            'parameters'=>array('type'=>'object','default'=>array()),
        );
        uwnext_register_internal_route(UWNext_NS,'/rest-api/read',array('methods'=>'GET','permission_callback'=>$permission,'args'=>$rest_args,'callback'=>function($request){return self::rest($request,'GET');}));
        $rest_args['method']=array('type'=>'string','required'=>true,'enum'=>array('GET','POST','PUT','PATCH','DELETE'));
        uwnext_register_internal_route(UWNext_NS,'/rest-api/write',array('methods'=>'POST','permission_callback'=>$permission,'args'=>$rest_args,'callback'=>function($request){return self::rest($request,$request['method'],true);}));
        if(function_exists('wp_get_ability')) {
            uwnext_register_internal_route(UWNext_NS,'/ability/info',array('methods'=>'GET','permission_callback'=>$permission,'args'=>array('name'=>array('type'=>'string','required'=>true)),'callback'=>function($request){
                $ability=wp_get_ability($request['name']);
                if(!$ability){return new WP_Error('ability_missing','Ability not registered.',array('status'=>404));}
                return array('untrusted_plugin_metadata'=>true,'name'=>$ability->get_name(),'description'=>$ability->get_description(),'input_schema'=>$ability->get_input_schema(),'output_schema'=>$ability->get_output_schema());
            }));
        }
    }
    public static function rest($request,$method,$allow_extension=false) {
        $path=$request['path'];
        // Only local canonical REST paths; never URLs, query strings or our
        // own control-plane endpoints. Native WordPress permissions still run.
        if(!is_string($path)||preg_match('/[%?#\\\\\x00-\x20]/',$path)||str_contains($path,'..')||!preg_match('#^/[A-Za-z0-9_-]+/v[0-9]+(?:/|$)#D',$path)||preg_match('#^/(?:unlimited-wp-mcp(?:-next)?|uwnext|wpvibe)/#i',$path)) {
            return new WP_Error('rest_path_refused','Use a canonical local plugin REST path outside the MCP control plane.',array('status'=>400));
        }
        if(!$allow_extension&&!str_starts_with($path,'/wp/v2/')) {
            return new WP_Error('extension_approval_required','Extension GET endpoints can change state. Use rest_api_write with method GET and review the local approval.',array('status'=>403));
        }
        $inner=new WP_REST_Request($method,$path);
        $params=(array)$request['parameters'];
        if($method==='GET'){$inner->set_query_params($params);}else{$inner->set_body_params($params);}
        return rest_do_request($inner);
    }
}
add_action('rest_api_init',array('UWNext_Capabilities','register'));
