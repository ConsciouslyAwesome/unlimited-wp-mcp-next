<?php
// Exercise the actual adapter with bounded mocked HTTP; no external provider calls.
$success=array('response'=>array('code'=>200),'headers'=>array(),'body'=>wp_json_encode(array('page_count'=>1,'results'=>array(array('id'=>'fixture-image','license'=>'cc0','creator'=>'Fixture Artist')))));
$run_images=function($responses) {
    $calls=array();
    $mock=function($pre,$options,$url)use(&$calls,$responses){
        if(!str_starts_with($url,'https://api.openverse.org/v1/images/?')) {return $pre;}
        $calls[]=array('args'=>$options,'url'=>$url,'time'=>microtime(true));
        return $responses[min(count($calls)-1,count($responses)-1)];
    };
    add_filter('pre_http_request',$mock,1,3);
    try {$value=data(tool('search_images',array('query'=>'fixture query','page'=>1,'limit'=>1)));}
    finally {remove_filter('pre_http_request',$mock,1);}
    return array($value,$calls);
};
$timeout=new WP_Error('http_request_failed','cURL error 28: Operation timed out after 10000 milliseconds with 0 bytes received');
list($value,$calls)=$run_images(array($success));
check(count($calls)===1&&($value['results'][0]['license']??'')==='cc0'&&($value['results'][0]['creator']??'')==='Fixture Artist','image success on first attempt retains attribution');
check(($calls[0]['args']['limit_response_size']??0)===524288&&($calls[0]['args']['timeout']??0)===10&&($calls[0]['args']['redirection']??-1)===0&&($calls[0]['args']['sslverify']??false)===true&&($calls[0]['args']['reject_unsafe_urls']??false)===true,'safe image GET retains size/timeout/redirect/SSL safeguards');
check(($calls[0]['args']['headers']['Accept']??'')==='application/json','image request retains default JSON Accept');
list($value,$calls)=$run_images(array($timeout,$success));
check(count($calls)===2&&($value['provider']??'')==='Openverse'&&($value['request_diagnostics']['attempt_log'][0]['wp_error_message']??'')===$timeout->get_error_message(),'timeout then success retains original diagnostics');
check($calls[1]['time']-$calls[0]['time']>=0.24&&($value['request_diagnostics']['attempt_log'][0]['retry_delay_ms']??0)===250,'image retry uses bounded backoff');
check($calls[0]['args']===$calls[1]['args']&&$calls[0]['url']===$calls[1]['url'],'retry retains all request arguments and identity');
list($value,$calls)=$run_images(array($timeout));
check(count($calls)===3&&($value['data']['attempts']??0)===3&&($value['data']['wp_error_code']??'')==='http_request_failed'&&($value['data']['wp_error_message']??'')===$timeout->get_error_message(),'three timeouts stop at three attempts and preserve original error');
check(($value['data']['attempt_log'][1]['retry_delay_ms']??0)===500&&($value['data']['attempt_log'][2]['retry_delay_ms']??-1)===0&&$calls[2]['time']-$calls[1]['time']>=0.49,'backoff increases and no wait occurs after final failure');
foreach(array('cURL error 60: SSL certificate problem','cURL error 35: SSL connect error','cURL error 6: Could not resolve host','cURL error 7: Failed to connect','Unspecified transport failure') as $message) {
    list($value,$calls)=$run_images(array(new WP_Error('http_request_failed',$message),$success));
    check(count($calls)===1&&($value['code']??'')==='image_provider_unavailable','nonretryable transport error is not retried: '.$message);
}
foreach(array('cURL error 56: Recv failure: Connection reset by peer','Connection reset by peer','Operation timed out') as $message) {
    list($value,$calls)=$run_images(array(new WP_Error('http_request_failed',$message),$success));
    check(count($calls)===2&&($value['provider']??'')==='Openverse','recognized transient transport retries: '.$message);
}
foreach(array(429,500,502,503,504) as $status) {
    $http=array('response'=>array('code'=>$status),'headers'=>array(),'body'=>'');
    list($value,$calls)=$run_images(array($http,$success));
    check(count($calls)===2&&($value['provider']??'')==='Openverse'&&($value['request_diagnostics']['attempt_log'][0]['http_status']??0)===$status,'HTTP '.$status.' then success retries');
    list($value,$calls)=$run_images(array($http));
    check(count($calls)===3&&($value['data']['provider_status']??0)===$status,'HTTP '.$status.' repeated failure stops at three');
}
foreach(array(400,401,403,404) as $status) {
    list($value,$calls)=$run_images(array(array('response'=>array('code'=>$status),'headers'=>array(),'body'=>''),$success));
    check(count($calls)===1&&($value['data']['provider_status']??0)===$status,'HTTP '.$status.' is not retried');
}
list($value,$calls)=$run_images(array(new WP_Error('http_request_not_executed','Blocked by policy'),$success));
check(count($calls)===1&&($value['data']['failure_kind']??'')==='blocked','blocked request never retries');
list($value,$calls)=$run_images(array(new WP_Error('http_request_failed','cURL error 60: password=privatevalue Authorization: Bearer privatevalue'),$success));
check(!str_contains(wp_json_encode($value),'privatevalue')&&($value['data']['wp_error_code']??'')==='http_request_failed','original diagnostic code retained while secrets redacted');
$method=new ReflectionMethod(UWNext_Resources::class,'image_request');$method->setAccessible(true);
$merge_calls=array();
$merge_mock=function($pre,$args,$url)use(&$merge_calls,$success){if(!str_starts_with($url,'https://api.openverse.org/v1/images/')){return $pre;}$merge_calls[]=$args;return $success;};
add_filter('pre_http_request',$merge_mock,1,3);
try {$method->invoke(null,'https://api.openverse.org/v1/images/?q=fixture',array('accept'=>'','ACCEPT'=>'text/html','X-Test'=>'retained'));}
finally {remove_filter('pre_http_request',$merge_mock,1);}
check(($merge_calls[0]['headers']??array())===array('X-Test'=>'retained','Accept'=>'application/json'),'case-insensitive caller header merge cannot erase default Accept');
