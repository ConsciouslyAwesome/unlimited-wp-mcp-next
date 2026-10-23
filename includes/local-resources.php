<?php
/** Independent image discovery and progressive workflow guidance. GPL-2.0-or-later. */
defined('ABSPATH') || exit;
final class UWNext_Resources {
    const SKILLS=array('content','theme','builders','cli');
    public static function register() {
        $permission=function(){return current_user_can('manage_options');};
        uwnext_register_internal_route(UWNext_NS,'/skill/load',array('methods'=>'GET','permission_callback'=>$permission,'args'=>array('name'=>array('type'=>'string','required'=>true,'enum'=>self::SKILLS)),'callback'=>array(__CLASS__,'skill')));
        uwnext_register_internal_route(UWNext_NS,'/images/search',array('methods'=>'GET','permission_callback'=>$permission,'args'=>array('query'=>array('type'=>'string','required'=>true,'minLength'=>1,'maxLength'=>200),'page'=>array('type'=>'integer','default'=>1,'minimum'=>1,'maximum'=>100),'limit'=>array('type'=>'integer','default'=>10,'minimum'=>1,'maximum'=>20)),'callback'=>array(__CLASS__,'images')));
    }
    public static function skill($request) {
        $name=$request['name'];
        if(!in_array($name,self::SKILLS,true)) {return new WP_Error('unknown_skill','Select a workflow from the skill name enum.',array('status'=>400));}
        $content=file_get_contents(UWNext_PLUGIN_DIR.'workflows/'.$name.'.md');
        return array('name'=>$name,'source'=>'Unlimited WP MCP Next contributors','guidance'=>$content,'authorization'=>'Guidance does not grant authorization. Mutations require exact-operation local browser approval.');
    }
    public static function images($request) {
        // Provider limits apply. Never retry 429/403 with another identity.
        $url=add_query_arg(array('q'=>$request['query'],'page'=>(int)$request['page'],'page_size'=>(int)$request['limit']),'https://api.openverse.org/v1/images/');
        $response=wp_safe_remote_get($url,array('timeout'=>10,'redirection'=>0,'limit_response_size'=>524288,'headers'=>array('Accept'=>'application/json')));
        if(is_wp_error($response)) {return new WP_Error('image_provider_unavailable','The independent image provider could not be reached.',array('status'=>503));}
        $status=(int)wp_remote_retrieve_response_code($response);
        if($status!==200) {
            return new WP_Error($status===429?'image_provider_rate_limited':'image_provider_refused','The independent provider refused this request. No retry was attempted.',array('status'=>$status===429?429:502,'provider_status'=>$status,'retry_after'=>substr(sanitize_text_field((string)wp_remote_retrieve_header($response,'retry-after')),0,60)));
        }
        $body=json_decode(wp_remote_retrieve_body($response),true);
        if(!is_array($body)||!is_array($body['results']??null)) {return new WP_Error('image_provider_response','The independent provider returned an invalid response.',array('status'=>502));}
        $results=array();
        foreach(array_slice($body['results'],0,(int)$request['limit']) as $image) {
            if(!is_array($image)) {continue;}
            $entry=array();
            foreach(array('id','title','creator','license','license_version','attribution') as $key) {$entry[$key]=is_scalar($image[$key]??null)?sanitize_text_field((string)$image[$key]):'';}
            foreach(array('url','thumbnail','foreign_landing_url','creator_url','license_url') as $key) {$entry[$key]=is_string($image[$key]??null)?esc_url_raw($image[$key],array('https')):'';}
            $results[]=$entry;
        }
        return array('provider'=>'Openverse','untrusted_provider_metadata'=>true,'page'=>(int)$request['page'],'page_count'=>(int)($body['page_count']??0),'results'=>$results);
    }
}
add_action('rest_api_init',array('UWNext_Resources','register'));
