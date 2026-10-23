<?php
defined('ABSPATH') || exit;
/** Retain upstream route definitions as private in-process handler descriptors. */
function uwnext_register_internal_route($namespace,$route,$definition,$override=false) {
    UWNext_Tools::$routes[$route][$definition['methods']]=$definition;
}
final class UWNext_Tools {
    public static $routes=array();
    private static $executing=false;
    public static function executing() { return self::$executing; }
    private const READ=array('/rest-api/read','/ability/info','/site-info','/registered-meta','/file/read','/file/list','/file/search','/file/outline','/content/search','/classic-theme-safety','/draft-theme/compile-sources','/draft-theme/preview','/audit-log','/cli/status','/rendered-html','/last-change','/elementor/widgets','/elementor/schema','/elementor/style-schema','/beaver/modules','/beaver/schema','/breakdance/get-page','/breakdance/elements','/bricks/get-page','/bricks/elements');
    private const OMIT=array('/draft-theme/delete','/cli/run-approved','/authorize','/authorize/preflight','/connection-status','/op-proof/check','/audit-log/record','/op-receipt/(?P<op_id>[a-z0-9_.:]+)');
    public static function definitions() {
        $tools=array();
        foreach(self::$routes as $route=>$methods) {
            if(in_array($route,self::OMIT,true)) { continue; }
            foreach($methods as $method=>$def) {
                $name=trim(str_replace(array('/','-'),array('_','_'),$route),'_');
                if($route==='/draft-theme') { $name=$method==='DELETE'?'delete_draft_theme':'create_draft_theme'; }
                $read=in_array($route,self::READ,true) || $route==='/abilities';
                $props=array(); $required=array();
                foreach($def['args']??array() as $key=>$arg) {
                    if(in_array($key,array('detach','approved_state'),true)) { continue; }
                    $prop=array_intersect_key($arg,array_flip(array('type','enum','description','default','minimum','maximum','minLength','maxLength','pattern','items','properties','additionalProperties')));
                    if(empty($prop['type'])) { $prop['type']='string'; }
                    if(($prop['type']??'')==='integer' && in_array($key,array('max_results','limit'),true)) { $prop['minimum']=1;$prop['maximum']=200; }
                    $props[$key]=$prop;
                    if(!empty($arg['required'])) { $required[]=$key; }
                }
                if(!$read) { $props['approval_id']=array('type'=>'string','pattern'=>'^[a-f0-9]{48}$','description'=>'One-use local browser approval returned by a prior call with these exact arguments.'); }
                $label=ucwords(str_replace('_',' ',$name));
                $descriptions=array('cli_run'=>'Run an allowlisted WordPress administrative command using native PHP, not a shell. Supports posts, comments, users, roles, plugins, themes, options, transients, database and core. All commands require browser approval; destructive commands include a fresh state preview.','site_info'=>'Read site, theme, plugin and authenticated WordPress user information.','file_read'=>'Read a bounded theme or wp-content text file. Private/configuration files and sandbox escapes are denied.','file_write'=>'Write a draft theme file with PHP syntax checks and optional source-hash concurrency protection.','content_edit'=>'Replace exact text in an authorized post, meta value or allowed option.','draft_theme_publish'=>'Publish the draft theme after safety checks. Replaces live theme files; browser approval required.');
                $desc=$descriptions[$name]??($label.'. Uses the original WordPress handler and capability checks. '.($read?'Read operation.':'Write or potentially destructive operation; requires local browser approval.'));
                $callback=is_array($def['permission_callback']) ? $def['permission_callback'][1] : '';
                $caps=array('can_manage_themes'=>'edit_theme_options','can_manage_options'=>'manage_options','can_edit_posts'=>'edit_posts','can_read_themes'=>'edit_themes (edit_theme_options on a locked single site)','can_read_files'=>'edit_themes for theme files; manage_options for wp-content; network administrator on multisite','can_edit_themes'=>'edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS','can_publish_theme'=>'edit_themes and switch_themes','can_edit_content'=>'edit_post for the target post; manage_options for options','can_run_cli'=>'manage_options plus the command-specific capability','can_edit_code_snippets_approved'=>'wpcode_edit_snippets');
                $cap=$caps[$callback]??(str_contains($route,'/ability') || $route==='/abilities'?'manage_options plus the ability permission callback':($route==='/upload-media'?'upload_files':'edit_post for target content plus the builder-specific permission callback'));
                $desc.=' Requires '.$cap.'.';
                $tools[$name]=array('name'=>$name,'description'=>$desc,'inputSchema'=>array('type'=>'object','properties'=>(object)$props,'required'=>$required,'additionalProperties'=>false),'annotations'=>array('readOnlyHint'=>$read,'destructiveHint'=>!$read,'idempotentHint'=>$read,'openWorldHint'=>true),'_meta'=>array('org.unlimitedwpmcp/permission'=>$cap),'route'=>$route,'method'=>$method,'def'=>$def,'read'=>$read);
            }
        }
        $tools['operation_receipt']=array('name'=>'operation_receipt','description'=>'Read the local status of an operation belonging to this authenticated connection. A started operation with no completion is uncertain and must not be blindly retried.','inputSchema'=>array('type'=>'object','properties'=>(object)array('operation_id'=>array('type'=>'string','pattern'=>'^[a-f0-9]{48}$')),'required'=>array('operation_id'),'additionalProperties'=>false),'annotations'=>array('readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false),'_meta'=>array('org.unlimitedwpmcp/permission'=>'read; same user and connection only'),'read'=>true);
        ksort($tools,SORT_STRING); return $tools;
    }
    public static function listing() {
        return array_values(array_map(function($t){ unset($t['route'],$t['method'],$t['def'],$t['read']);return $t; },self::definitions()));
    }
    private static function redact_error($value) {
        if(is_array($value)) { return array_map(array(__CLASS__,'redact_error'),$value); }
        if(!is_string($value)) { return $value; }
        foreach(array(WP_CONTENT_DIR,ABSPATH,sys_get_temp_dir()) as $root) {
            $root=rtrim($root,'/\\');
            $value=str_ireplace(array($root,str_replace('\\','/',$root),str_replace('/','\\',$root)),'[server path]',$value);
        }
        return UWNext_Content_Files::redact($value) ?? 'Error details unavailable.';
    }
    public static function result($value) {
        if(is_wp_error($value)) { return array('resultType'=>'complete','isError'=>true,'content'=>array(array('type'=>'text','text'=>wp_json_encode(self::redact_error(array('code'=>$value->get_error_code(),'message'=>$value->get_error_message(),'data'=>$value->get_error_data())))))); }
        if($value instanceof WP_REST_Response) { if($value->get_status()>=400) { return self::result(new WP_Error('handler_error','WordPress handler rejected the operation.',array('status'=>$value->get_status()))); } $value=$value->get_data(); }
        if(is_array($value) && isset($value['exit_code']) && $value['exit_code']!==0) { return self::result(new WP_Error('command_failed',(string)($value['stderr']??'Command failed.'),array('exit_code'=>$value['exit_code']))); }
        return array('resultType'=>'complete','isError'=>false,'content'=>array(array('type'=>'text','text'=>wp_json_encode($value))));
    }
    public static function deny($code,$message,$status=403) { return new WP_Error($code,$message,array('status'=>$status)); }
    public static function validate($tool,$args) {
        if(!is_array($args)) { return self::deny('invalid_arguments','Arguments must be an object.',400); }
        // JSON Schema emitted here uses this bounded subset of 2020-12, validated by WordPress.
        $schema=$tool['inputSchema']; $schema['properties']=(array)$schema['properties'];
        $valid=rest_validate_value_from_schema($args,$schema,'arguments');
        return is_wp_error($valid) ? $valid : true;
    }
    public static function call($name,$args) {
        $all=self::definitions(); if(!isset($all[$name])) { return self::deny('unknown_tool','Unknown tool.',400); }
        $tool=$all[$name]; $valid=self::validate($tool,$args); if(is_wp_error($valid)) { return $valid; }
        if(isset($tool['route']) && str_starts_with($tool['route'],'/file/')) {
            foreach(array('path','directory') as $key) {
                if(isset($args[$key]) && (preg_match('/[\x00-\x1f\x7f\\\\:]/',$args[$key]) || str_starts_with($args[$key],'/') || preg_match('#(?:^|/)\.\.?(/|$)#',$args[$key]))) { return self::deny('path_traversal','Use a clean relative path inside the file sandbox.'); }
            }
        }
        if($name==='operation_receipt') {
            global $wpdb;
            return $wpdb->get_results($wpdb->prepare("SELECT operation,op_id,status,created FROM {$wpdb->prefix}uwnext_events WHERE op_id=%s AND connection=%s AND user_id=%d ORDER BY id",$args['operation_id'],UWNext_Auth::$connection,get_current_user_id()),ARRAY_A);
        }
        $approval=$args['approval_id']??'';unset($args['approval_id']);
        $def=$tool['def']; $req=new WP_REST_Request($tool['method'],'/'.UWNext_NS.$tool['route']);
        $req->set_attributes($def);
        if($tool['method']==='GET') { $req->set_query_params($args); }
        else { $req->set_body_params($args); }
        foreach($def['args']??array() as $k=>$v) { if(!array_key_exists($k,$args) && array_key_exists('default',$v)) { $req->set_param($k,$v['default']); } }
        $valid=$req->has_valid_params();if(is_wp_error($valid)){return $valid;}
        $valid=$req->sanitize_params();if(is_wp_error($valid)){return $valid;}
        $permission=$def['permission_callback'];
        if(is_array($permission) && in_array($permission[1],array('can_edit_code_snippets_approved','can_edit_code_snippets_dormant'),true)) { $permission=array($permission[0],'can_edit_code_snippets'); }
        $perm=call_user_func($permission,$req);
        if($perm!==true) { UWNext_Store::log($name,'denied');return is_wp_error($perm)?$perm:self::deny('capability_denied','WordPress permission denied.'); }
        // A read-only content search must not use the broad theme capability to reveal private posts.
        if(str_starts_with($tool['route'],'/content/') && in_array($args['target_type']??'',array('post','meta'),true) && !current_user_can('edit_post',(int)($args['post_id']??0))) { return self::deny('capability_denied','This post is not editable by this user.'); }
        if($name==='cli_run') { $policy=(new UWNext_CLI())->uwnext_preview($req['command']); if(is_wp_error($policy)) { return $policy; } }
        $op=UWNext_Store::id();$row=null;
        if(!$tool['read']) {
            if(!$approval) { return self::request_approval($name,$args,$req); }
            $row=UWNext_Store::get('approval',$approval);
            if(!$row || empty($row['approved']) || $row['expires']<=time() || $row['user']!==get_current_user_id() || !hash_equals($row['connection'],UWNext_Auth::$connection) || !hash_equals($row['digest'],UWNext_Store::digest($name,$args)) || $row['site']!==site_url()) { return self::deny('approval_invalid','Approval is missing, expired, consumed or does not match this operation.'); }
            $op=$approval;
        }
        $lock=false;
        if(!$tool['read']) {
            $lock=add_option('uwnext_write_lock',$op,'',false);
            if(!$lock) { return self::deny('operation_busy','Another write is running or needs recovery. Inspect recent receipts before clearing a stale lock in WordPress.',409); }
            if(!UWNext_Store::consume('approval',$approval,$row)) { delete_option('uwnext_write_lock');return self::deny('approval_spent','Approval was already used.'); }
        }
        try {
            if(!UWNext_Store::log($name,'started',$op)) { return self::deny('audit_unavailable','Local operation receipt could not be stored.',503); }
            $payload=$row?UWNext_Store::decrypt($row['payload']):null;
            if($row && !$payload) { UWNext_Store::log($name,'failed',$op);return self::deny('approval_unreadable','Approval cannot be decrypted.'); }
            if(in_array($name,array('draft_theme_publish','delete_draft_theme'),true)) {
                $fresh=self::theme_snapshot();
                if(is_wp_error($fresh) || !hash_equals(hash('sha256',wp_json_encode($payload['preview'])),hash('sha256',wp_json_encode($fresh)))) {
                    UWNext_Store::log($name,'state_changed',$op);return self::deny('approval_state_changed','The draft changed after approval was requested. Review a fresh approval.');
                }
            }
            if($name==='cli_run') {
                $out=(new UWNext_CLI())->run_approved($req['command'],(bool)$req['confirm_write'],isset($payload['preview']['operation'])?wp_json_encode($payload['preview']):null);
            } else { self::$executing=!$tool['read']; $out=call_user_func($def['callback'],$req); }
            $failed=is_wp_error($out) || ($out instanceof WP_REST_Response && ($out->get_status()>=400 || (!empty($out->get_data()['exit_code']))));
            UWNext_Store::log($name,$failed?'failed':'completed',$op);
            return $out;
        } catch(Throwable $error) {
            UWNext_Store::log($name,'host_failure',$op);
            return self::deny('host_failure','WordPress could not complete the operation. Inspect the local receipt before retrying a write.',503);
        } finally { self::$executing=false; if($lock && get_option('uwnext_write_lock')===$op) { delete_option('uwnext_write_lock'); } }
    }
    public static function request_approval($name,$args,$request) {
        $preview=null;
        if(in_array($name,array('draft_theme_publish','delete_draft_theme'),true)) {
            $preview=self::theme_snapshot();if(is_wp_error($preview)){return $preview;}
        }
        if($name==='cli_run') {
            $preview=(new UWNext_CLI())->uwnext_preview($request['command']);
            if(is_wp_error($preview)) { return $preview; }
        }
        $id=UWNext_Store::id();
        $row=array('id'=>$id,'site'=>site_url(),'user'=>get_current_user_id(),'connection'=>UWNext_Auth::$connection,'digest'=>UWNext_Store::digest($name,$args),'expires'=>time()+600,'approved'=>false,'tool'=>$name,'payload'=>UWNext_Store::encrypt(array('arguments'=>$args,'preview'=>$preview)));
        UWNext_Store::put('approval',$id,$row); UWNext_Store::log($name,'approval_requested',$id);
        return new WP_Error('approval_required','Review and approve this exact operation in your WordPress browser session, then repeat the call with approval_id.',array('status'=>409,'approval_id'=>$id,'expires'=>$row['expires'],'approval_url'=>admin_url('admin.php?page=unlimited-wp-mcp-next&approval='.rawurlencode($id))));
    }
    private static function theme_snapshot() {
        $slug=get_option('uwnext_draft_theme');$root=realpath(get_theme_root());
        if(!is_string($slug) || !preg_match('/^[A-Za-z0-9_-]+$/D',$slug) || !$root) { return self::deny('draft_missing','No valid draft is available.'); }
        $dir=realpath($root.DIRECTORY_SEPARATOR.$slug);
        if(!$dir || is_link($root.DIRECTORY_SEPARATOR.$slug) || dirname($dir)!==$root) { return self::deny('draft_missing','Draft is outside the theme root.'); }
        $files=array();$bytes=0;$started=microtime(true);$entries=0;
        try {
            $walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
            foreach($walk as $file) {
                if(++$entries>5000 || microtime(true)-$started>5 || $file->isLink()) { return self::deny('snapshot_unsafe','Draft snapshot exceeds safety bounds or contains a link.'); }
                if(!$file->isFile()){continue;}
                $bytes+=$file->getSize();if($bytes>20971520){return self::deny('snapshot_too_large','Draft snapshot exceeds 20 MiB.');}
                $hash=hash_file('sha256',$file->getPathname());if(!$hash){return self::deny('snapshot_unreadable','Could not hash every draft file.');}
                $files[str_replace('\\','/',substr($file->getPathname(),strlen($dir)+1))]=$hash;
            }
        } catch(Throwable $e) { return self::deny('snapshot_unreadable','Could not inspect the complete draft.'); }
        ksort($files,SORT_STRING);return array('draft'=>$slug,'source'=>get_option('uwnext_draft_source'),'sha256_by_file'=>$files);
    }
}
