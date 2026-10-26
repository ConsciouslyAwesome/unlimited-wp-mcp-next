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
        $url=add_query_arg(array('q'=>$request['query'],'page'=>(int)$request['page'],'page_size'=>(int)$request['limit']),'https://api.openverse.org/v1/images/');
        $request_result=self::image_request($url);
        if(is_wp_error($request_result)) {return $request_result;}
        $response=$request_result['response'];
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
        return array('provider'=>'Openverse','untrusted_provider_metadata'=>true,'page'=>(int)$request['page'],'page_count'=>(int)($body['page_count']??0),'results'=>$results,'request_diagnostics'=>$request_result['diagnostics']);
    }
    private static function diagnostic_message($message) {
        foreach(array(WP_CONTENT_DIR,ABSPATH,sys_get_temp_dir()) as $root) {
            $root=rtrim($root,'/\\');
            $message=str_ireplace(array($root,str_replace('\\','/',$root),str_replace('/','\\',$root)),'[server path]',$message);
        }
        $message=UWNext_Content_Files::redact($message);
        return substr(sanitize_text_field($message??'Error details unavailable.'),0,1000);
    }
    private static function transport_failure($error) {
        $message=$error->get_error_message();
        $kind='transport';
        if($error->get_error_code()==='http_request_not_executed') {return array('blocked',false);}
        if(preg_match('/\bcURL error (\d+)\b/i',$message,$match)) {
            $code=(int)$match[1];
            $kind=array(6=>'dns',7=>'connection',28=>'timeout',35=>'tls',60=>'tls')[$code]??'transport';
            // TLS, DNS, generic connect failures and unknown cURL errors are not assumed transient.
            return array($kind,$code===28||($code===56&&preg_match('/connection (?:was )?reset|recv failure.*reset/i',$message)));
        }
        return array($kind,$error->get_error_code()==='http_request_failed'&&preg_match('/\b(?:operation|connection|request) timed out\b|\bconnection (?:was )?reset(?: by peer)?\b/i',$message));
    }
    private static function image_request($url,$caller_headers=array()) {
        // Case-insensitive merging cannot erase the adapter's required JSON Accept header.
        $headers=array();
        foreach($caller_headers as $name=>$value) {
            if(is_string($name)&&strtolower($name)!=='accept') {$headers[$name]=$value;}
        }
        $headers['Accept']='application/json';
        $diagnostics=array();$started=microtime(true);
        for($attempt=1;$attempt<=3;$attempt++) {
            $attempt_started=microtime(true);
            $response=wp_safe_remote_get($url,array('timeout'=>10,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>524288,'headers'=>$headers));
            $entry=array('attempt'=>$attempt,'elapsed_ms'=>(int)round((microtime(true)-$attempt_started)*1000));
            if(is_wp_error($response)) {
                list($kind,$retry)=self::transport_failure($response);
                $entry+=array('wp_error_code'=>$response->get_error_code(),'wp_error_message'=>self::diagnostic_message($response->get_error_message()),'failure_kind'=>$kind);
            } else {
                $status=(int)wp_remote_retrieve_response_code($response);
                $entry['http_status']=$status;
                $retry=in_array($status,array(429,500,502,503,504),true);
            }
            $entry['retry_delay_ms']=$retry&&$attempt<3?250*$attempt:0;
            $diagnostics[]=$entry;
            if(!$retry||$attempt===3) {break;}
            // Fixed identity, no redirects or TLS relaxation; total backoff is bounded to 750 ms.
            usleep(250000*$attempt);
        }
        $details=array('attempts'=>count($diagnostics),'elapsed_ms'=>(int)round((microtime(true)-$started)*1000),'attempt_log'=>$diagnostics);
        if(is_wp_error($response)) {
            return new WP_Error('image_provider_unavailable','The independent image provider could not be reached.',array_merge(array('status'=>503,'failure_kind'=>$kind,'wp_error_code'=>$entry['wp_error_code'],'wp_error_message'=>$entry['wp_error_message']),$details));
        }
        if($status!==200) {
            return new WP_Error($status===429?'image_provider_rate_limited':'image_provider_refused','The independent provider refused this request.',array_merge(array('status'=>$status===429?429:502,'provider_status'=>$status,'retry_after'=>substr(sanitize_text_field((string)wp_remote_retrieve_header($response,'retry-after')),0,60)),$details));
        }
        return array('response'=>$response,'diagnostics'=>$details);
    }
}
add_action('rest_api_init',array('UWNext_Resources','register'));
