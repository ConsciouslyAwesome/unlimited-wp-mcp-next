<?php
/**
 * Plugin Name: Unlimited WP MCP Next
 * Description: Owner-controlled WordPress MCP server with local approvals and no usage quota.
 * Version: 2.0.0-dev
 * Author: Unlimited WP MCP contributors
 * License: GPL-2.0-or-later
 * Text Domain: unlimited-wp-mcp-next
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Update URI: false
 */
defined('ABSPATH') || exit;
define('UWNext_NAME', 'Unlimited WP MCP Next');
define('UWNext_VERSION', '2.0.0-dev');
define('UWNext_PROTOCOL', '2026-07-28');
define('UWNext_NS', 'unlimited-wp-mcp-next/v1');
define('UWNext_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UWNext_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UWNext_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once __DIR__ . '/includes/local-store.php';
require_once __DIR__ . '/includes/local-auth.php';
require_once __DIR__ . '/includes/local-tools.php';
require_once __DIR__ . '/includes/local-mcp.php';
require_once __DIR__ . '/includes/local-admin.php';
require_once __DIR__ . '/includes/upstream-helpers.php';
require_once __DIR__ . '/includes/local-adapters.php';
require_once __DIR__ . '/includes/local-capabilities.php';
foreach (array('error-contract','svg-sanitizer','builder-login','timing','output-guard','file-ops','content-files','content-ops','draft-lock','draft-theme','preview','cli','code-snippet','change-tracker','live-reload','classic-theme','php-guard','rest','elementor','beaver','breakdance','bricks') as $uwnext_module) {
    require_once __DIR__ . '/includes/class-uwnext-' . $uwnext_module . '.php';
}
foreach (array('fields','field-renderers','meta-boxes','settings','edit-affordance') as $uwnext_module) {
    require_once __DIR__ . '/includes/fields/class-uwnext-' . $uwnext_module . '.php';
}
unset($uwnext_module);
function uwnext_activate() {
    if (!extension_loaded('openssl')) { wp_die('Unlimited WP MCP requires OpenSSL for encrypted pending approvals.'); }
    UWNext_Store::install();
    if (!wp_next_scheduled('uwnext_cleanup')) { wp_schedule_event(time()+3600, 'daily', 'uwnext_cleanup'); }
}
function uwnext_deactivate() { wp_clear_scheduled_hook('uwnext_cleanup'); }
register_activation_hook(__FILE__, 'uwnext_activate');
register_deactivation_hook(__FILE__, 'uwnext_deactivate');
add_action('uwnext_cleanup', array('UWNext_Store','cleanup'));
add_action('plugins_loaded', function() {
    UWNext_REST::instance();
    foreach (array('UWNext_Elementor','UWNext_Beaver','UWNext_Breakdance','UWNext_Bricks','UWNext_Preview','UWNext_Live_Reload','UWNext_Fields','UWNext_Edit_Affordance','UWNext_Builder_Login','UWNext_Timing','UWNext_Output_Guard') as $class) { $class::instance(); }
    if (is_admin()) { UWNext_Meta_Boxes::instance(); UWNext_Settings::instance(); }
    UWNext_Code_Snippet::load_wpcode_file_cache();
});
add_filter('extra_theme_headers', function($headers) { $headers['UWNext']='UWNext'; return $headers; });
add_action('rest_api_init', array('UWNext_MCP','register'));
add_action('rest_api_init', array('UWNext_Auth','register'));
// Browser live-reload polling is the sole retained public handler route. WordPress
// validates its REST cookie nonce; the callback also requires edit_posts.
add_action('rest_api_init',function(){
    register_rest_route(UWNext_NS,'/last-change',array('methods'=>'GET','callback'=>array(UWNext_REST::instance(),'get_last_change'),'permission_callback'=>array(UWNext_REST::instance(),'can_edit_posts'),'args'=>array('since'=>array('type'=>'number','default'=>0))));
});
add_action('admin_menu', array('UWNext_Admin','menu'));
add_action('admin_post_uwnext_action', array('UWNext_Admin','action'));
add_action('admin_post_uwnext_authorize', array('UWNext_Auth','authorize'));
add_action('admin_post_nopriv_uwnext_authorize', array('UWNext_Auth','authorize'));
add_action('application_password_did_authenticate', function($user,$item) { UWNext_Auth::$app = array('user'=>$user->ID,'uuid'=>$item['uuid']); },10,2);
add_action('parse_request', array('UWNext_Auth','well_known'),0);
add_action('wp_enqueue_scripts',function() {
    $draft=get_option('uwnext_draft_theme');
    if($draft && get_stylesheet()===$draft) {
        wp_enqueue_style('uwnext-presets',UWNext_PLUGIN_URL.'assets/presets.css',array(),UWNext_VERSION);
        wp_enqueue_script('uwnext-tailwind',UWNext_PLUGIN_URL.'assets/vendor/tailwind-browser.js',array(),'4.1.13',array('strategy'=>'defer'));
    }
});
add_action('rest_api_init',function() {
    if(!function_exists('wp_get_abilities')){return;}
    uwnext_register_internal_route(UWNext_NS,'/abilities',array('methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_options');},'args'=>array(),'callback'=>function(){
        $out=array();foreach(wp_get_abilities() as $ability){$out[]=array('name'=>$ability->get_name(),'description'=>$ability->get_description(),'input_schema'=>$ability->get_input_schema());}return array('untrusted_plugin_metadata'=>true,'abilities'=>$out);
    }));
    uwnext_register_internal_route(UWNext_NS,'/ability/run',array('methods'=>'POST','permission_callback'=>function(){return current_user_can('manage_options');},'args'=>array('name'=>array('type'=>'string','required'=>true),'input'=>array('type'=>'object','required'=>true)),'callback'=>function($request){
        $ability=wp_get_ability($request['name']);if(!$ability){return new WP_Error('ability_missing','Ability not registered.');}
        return $ability->execute($request['input']); // Core validates its schema and permission callback again.
    }));
});
