<?php
/** Read-only route/schema extraction from the pinned GPL class, never invoke a handler. */
define('ABSPATH',__DIR__.'/');
function register_rest_route($ns,$route,$def) {
    global $routes;
    $props=array();$required=array();
    foreach($def['args']??array() as $key=>$arg) {
        $props[$key]=array_intersect_key($arg,array_flip(array('type','enum','default','pattern','minimum','maximum','minLength','maxLength','description')));
        if(!empty($arg['required']))$required[]=$key;
    }
    $routes[$route]=array('method'=>$def['methods'],'inputSchema'=>array('type'=>'object','properties'=>(object)$props,'required'=>$required,'additionalProperties'=>false));
}
class WPVibe_Builder_Login {static function instance(){return new self;}}
require $argv[1];
$routes=array();
(new ReflectionClass('WPVibe_REST'))->newInstanceWithoutConstructor()->register_routes();
echo json_encode($routes,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
