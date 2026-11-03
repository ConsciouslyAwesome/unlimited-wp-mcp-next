<?php
defined('ABSPATH') || exit;
function uwnext_preview_token() {
    $stored=get_option('uwnext_preview_token');
    $value=is_string($stored)?UWNext_Store::decrypt($stored):false;
    return is_array($value) ? ($value['token']??false) : false;
}
function uwnext_set_preview_token($token) { return update_option('uwnext_preview_token',UWNext_Store::encrypt(array('token'=>$token)),false); }
final class UWNext_Store {
    public static function install() {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table=$wpdb->prefix.'uwnext_events';
        dbDelta("CREATE TABLE $table (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
created bigint(20) NOT NULL,
user_id bigint(20) NOT NULL,
connection varchar(100) NOT NULL,
operation varchar(100) NOT NULL,
op_id varchar(64) NOT NULL,
status varchar(40) NOT NULL,
PRIMARY KEY  (id)
) ".$wpdb->get_charset_collate().';');
    }
    public static function id() { return bin2hex(random_bytes(24)); }
    public static function key($kind,$id) { return 'uwnext_'.$kind.'_'.hash('sha256',(string)$id); }
    public static function put($kind,$id,$value) { return add_option(self::key($kind,$id),$value,'',false); }
    public static function get($kind,$id) { return get_option(self::key($kind,$id),false); }
    public static function delete($kind,$id) { return delete_option(self::key($kind,$id)); }
    /** Atomic compare-and-delete: exactly one request may spend a code/approval. */
    public static function consume($kind,$id,$expected) {
        global $wpdb;
        $key=self::key($kind,$id);
        $n=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$key,maybe_serialize($expected)));
        wp_cache_delete($key,'options');
        return 1===$n;
    }
    public static function records($kind) {
        global $wpdb;
        $names=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like('uwnext_'.$kind.'_').'%'));
        $out=array(); foreach($names as $name) { $out[$name]=get_option($name); } return $out;
    }
    public static function encrypt($value) {
        $iv=random_bytes(12); $tag='';
        $cipher=openssl_encrypt(wp_json_encode($value),'aes-256-gcm',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,$iv,$tag,'uwnext-approval');
        if(false===$cipher) { throw new RuntimeException('Encryption unavailable'); }
        return base64_encode($iv.$tag.$cipher);
    }
    public static function decrypt($value) {
        $raw=base64_decode($value,true); if(false===$raw || strlen($raw)<28) { return false; }
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'uwnext-approval');
        return false===$plain ? false : json_decode($plain,true);
    }
    public static function canonical($value) {
        if(is_object($value)) { $value=get_object_vars($value); }
        if(is_array($value)) { if(!array_is_list($value)) { ksort($value,SORT_STRING); } foreach($value as &$v) { $v=self::canonical($v); } unset($v); }
        return $value;
    }
    public static function digest($tool,$args) { return hash('sha256',wp_json_encode(self::canonical(array('site'=>site_url(),'tool'=>$tool,'args'=>$args)))); }
    public static function log($operation,$status,$op='') {
        global $wpdb;
        return false!==$wpdb->insert($wpdb->prefix.'uwnext_events',array('created'=>time(),'user_id'=>get_current_user_id(),'connection'=>substr(UWNext_Auth::$connection,0,100),'operation'=>substr($operation,0,100),'op_id'=>substr($op,0,64),'status'=>substr($status,0,40)));
    }
    public static function recent($limit=50) {
        global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}uwnext_events ORDER BY id DESC LIMIT %d",min(200,max(1,$limit))),ARRAY_A);
    }
    public static function cleanup() {
        global $wpdb;
        $days=max(1,min(365,(int)get_option('uwnext_retention_days',30)));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}uwnext_events WHERE created < %d",time()-DAY_IN_SECONDS*$days));
        foreach(array('approval','code','access','refresh','spent_refresh') as $kind) { foreach(self::records($kind) as $key=>$row) { if(isset($row['expires']) && $row['expires']<time()) { delete_option($key); } } }
    }
}
/** Compatibility adapter: upstream raw command/result logging is deliberately omitted. */
class UWNext_Audit_Log {
    public static function log_execution($args) { return UWNext_Store::log('approved_cli','executed'); }
    public static function get_recent($limit=50,$offset=0) { return UWNext_Store::recent($limit); }
    public static function count() { global $wpdb; return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}uwnext_events"); }
}
