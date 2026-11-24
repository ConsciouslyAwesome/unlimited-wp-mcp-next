<?php
/**
 * Plugin Name: Unlimited Amelia Staging Isolation (test helper)
 * Description: Fixed-purpose isolation and encrypted configuration recovery for the named Amelia staging site. Never include in a release package.
 * Version: 0.1.0
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;

final class UWNext_Amelia_Staging_Isolation {
    const HOST='booking.fractalillumination.com';
    const BACKUP='uwnext_amelia_staging_backup_v1';
    const ENABLED='uwnext_amelia_staging_isolated_v1';
    const USER_FIELDS='id,googleCalendarId,outlookCalendarId,appleCalendarId,employeeAppleCalendar,zoomUserId';
    const TABLES=array('providers_to_google_calendar','providers_to_outlook_calendar','mobile_devices');

    public static function allowed($home,$site) {
        foreach(array($home,$site) as $url) {
            if(parse_url($url,PHP_URL_SCHEME)!=='https' || parse_url($url,PHP_URL_HOST)!==self::HOST || !in_array(parse_url($url,PHP_URL_PORT),array(null,443),true)) { return false; }
        }
        return true;
    }
    private static function assert_site() {
        if(!self::allowed((string)get_option('home'),(string)get_option('siteurl')) || is_multisite()
            || !current_user_can('manage_options') || !class_exists('UWNext_Store') || !defined('AMELIA_VERSION') || AMELIA_VERSION!=='9.10') {
            throw new RuntimeException('This helper requires the named single-site staging installation, Amelia 9.10 and an administrator.');
        }
    }
    public static function settings($original) {
        if(!is_array($original) || !isset($original['notifications'])) { throw new RuntimeException('Unsupported Amelia settings shape.'); }
        $settings=$original;
        foreach(array('googleCalendar','outlookCalendar','appleCalendar','zoom','lessonSpace','mailchimp') as $name) {
            $settings[$name]['enabled']=false;
        }
        $settings['notifications']['mailService']='wp_mail';
        $settings['notifications']['smsSignedIn']=false;
        $settings['notifications']['smsApiToken']='';
        $settings['notifications']['whatsAppEnabled']=false;
        $settings['notifications']['whatsAppReplyEnabled']=false;
        foreach(array('whatsAppAccessToken','whatsAppPhoneID','whatsAppBusinessID','whatsAppReplyToken') as $name) { $settings['notifications'][$name]=''; }
        $settings['webHooks']=array();
        // Preserve working hours, resources, durations, buffers, slot-grid and all scheduling settings.
        return $settings;
    }
    public static function guards() {
        if(!self::allowed((string)get_option('home'),(string)get_option('siteurl')) || !get_option(self::ENABLED)) { return; }
        add_filter('pre_wp_mail',static function(){return true;},PHP_INT_MAX);
        add_filter('pre_http_request',static function(){return new WP_Error('staging_egress_blocked','Staging isolation blocks WordPress HTTP.');},PHP_INT_MAX,3);
        add_filter('pre_option_cron',static function(){return array();},PHP_INT_MAX);
    }
    private static function query($sql) {
        global $wpdb;
        if($wpdb->query($sql)===false) { throw new RuntimeException('An isolation database operation failed. Details are not returned because they may contain credentials.'); }
    }
    private static function rows($sql) {
        global $wpdb;
        $rows=$wpdb->get_results($sql,ARRAY_A);
        if(!is_array($rows) || $wpdb->last_error!=='') { throw new RuntimeException('Isolation snapshot could not be read.'); }
        return $rows;
    }
    private static function count_rows($sql) {
        $rows=self::rows($sql);
        if(count($rows)!==1 || !isset($rows[0]['n']) || !is_numeric($rows[0]['n'])) { throw new RuntimeException('Isolation counts could not be verified.'); }
        return (int)$rows[0]['n'];
    }
    private static function prefix() {
        global $wpdb;
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$wpdb->prefix)) { throw new RuntimeException('Unsupported table prefix.'); }
        return $wpdb->prefix.'amelia_';
    }
    private static function transactional($prefix) {
        global $wpdb;
        $tables=array_merge(array($wpdb->options,$prefix.'users',$prefix.'notifications'),array_map(static function($name)use($prefix){return $prefix.$name;},self::TABLES));
        foreach($tables as $table) {
            $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
            if($engine!=='InnoDB') { throw new RuntimeException('Every touched table must use InnoDB.'); }
        }
    }
    public static function isolate() {
        self::assert_site();global $wpdb;$p=self::prefix();self::transactional($p);
        if(get_option(self::BACKUP)) { throw new RuntimeException('An encrypted backup already exists. It will not be overwritten.'); }
        $raw=get_option('amelia_settings');$settings=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        $backup=array('site'=>site_url(),'version'=>AMELIA_VERSION,'settings'=>$raw,'plugins'=>get_option('active_plugins'),
            'users'=>self::rows('SELECT '.self::USER_FIELDS.' FROM '.$p.'users'),
            'notifications'=>self::rows('SELECT id,status FROM '.$p.'notifications'),'connections'=>array());
        foreach(self::TABLES as $table) { $backup['connections'][$table]=self::rows('SELECT * FROM '.$p.$table); }
        $encrypted=UWNext_Store::encrypt($backup);
        if(UWNext_Store::decrypt($encrypted)!==$backup || !add_option(self::BACKUP,$encrypted,'',false)) { throw new RuntimeException('Encrypted backup could not be verified and saved. No isolation changes were made.'); }
        // Persist backup before mutation. Keep guards enabled on any partial failure.
        update_option(self::ENABLED,true,false);self::guards();
        UWNext_Store::log('amelia_staging_isolation','started');
        self::query('START TRANSACTION');
        try {
            foreach(self::TABLES as $table) { self::query('DELETE FROM '.$p.$table); }
            self::query('UPDATE '.$p.'users SET googleCalendarId=NULL,outlookCalendarId=NULL,appleCalendarId=NULL,employeeAppleCalendar=NULL,zoomUserId=NULL');
            self::query("UPDATE {$p}notifications SET status='disabled'");
            $isolated=wp_json_encode(self::settings($settings));
            update_option('amelia_settings',$isolated,false);
            if(get_option('amelia_settings')!==$isolated) { throw new RuntimeException('Isolated settings were not stored.'); }
            $allow=array('ameliabooking/ameliabooking.php','unlimited-wp-mcp-next/unlimited-wp-mcp-next.php',plugin_basename(__FILE__));
            update_option('active_plugins',array_values(array_intersect($backup['plugins'],$allow)),false);
            self::query('COMMIT');
            UWNext_Store::log('amelia_staging_isolation','completed');
        } catch(Throwable $error) { $wpdb->query('ROLLBACK');wp_cache_flush();UWNext_Store::log('amelia_staging_isolation','failed');throw $error; }
    }
    public static function restore() {
        self::assert_site();global $wpdb;$p=self::prefix();self::transactional($p);
        $backup=UWNext_Store::decrypt(get_option(self::BACKUP));
        if(!$backup || $backup['site']!==site_url() || $backup['version']!==AMELIA_VERSION) { throw new RuntimeException('No matching backup is available.'); }
        UWNext_Store::log('amelia_staging_restore','started');self::query('START TRANSACTION');
        try {
            foreach(self::TABLES as $table) {
                self::query('DELETE FROM '.$p.$table);
                foreach($backup['connections'][$table] as $row) { if($wpdb->insert($p.$table,$row)===false) { throw new RuntimeException('Connection recovery failed.'); } }
            }
            foreach($backup['users'] as $row) { $id=$row['id'];unset($row['id']);if($wpdb->update($p.'users',$row,array('id'=>$id))===false) { throw new RuntimeException('User recovery failed.'); } }
            foreach($backup['notifications'] as $row) { if($wpdb->update($p.'notifications',array('status'=>$row['status']),array('id'=>$row['id']))===false) { throw new RuntimeException('Notification recovery failed.'); } }
            update_option('amelia_settings',$backup['settings'],false);update_option('active_plugins',$backup['plugins'],false);
            self::query('COMMIT');update_option(self::ENABLED,false,false);UWNext_Store::log('amelia_staging_restore','completed');
        } catch(Throwable $error) { $wpdb->query('ROLLBACK');wp_cache_flush();UWNext_Store::log('amelia_staging_restore','failed');throw $error; }
        // Retain the encrypted backup. No automatic restoration or credential revocation occurs.
    }
    public static function report() {
        self::assert_site();global $wpdb;$p=self::prefix();$counts=array();
        foreach(self::TABLES as $table) { $counts[$table]=self::count_rows('SELECT COUNT(*) AS n FROM '.$p.$table); }
        $counts['enabled_notifications']=self::count_rows("SELECT COUNT(*) AS n FROM {$p}notifications WHERE status='enabled'");
        $counts['linked_users']=self::count_rows("SELECT COUNT(*) AS n FROM {$p}users WHERE googleCalendarId IS NOT NULL OR outlookCalendarId IS NOT NULL OR appleCalendarId IS NOT NULL OR employeeAppleCalendar IS NOT NULL OR zoomUserId IS NOT NULL");
        $settings=json_decode(get_option('amelia_settings'),true);
        return array('host'=>self::HOST,'guards_enabled'=>(bool)get_option(self::ENABLED),'encrypted_backup_present'=>(bool)get_option(self::BACKUP),
            'counts'=>$counts,'sms_signed_in'=>$settings['notifications']['smsSignedIn']??null,'whatsapp_enabled'=>$settings['notifications']['whatsAppEnabled']??null,
            'configured_webhooks'=>count($settings['webHooks']??array()),'active_plugins'=>get_option('active_plugins'),
            'warning'=>'Counts and guards do not certify an infrastructure firewall. External-provider acceptance requires dedicated sandbox accounts.');
    }
    public static function page() {
        try {self::assert_site();}catch(Throwable $error){wp_die(esc_html($error->getMessage()));}
        echo '<div class="wrap"><h1>Amelia staging isolation</h1><p>Only '.esc_html(self::HOST).'. This test helper saves an encrypted backup, disables cloned external integrations and messaging, blocks WordPress mail/HTTP/cron, and limits active plugins to Amelia, Unlimited Next and this helper. It does not edit appointments, change scheduling settings or revoke production credentials.</p>';
        echo '<pre>'.esc_html(wp_json_encode(self::report(),JSON_PRETTY_PRINT)).'</pre>';
        foreach(array('isolate'=>'Back up and isolate staging','restore'=>'Restore original connections and messaging (ends isolation)') as $action=>$label) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('uwnext_staging_isolation');
            echo '<input type="hidden" name="action" value="uwnext_staging_isolation"><input type="hidden" name="operation" value="'.esc_attr($action).'"><p><button class="button" type="submit">'.esc_html($label).'</button></p></form>';
        }
        echo '</div>';
    }
    public static function action() {
        check_admin_referer('uwnext_staging_isolation');
        try {self::assert_site();$action=$_POST['operation']??'';if($action==='isolate'){self::isolate();}elseif($action==='restore'){self::restore();}else{throw new RuntimeException('Unsupported action.');}}
        catch(Throwable $error){wp_die(esc_html($error->getMessage()));}
        wp_safe_redirect(admin_url('tools.php?page=uwnext-amelia-staging-isolation'));exit;
    }
}
add_action('plugins_loaded',array('UWNext_Amelia_Staging_Isolation','guards'),0);
add_action('admin_menu',static function(){if(UWNext_Amelia_Staging_Isolation::allowed((string)get_option('home'),(string)get_option('siteurl'))){add_management_page('Amelia staging isolation','Amelia staging isolation','manage_options','uwnext-amelia-staging-isolation',array('UWNext_Amelia_Staging_Isolation','page'));}});
add_action('admin_post_uwnext_staging_isolation',array('UWNext_Amelia_Staging_Isolation','action'));
