<?php
/** Independent, single-use WP-Cron jobs for locally approved CLI operations. */
defined('ABSPATH') || exit;

final class UWNext_Jobs {
    public static function enqueue($operation,$approval) {
        if(!UWNext_Store::put('job',$operation,$approval)) {
            return new WP_Error('job_exists','This operation already has a job. Inspect its receipt.',array('status'=>409));
        }
        $scheduled=wp_schedule_single_event(time()+1,'uwnext_job',array($operation),true);
        if(is_wp_error($scheduled)||!$scheduled) {
            UWNext_Store::consume('job',$operation,$approval);
            UWNext_Store::log('cli_run','schedule_failed',$operation);
            return new WP_Error('job_unavailable','WordPress could not schedule this operation. Nothing executed; request a fresh approval before trying again.',array('status'=>503));
        }
        if(!UWNext_Store::log('cli_run','queued',$operation)) {
            UWNext_Store::consume('job',$operation,$approval);
            wp_clear_scheduled_hook('uwnext_job',array($operation));
            return new WP_Error('audit_unavailable','The queue receipt could not be stored. Nothing executed.',array('status'=>503));
        }
        return array('status'=>'queued','operation_id'=>$operation,'message'=>'WordPress cron will run this approved operation once. Poll operation_receipt; never resubmit an uncertain write.');
    }
    private static function active($connection,$user) {
        if(str_starts_with($connection,'app:')) {
            $parts=explode(':',$connection,3);
            return count($parts)===3&&(int)$parts[1]===$user&&(bool)WP_Application_Passwords::get_user_application_password($user,$parts[2]);
        }
        $grant=UWNext_Store::get('connection',$connection);
        return $grant&&!empty($grant['active'])&&(int)$grant['user']===$user;
    }
    public static function run($operation) {
        if(!is_string($operation)||!preg_match('/^[a-f0-9]{48}$/D',$operation)) { return; }
        $row=UWNext_Store::get('job',$operation);
        if(!$row||!UWNext_Store::consume('job',$operation,$row)) { return; }
        $previous_user=get_current_user_id();$previous_connection=UWNext_Auth::$connection;$locked=false;
        try {
            UWNext_Auth::$connection=(string)($row['connection']??'');
            $user=(int)($row['user']??0);wp_set_current_user($user);
            if(empty($row['approved'])||($row['site']??null)!==site_url()||($row['expires']??0)<=time()||!get_user_by('id',$user)||!self::active(UWNext_Auth::$connection,$user)) {
                UWNext_Store::log('cli_run','job_authority_expired',$operation);return;
            }
            $payload=UWNext_Store::decrypt($row['payload']);
            if(!is_array($payload)||!isset($payload['arguments'])||!hash_equals($row['digest'],UWNext_Store::digest('cli_run',$payload['arguments']))) {
                UWNext_Store::log('cli_run','job_payload_invalid',$operation);return;
            }
            // Register private descriptors in the ordinary cron request too.
            rest_get_server();
            $tool=UWNext_Tools::definitions()['cli_run'];$args=$payload['arguments'];
            $valid=UWNext_Tools::validate($tool,$args);
            if(is_wp_error($valid)||!current_user_can('manage_options')) { UWNext_Store::log('cli_run','denied',$operation);return; }
            $cli=new UWNext_CLI();$policy=$cli->uwnext_preview($args['command']);
            if(is_wp_error($policy)) { UWNext_Store::log('cli_run','denied',$operation);return; }
            $locked=add_option('uwnext_write_lock',$operation,'',false);
            if(!$locked) { UWNext_Store::log('cli_run','job_busy_not_started',$operation);return; }
            if(!UWNext_Store::log('cli_run','started',$operation)) { return; }
            $cli->set_detached(true);
            $out=isset($payload['preview']['operation'])
                ? $cli->run_approved($args['command'],!empty($args['confirm_write']),wp_json_encode($payload['preview']))
                : $cli->run($args['command'],!empty($args['confirm_write']));
            $failed=is_wp_error($out)||($out instanceof WP_REST_Response&&($out->get_status()>=400||!empty($out->get_data()['exit_code'])));
            UWNext_Store::log('cli_run',$failed?'failed':'completed',$operation);
        } catch(Throwable $error) {
            UWNext_Store::log('cli_run','host_failure',$operation);
        } finally {
            if($locked&&get_option('uwnext_write_lock')===$operation) {delete_option('uwnext_write_lock');}
            UWNext_Auth::$connection=$previous_connection;wp_set_current_user($previous_user);
        }
    }
}
add_action('uwnext_job',array('UWNext_Jobs','run'));
