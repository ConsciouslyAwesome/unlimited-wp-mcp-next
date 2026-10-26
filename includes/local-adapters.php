<?php
/** Unlimited-owned adapters for imported GPL handlers. Modified 2026-10-22. */
defined('ABSPATH') || exit;

final class UWNext_Op_Proof {
    // No vendor keys or remotely supplied proof can authorize execution.
    public static function verify($request,$route,$subject) {
        return UWNext_Tools::executing() ? true : new WP_Error('approval_required','A local exact-operation approval is required.',array('status'=>409));
    }
}
final class UWNext_White_Label {
    const OPTION='uwnext_white_label';
    public static function is_hidden() { return false; }
    public static function truthy($value) { return !empty($value); }
}
final class UWNext_Connection_Status {
    public static function note_activity() {}
}
final class UWNext_Self_Update {
    public static function auto_update_list() { return (array)get_site_option('auto_update_plugins',array()); }
    public static function set_auto_update_list($value) { return update_site_option('auto_update_plugins',array_values($value)); }
    public static function instance() { return new self(); }
    public function schedule_self_update(...$args) {
        return new WP_Error('independent_release_required','Install an independently verified Unlimited release through WordPress. Upstream self-updates are disabled.',array('status'=>403));
    }
}
