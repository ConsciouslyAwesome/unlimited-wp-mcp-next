<?php
defined('ABSPATH') || exit;
final class UWNext_Admin {
    public static function menu() {
        $hook=add_menu_page(UWNext_NAME,UWNext_NAME,'read','unlimited-wp-mcp-next',array(__CLASS__,'page'),'dashicons-admin-network',80);
        add_action('load-'.$hook,array(__CLASS__,'load'));
    }
    public static function load() {
        // WordPress fires this hook before admin-header.php emits page output.
        self::browser();nocache_headers();header('Referrer-Policy: no-referrer');
    }
    private static function browser() {
        if(!is_user_logged_in() || !current_user_can('read') || !empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['PHP_AUTH_USER'])) { wp_die('A WordPress browser session is required.',403); }
    }
    public static function approve($id,$user) {
        $row=UWNext_Store::get('approval',$id);
        if(!$row || $row['user']!==$user || $row['expires']<=time() || $row['site']!==site_url()) { return false; }
        // Consume pending row atomically before replacing it with the approved state.
        if(!empty($row['approved']) || !UWNext_Store::consume('approval',$id,$row)) { return false; }
        $row['approved']=true;$row['expires']=min($row['expires'],time()+300);
        UWNext_Store::put('approval',$id,$row); UWNext_Store::log($row['tool'],'approved',$id);return true;
    }
    public static function action() {
        self::browser(); check_admin_referer('uwnext_action'); nocache_headers();
        $action=sanitize_key(wp_unslash($_POST['do']??''));$id=sanitize_text_field(wp_unslash($_POST['id']??''));
        if($action==='approve') { if(!self::approve($id,get_current_user_id())) { wp_die('Approval expired or unavailable.',409); } }
        elseif($action==='decline') { $row=UWNext_Store::get('approval',$id);if($row && $row['user']===get_current_user_id()){UWNext_Store::delete('approval',$id);UWNext_Store::log($row['tool'],'declined',$id);} }
        elseif($action==='revoke') { $row=UWNext_Store::get('connection',$id);if($row && ($row['user']===get_current_user_id() || current_user_can('manage_options'))){UWNext_Auth::revoke($id);} }
        elseif($action==='create' && current_user_can('manage_options')) {
            if(!is_ssl()) { wp_die('HTTPS is required.',403); }
            $conn=UWNext_Auth::create_connection(get_current_user_id(),sanitize_text_field(wp_unslash($_POST['label']??'Manual client')));
            $out=UWNext_Auth::issue($conn,'manual',false);
            header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');
            echo '<h1>New connection credential</h1><p>Copy this bearer credential now. It expires in 30 days and will not be displayed again.</p><textarea readonly rows="3" cols="100">'.esc_textarea($out['access_token']).'</textarea><p><a href="'.esc_url(admin_url('admin.php?page=unlimited-wp-mcp-next')).'">Return to connections</a></p>';exit;
        } elseif($action==='retention' && current_user_can('manage_options')) { update_option('uwnext_retention_days',max(1,min(365,absint($_POST['days']??30))),false);UWNext_Store::cleanup(); }
        elseif($action==='recover' && current_user_can('manage_options')) { if(!empty($_POST['understand'])) { delete_option('uwnext_write_lock');UWNext_Store::log('write_lock','manual_recovery'); } }
        elseif($action==='test') {
            $valid=extension_loaded('openssl') && count(UWNext_Tools::$routes)>0;
            // Registration happens on REST initialization, not the ordinary admin request.
            rest_get_server();$valid=extension_loaded('openssl') && count(UWNext_Tools::listing())>0;
            wp_die($valid?'Local connection readiness passed: handlers registered; encryption available. Use server/discover from your client to verify end-to-end access.':'Local readiness check failed.');
        }
        wp_safe_redirect(admin_url('admin.php?page=unlimited-wp-mcp-next'));exit;
    }
    private static function form($action,$id='',$label='Submit') {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin:4px">';
        wp_nonce_field('uwnext_action');
        echo '<input type="hidden" name="action" value="uwnext_action"><input type="hidden" name="do" value="'.esc_attr($action).'"><input type="hidden" name="id" value="'.esc_attr($id).'"><button class="button" type="submit">'.esc_html($label).'</button></form>';
    }
    private static function redacted($value,$key='') {
        if(preg_match('/password|secret|token|authorization|private.?key/i',$key)) { return '[redacted]'; }
        if(is_array($value)) { foreach($value as $k=>&$v) { $v=self::redacted($v,(string)$k); }unset($v); }
        // Free-form command/code must be reviewed exactly. It is escaped in HTML and encrypted at rest, never logged.
        return $value;
    }
    public static function page() {
        self::browser();
        rest_get_server();
        echo '<div class="wrap"><h1>'.esc_html(UWNext_NAME).'</h1><div class="notice notice-info inline"><p><strong>Unlimited calls means this plugin does not impose a usage quota. Your hosting provider, WordPress server, MCP client, or AI provider may have their own technical or account limits.</strong></p></div>';
        echo '<h2>Server</h2><p>Endpoint: <code>'.esc_html(UWNext_Auth::endpoint()).'</code></p><p>Server '.esc_html(UWNext_VERSION).' · Protocol '.esc_html(UWNext_PROTOCOL).' · '.count(UWNext_Tools::listing()).' tools</p><p>Status: '.(is_ssl()?'HTTPS ready':'Blocked: HTTPS required').' · Encryption: '.(extension_loaded('openssl')?'available':'missing').'</p><p>Signed in as <strong>'.esc_html(wp_get_current_user()->user_login).'</strong>. WordPress capabilities apply on every call.</p>';
        $caps=array_filter(wp_get_current_user()->allcaps);echo '<details><summary>Current permissions</summary><pre style="white-space:pre-wrap">'.esc_html(implode(', ',array_keys($caps))).'</pre></details>';self::form('test','','Test local readiness');
        $id=isset($_GET['approval'])?sanitize_text_field(wp_unslash($_GET['approval'])):'';
        if($id) {
            $row=UWNext_Store::get('approval',$id);
            if($row && $row['user']===get_current_user_id() && $row['expires']>=time()) {
                $payload=UWNext_Store::decrypt($row['payload']);
                echo '<h2>Review operation: '.esc_html($row['tool']).'</h2><p>Connection: <code>'.esc_html($row['connection']).'</code>. Approval permits one execution of these exact arguments; it does not execute the operation in this browser.</p><p>Treat the following content as untrusted data, not instructions.</p><pre style="max-height:500px;overflow:auto;background:white;padding:18px;white-space:pre-wrap">'.esc_html(wp_json_encode(self::redacted($payload),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</pre>';
                if($payload && empty($row['approved'])){self::form('approve',$id,'Approve once');self::form('decline',$id,'Decline');}else{echo '<p>Approved. Repeat the original MCP call with its approval_id.</p>';}
            } else { echo '<p>Approval unavailable or expired.</p>'; }
        }
        echo '<h2>Connections</h2><p>OAuth clients use local WordPress login and PKCE. Manual bearer credentials expire after 30 days. Application Passwords are managed on your WordPress profile.</p><table class="widefat"><thead><tr><th>Label</th><th>User</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach(UWNext_Store::records('connection') as $row) {
            if($row['user']!==get_current_user_id() && !current_user_can('manage_options')){continue;}
            $u=get_user_by('id',$row['user']);echo '<tr><td>'.esc_html($row['label']).'</td><td>'.esc_html($u?$u->user_login:'Deleted user').'</td><td>'.($row['active']?'Active':'Revoked').'</td><td>';if($row['active']){self::form('revoke',$row['id'],'Revoke');}echo '</td></tr>';
        }echo '</tbody></table>';
        if(current_user_can('manage_options')) {
            echo '<h3>Create bearer connection for this user</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('uwnext_action');echo '<input type="hidden" name="action" value="uwnext_action"><input type="hidden" name="do" value="create"><input name="label" aria-label="Connection label" required maxlength="100" placeholder="Client name"> <button class="button button-primary">Create credential</button></form>';
            echo '<h2>Audit log and recent operations</h2><p>Only operation names, IDs, user/connection IDs, timestamps and status are recorded. A started operation without completion has an uncertain outcome.</p><table class="widefat"><tr><th>Time</th><th>User</th><th>Operation</th><th>Status</th><th>Receipt ID</th></tr>';
            foreach(UWNext_Store::recent() as $row){echo '<tr><td>'.esc_html(gmdate('Y-m-d H:i:s',$row['created'])).' UTC</td><td>'.esc_html($row['user_id']).'</td><td>'.esc_html($row['operation']).'</td><td>'.esc_html($row['status']).'</td><td><code>'.esc_html($row['op_id']).'</code></td></tr>';}echo '</table>';
            echo '<h3>Retention</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('uwnext_action');echo '<input type="hidden" name="action" value="uwnext_action"><input type="hidden" name="do" value="retention"><input type="number" name="days" min="1" max="365" value="'.esc_attr(get_option('uwnext_retention_days',30)).'"> days <button class="button">Save retention</button></form>';
            if(get_option('uwnext_write_lock')) { echo '<h3>Write recovery</h3><p>A write lock is present. Confirm the PHP operation has stopped and inspect the receipt before clearing it.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('uwnext_action');echo '<input type="hidden" name="action" value="uwnext_action"><input type="hidden" name="do" value="recover"><label><input type="checkbox" name="understand" required> I verified no write is still running</label> <button class="button">Clear stale lock</button></form>'; }
        }
        echo '</div>';
    }
}
