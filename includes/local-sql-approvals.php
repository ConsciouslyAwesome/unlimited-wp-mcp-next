<?php
/** Unlimited session storage for WPVibe 1.20.5's documented 30-minute/25-statement SQL scope. */
defined('ABSPATH') || exit;
final class UWNext_SQL_Approvals {
    const TTL=1800;
    const STATEMENTS=25;
    public static function eligible($preview) {
        return is_array($preview) && empty($preview['refuse']) && preg_match('/^db_query_(update|insert|delete|replace):sql$/D',$preview['operation']??'');
    }
    private static function scope() {
        return array('site'=>site_url(),'user'=>get_current_user_id(),'connection'=>UWNext_Auth::$connection);
    }
    private static function id() { return hash('sha256',wp_json_encode(self::scope())); }
    /** Only called by the browser's approval handler after its user/site checks. */
    public static function remember($approval) {
        $cookie=wp_validate_auth_cookie('','logged_in');
        if(!is_user_logged_in() || get_current_user_id()!==$approval['user'] || !$cookie || (int)$cookie!==get_current_user_id() || UWNext_Tools::executing() || !empty(UWNext_Auth::$connection) || did_action('application_password_did_authenticate') || (function_exists('rest_get_authenticated_app_password') && rest_get_authenticated_app_password())) { return false; }
        $payload=UWNext_Store::decrypt($approval['payload']);
        if(($approval['tool']??'')!=='cli_run'||!self::eligible($payload['preview']??null)) { return false; }
        $scope=array('site'=>$approval['site'],'user'=>$approval['user'],'connection'=>$approval['connection']);
        $id=hash('sha256',wp_json_encode($scope));
        UWNext_Store::delete('sql_session',$id);
        // The explicitly approved first statement is one of the 25 statements.
        return UWNext_Store::put('sql_session',$id,$scope+array('expires'=>time()+self::TTL,'remaining'=>self::STATEMENTS-1));
    }
    /** Called under the existing write lock; spend once before execution, never retry a statement. */
    public static function take($preview) {
        if(!self::eligible($preview)) { return false; }
        $id=self::id();$row=UWNext_Store::get('sql_session',$id);
        if(!$row || $row['expires']<=time() || $row['remaining']<=0 || array_intersect_key($row,self::scope())!==self::scope()) { return false; }
        if(!UWNext_Store::consume('sql_session',$id,$row)) { return false; }
        $row['remaining']--;
        return UWNext_Store::put('sql_session',$id,$row);
    }
    public static function available($preview) {
        $row=UWNext_Store::get('sql_session',self::id());
        return self::eligible($preview) && $row && $row['expires']>time() && $row['remaining']>0;
    }
}
