<?php
/** Focused in-memory approval tests; no WordPress installation, network, or live writes. */
error_reporting(E_ALL);
set_error_handler(function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
define('ABSPATH',__DIR__.'/');define('UWNext_NS','unlimited-wp-mcp-next/v1');define('ARRAY_A','ARRAY_A');
$options=$hooks=$events=array();$uid=7;$cookie=7;$can=true;$app=false;$multi=false;$super=false;$site='https://fixture.test';$ran=0;$audit_ok=true;
class WP_Error {
    private $code,$message,$data;
    function __construct($c,$m,$d=null){$this->code=$c;$this->message=$m;$this->data=$d;}
    function get_error_code(){return $this->code;}function get_error_message(){return $this->message;}function get_error_data(){return $this->data;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function __($s,$d=null){return $s;}function esc_html__($s,$d=null){return esc_html($s);}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);}function esc_url($s){return esc_html($s);}
function add_filter($h,$cb,...$rest){global $hooks;$hooks[$h][]=$cb;}
function add_action($h,$cb,...$rest){add_filter($h,$cb);}
function get_option($k,$d=false){global $options;return $options[$k]??$d;}
function update_option($k,$v,...$rest){global $options,$hooks;foreach($hooks['sanitize_option_'.$k]??array() as $cb){$v=$cb($v);}$options[$k]=$v;return true;}
function add_option($k,$v,...$rest){global $options;if(array_key_exists($k,$options)){return false;}return update_option($k,$v);}
function delete_option($k){global $options;unset($options[$k]);return true;}
function current_user_can(...$args){global $can;return $can;}function is_user_logged_in(){return get_current_user_id()>0;}
function get_current_user_id(){global $uid;return $uid;}function wp_get_current_user(){return (object)array('user_login'=>'admin');}
function wp_validate_auth_cookie(...$args){global $cookie;return $cookie;}
function did_action($name){global $app;return $name==='application_password_did_authenticate'&&$app?1:0;}
function rest_get_authenticated_app_password(){global $app;return $app;}
function wp_verify_nonce($nonce,$action){return $nonce==='human-nonce';}function wp_unslash($s){return $s;}
function is_multisite(){global $multi;return $multi;}function is_super_admin(){global $super;return $super;}
function site_url(){global $site;return $site;}function admin_url($s){return site_url().'/wp-admin/'.$s;}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function rest_validate_value_from_schema(...$args){return true;}
class UWNext_Auth { public static $connection=''; }
class UWNext_Store {
    public static $rows=array();
    static function id(){return bin2hex(random_bytes(24));}
    static function get($k,$id){return self::$rows[$k][$id]??false;}
    static function put($k,$id,$v){if(isset(self::$rows[$k][$id])){return false;}self::$rows[$k][$id]=$v;return true;}
    static function delete($k,$id){unset(self::$rows[$k][$id]);return true;}
    static function consume($k,$id,$v){if(self::get($k,$id)!==$v){return false;}self::delete($k,$id);return true;}
    static function encrypt($v){return json_encode($v);}static function decrypt($v){return json_decode($v,true);}
    static function digest($tool,$args){return hash('sha256',json_encode(array(site_url(),$tool,$args)));}
    static function log($op,$status,$id=''){global $events,$audit_ok;$events[]=array($op,$status,$id);return $audit_ok;}
}
class UWNext_Audit_Log {static function log_execution($args){return UWNext_Store::log($args['operation'],'changed');}}
class WP_REST_Response {}
class WP_REST_Request implements ArrayAccess {
    private $params=array();function __construct(...$args){}function set_attributes($v){}
    function set_query_params($v){$this->params=$v;}function set_body_params($v){$this->params=$v;}
    function set_param($k,$v){$this->params[$k]=$v;}function has_valid_params(){return true;}function sanitize_params(){return true;}
    public function offsetExists(mixed $k):bool{return isset($this->params[$k]);}
    public function offsetGet(mixed $k):mixed{return $this->params[$k]??null;}
    public function offsetSet(mixed $k,mixed $v):void{$this->params[$k]=$v;}
    public function offsetUnset(mixed $k):void{unset($this->params[$k]);}
}
require dirname(__DIR__).'/includes/local-tools.php';
require dirname(__DIR__).'/includes/local-admin.php';
require dirname(__DIR__).'/includes/class-uwnext-approval-bypass.php';
require dirname(__DIR__).'/includes/local-sql-approvals.php';
$passed=0;
function check($ok,$label){global $passed;if(!$ok){throw new Exception('FAIL '.$label);}$passed++;echo "PASS $label\n";}
function code($v,$expected){return is_wp_error($v)&&$v->get_error_code()===$expected;}

if(($argv[1]??'')==='amelia') {
    class UWNext_Amelia {
        static function preview($request) {
            return $request['expected_state']==='fresh'?array('affected_bookings'=>array(array('booking_id'=>6,'customer_id'=>7))):new WP_Error('appointment_state_changed','Fresh preview required');
        }
    }
    $uid=7;UWNext_Auth::$connection='amelia-fixture';$can=true;$runs=0;
    uwnext_register_internal_route(UWNext_NS,'/amelia/reschedule-appointment',array('methods'=>'POST',
        'args'=>array('appointment_id'=>array('type'=>'integer','required'=>true),'expected_state'=>array('type'=>'string','required'=>true),'dry_run'=>array('type'=>'boolean','default'=>true)),
        'permission_callback'=>function(){return true;},'callback'=>function($request) use (&$runs) {
            if($request['dry_run']) { return array('preview'=>true); }
            if($request['expected_state']!=='fresh') { return new WP_Error('appointment_state_changed','Changed'); }
            $runs++;return array('appointment_update'=>'verified');
        }));
    $args=array('appointment_id'=>102,'expected_state'=>'fresh');
    $out=UWNext_Tools::call('amelia_reschedule_appointment',$args);
    check(($out['preview']??false)&&$runs===0,'Amelia defaults to non-mutating preview without approval');
    $args['dry_run']=false;$out=UWNext_Tools::call('amelia_reschedule_appointment',$args);
    check(code($out,'approval_required')&&$runs===0,'Amelia execution requests exact approval');
    $id=$out->get_error_data()['approval_id'];$row=UWNext_Store::get('approval',$id);$payload=UWNext_Store::decrypt($row['payload']);
    check(count($payload['preview']['affected_bookings'])===1,'Amelia approval includes affected bookings');
    check(UWNext_Admin::approve($id,7),'Fixture human approves Amelia operation');
    $other=$args;$other['appointment_id']=103;$other['approval_id']=$id;
    check(code(UWNext_Tools::call('amelia_reschedule_appointment',$other),'approval_invalid')&&$runs===0,'Amelia approval cannot change appointment');
    $args['approval_id']=$id;$out=UWNext_Tools::call('amelia_reschedule_appointment',$args);
    check(($out['appointment_update']??'')==='verified'&&($out['operation_id']??'')===$id&&$runs===1,'Approved Amelia write returns matching receipt');
    check(code(UWNext_Tools::call('amelia_reschedule_appointment',$args),'approval_invalid')&&$runs===1,'Amelia approval cannot replay');
    $stale=array('appointment_id'=>102,'expected_state'=>'stale','dry_run'=>false);
    check(code(UWNext_Tools::call('amelia_reschedule_appointment',$stale),'appointment_state_changed')&&$runs===1,'Stale Amelia preview cannot request approval');
    echo "RESULT $passed Amelia approval checks passed\n";exit;
}

if(($argv[1]??'')==='sql'){
    require dirname(__DIR__).'/includes/class-uwnext-error-contract.php';
    require dirname(__DIR__).'/includes/class-uwnext-cli.php';
    function esc_sql($s){return addslashes($s);}
    class wpdb {function db_connect(){return true;}function check_connection($allow_bail=false){return true;}}
    class Test_DB extends wpdb {
        public $prefix='wp_',$posts='wp_posts',$postmeta='wp_postmeta',$term_relationships='wp_term_relationships',$term_taxonomy='wp_term_taxonomy',$terms='wp_terms',$last_error='';
        public $indirect=0,$innodb=5,$loose=array(),$schemas=array(),$queries=array(),$fingerprint_changed=false;
        function suppress_errors($v){return false;}
        function get_var($q){return str_contains($q,'TRIGGERS')?$this->indirect:$this->innodb;}
        function get_col($q){return str_contains($q,'SCHEMATA')?$this->schemas:$this->loose;}
        function get_results($q,$mode){return $this->fingerprint_changed?array(array('ID'=>1)):array();}
        function query($q){$this->queries[]=$q;if(str_starts_with($q,'UPDATE')){$this->fingerprint_changed=true;}return 1;}
    }
    $wpdb=new Test_DB();
    function method($obj,$name,...$args){return (new ReflectionMethod($obj,$name))->invoke($obj,...$args);}
    foreach(array('UPDATE','INSERT','DELETE','REPLACE') as $verb){check(method(new UWNext_CLI(),'sql_session_rememberable',"$verb wp_posts SET post_title='safe'",$verb),"native $verb row writes rememberable on eligible database");}
    foreach(array('ALTER','DROP','TRUNCATE','CREATE') as $verb){check(!method(new UWNext_CLI(),'sql_session_rememberable',"$verb wp_posts",$verb),"native $verb never remembered");}
    foreach(array('wp_snippets','wp_hfcm_scripts','wp_wow_coder','wp_uwnext_events','wp_woocommerce_api_keys') as $table){check(!method(new UWNext_CLI(),'sql_session_rememberable',"UPDATE $table SET value=1",'UPDATE'),"native protected $table never remembered");}
    $multi=true;check(!method(new UWNext_CLI(),'sql_session_rememberable','UPDATE wp_posts SET ID=1','UPDATE'),'native multisite restriction retained');$multi=false;
    $wpdb->indirect=1;check(!method(new UWNext_CLI(),'sql_session_rememberable','UPDATE wp_posts SET ID=1','UPDATE'),'native triggers/views/routines restriction retained');$wpdb->indirect=0;
    $wpdb->innodb=4;check(!method(new UWNext_CLI(),'sql_session_rememberable','UPDATE wp_posts SET ID=1','UPDATE'),'native transactional post/term table requirement retained');$wpdb->innodb=5;
    $wpdb->loose=array('wp_posts');check(!method(new UWNext_CLI(),'sql_session_rememberable','UPDATE wp_posts SET ID=1','UPDATE'),'native nontransactional table restriction retained');$wpdb->loose=array();
    $wpdb->schemas=array('other');check(!method(new UWNext_CLI(),'sql_session_rememberable','UPDATE other.wp_posts SET ID=1','UPDATE'),'native cross-schema restriction retained');
    class Custom_DB extends Test_DB {function db_connect(){return true;}}
    $wpdb=new Custom_DB();check(!method(new UWNext_CLI(),'sql_session_rememberable','UPDATE wp_posts SET ID=1','UPDATE'),'native custom database connection restriction retained');$wpdb=new Test_DB();
    check(code(method(new UWNext_CLI(),'db_query_approval_scope',array('operation'=>'db_query_alter','dry_run'=>array('sql'=>'ALTER TABLE wp_posts ADD x INT'))),'sql_needs_own_approval'),'native schema changes require exact SQL snapshot');
    check(method(new UWNext_CLI(),'db_query_approval_scope',array('operation'=>'db_query_update:sql','dry_run'=>array('sql'=>'UPDATE wp_posts SET ID=1')))===null,'native eligible remembered SQL enters guarded transaction path');
    $out=method(new UWNext_CLI(),'db_query_session_transaction','UPDATE wp_posts SET post_title=\'code\'');
    check(in_array('ROLLBACK',$wpdb->queries,true)&&!in_array('COMMIT',$wpdb->queries,true)&&!empty($out['exit_code']),'native code-storage change rolls back remembered SQL');
    echo "RESULT $passed native SQL checks passed\n";exit;
}

// Gate tests isolate the existing CLI dispatcher from WordPress data; native SQL restrictions run above.
if(($argv[1]??'')!=='sql') { class UWNext_CLI {
    public function uwnext_preview($command){
        if(str_contains($command,'uwnext_')){return new WP_Error('protected_state','Protected state');}
        return str_starts_with($command,'db ')?array('operation'=>str_contains($command,'ALTER')?'db_query_alter':'db_query_update:sql','dry_run'=>array('sql'=>$command)):null;
    }
    public function run_approved(...$args){global $ran;$ran++;return array('ok'=>true);}
    public function run(...$args){global $ran;$ran++;return array('ok'=>true);}
} }
UWNext_Approval_Bypass::instance();
check(!UWNext_Approval_Bypass::is_on()&&UWNext_Approval_Bypass::details()===null,'bypass defaults OFF');
check(!UWNext_SQL_Approvals::available(array('operation'=>'db_query_update:sql')),'remembered SQL defaults OFF without human opt-in');
$post=array('_wpnonce'=>'human-nonce','enabled'=>1);
$uid=0;check(is_wp_error(UWNext_Approval_Bypass::save_from_request($post)),'logged-out user cannot change bypass');$uid=7;
$can=false;check(is_wp_error(UWNext_Approval_Bypass::save_from_request($post)),'non-admin cannot change bypass');$can=true;
check(is_wp_error(UWNext_Approval_Bypass::save_from_request(array('enabled'=>1))),'administrator still needs valid nonce');
$cookie=0;check(is_wp_error(UWNext_Approval_Bypass::save_from_request($post)),'administrator without login cookie rejected');$cookie=7;
$app=true;check(is_wp_error(UWNext_Approval_Bypass::save_from_request($post)),'application-password administrator rejected');$app=false;
UWNext_Auth::$connection='oauth:test';check(is_wp_error(UWNext_Approval_Bypass::save_from_request($post)),'Unlimited OAuth administrator cannot change bypass');UWNext_Auth::$connection='';
update_option(UWNext_Approval_Bypass::OPTION,array('enabled'=>true));check(!UWNext_Approval_Bypass::is_on(),'generic option writer cannot enable bypass');
$multi=true;check(is_wp_error(UWNext_Approval_Bypass::save_from_request($post)),'multisite subsite admin cannot enable bypass');$multi=false;
check(true===UWNext_Approval_Bypass::save_from_request($post)&&UWNext_Approval_Bypass::is_on(),'cookie-authenticated administrator can enable bypass');
ob_start();UWNext_Approval_Bypass::render_notice();$banner=ob_get_clean();check(str_contains($banner,'bypassing approvals')&&str_contains($banner,'admin')&&str_contains($banner,'Turn it off'),'enabled bypass displays warning, enabler and disable link');
update_option(UWNext_Approval_Bypass::OPTION,'');check(UWNext_Approval_Bypass::is_on(),'generic writer cannot disable administrator setting');
UWNext_Auth::$connection='oauth:test';check(is_wp_error(UWNext_Approval_Bypass::save_from_request(array('_wpnonce'=>'human-nonce'))),'OAuth cannot disable bypass either');UWNext_Auth::$connection='';
check(true===UWNext_Approval_Bypass::save_from_request(array('_wpnonce'=>'human-nonce'))&&!UWNext_Approval_Bypass::is_on(),'cookie administrator can disable bypass');
$def=array('args'=>array('value'=>array('type'=>'string')),'permission_callback'=>function(){return true;},'callback'=>function($r){global $ran;$ran++;return array('ok'=>true);});
UWNext_Tools::$routes=array('/code-snippet'=>array('POST'=>$def),'/content/edit'=>array('POST'=>$def),'/cli/run'=>array('POST'=>array_replace($def,array('args'=>array('command'=>array('type'=>'string'),'confirm_write'=>array('type'=>'boolean','default'=>false),'detach'=>array('type'=>'boolean','default'=>false))))));
UWNext_Auth::$connection='oauth:one';
$first=UWNext_Tools::call('code_snippet',array('value'=>'first'));check(code($first,'approval_required')&&$ran===0,'normal exact approval is required before execution');$id=$first->get_error_data()['approval_id'];
check(UWNext_Admin::approve($id,7),'ordinary approval can be granted');
check(code(UWNext_Tools::call('code_snippet',array('value'=>'changed','approval_id'=>$id)),'approval_invalid'),'ordinary approval cannot change arguments');
UWNext_Auth::$connection='oauth:two';check(code(UWNext_Tools::call('code_snippet',array('value'=>'first','approval_id'=>$id)),'approval_invalid'),'ordinary approval stays connection-bound');UWNext_Auth::$connection='oauth:one';
check(!is_wp_error(UWNext_Tools::call('code_snippet',array('value'=>'first','approval_id'=>$id)))&&$ran===1,'matching exact-operation approval executes once');
check(code(UWNext_Tools::call('code_snippet',array('value'=>'first','approval_id'=>$id)),'approval_invalid'),'spent approval cannot replay');
check(code(UWNext_Tools::call('cli_run',array('command'=>'option update uwnext_bypass_approvals 1')),'protected_state'),'MCP CLI cannot enable bypass');
UWNext_Auth::$connection='';UWNext_Approval_Bypass::save_from_request($post);UWNext_Auth::$connection='oauth:one';
$before=$ran;$out=UWNext_Tools::call('code_snippet',array('value'=>'bypassed'));
check(!is_wp_error($out)&&$ran===$before+1,'enabled bypass executes baseline code-snippet route');
check(in_array(array('code_snippet','bypassed',$events[count($events)-3][2]),$events,true),'bypassed operation has explicit audit marker');
check(!UWNext_Approval_Bypass::$request_bypassed&&!UWNext_Tools::executing(),'bypass execution context resets after call');
UWNext_Approval_Bypass::$request_bypassed=true;
check(is_wp_error(UWNext_Approval_Bypass::allows('white_label_enable')),'baseline white-label enabling always asks under bypass');
UWNext_Approval_Bypass::$request_bypassed=false;
check(code(UWNext_Tools::call('content_edit',array('value'=>'safe')),'approval_required'),'bypass does not widen beyond baseline proof routes');
check(code(UWNext_Tools::call('cli_run',array('command'=>'db query ALTER TABLE wp_posts ADD x INT')),'approval_required'),'schema SQL still asks even with bypass enabled');
$audit_ok=false;$before=$ran;check(code(UWNext_Tools::call('code_snippet',array('value'=>'audit-failure')),'audit_unavailable')&&$ran===$before,'audit failure prevents bypass execution');$audit_ok=true;
$def['callback']=function($r){return UWNext_Approval_Bypass::save_from_request(array('_wpnonce'=>'human-nonce','enabled'=>1));};
UWNext_Tools::$routes['/code-snippet']['POST']=$def;
check(is_wp_error(UWNext_Tools::call('code_snippet',array('value'=>'attempt'))),'MCP callback cannot impersonate browser setting change');
UWNext_Auth::$connection='';UWNext_Approval_Bypass::save_from_request(array('_wpnonce'=>'human-nonce'));UWNext_Auth::$connection='oauth:one';
$sql=UWNext_Tools::call('cli_run',array('command'=>'db query UPDATE wp_posts SET ID=1'));$sqlid=$sql->get_error_data()['approval_id'];
UWNext_Auth::$connection='';
check(UWNext_Admin::approve($sqlid,7,true),'human can opt into eligible remembered SQL');
UWNext_Auth::$connection='oauth:one';
$preview=array('operation'=>'db_query_update:sql');
check(UWNext_SQL_Approvals::available($preview),'remembered SQL is available to approving connection');
UWNext_Auth::$connection='oauth:two';check(!UWNext_SQL_Approvals::available($preview),'remembered SQL cannot cross connections');UWNext_Auth::$connection='oauth:one';
$site='https://other.test';check(!UWNext_SQL_Approvals::available($preview),'remembered SQL cannot cross sites');$site='https://fixture.test';
$uid=8;check(!UWNext_SQL_Approvals::available($preview),'remembered SQL cannot cross users');$uid=7;
check(!UWNext_SQL_Approvals::available(array('operation'=>'db_query_alter')),'session never covers schema/other operation keys');
$before=$ran;check(!is_wp_error(UWNext_Tools::call('cli_run',array('command'=>'db query UPDATE wp_posts SET ID=2')))&&$ran===$before+1,'eligible SQL uses remembered scope without another prompt');
for($i=0;$i<23;$i++){check(UWNext_SQL_Approvals::take($preview),'consume remaining SQL statement '.($i+1));}
check(!UWNext_SQL_Approvals::take($preview),'25 statements including original approval exhaust session');
$row=UWNext_Store::get('approval',$sqlid);
check(!UWNext_SQL_Approvals::remember($row),'MCP connection cannot grant remembered SQL to itself');
UWNext_Auth::$connection='';UWNext_SQL_Approvals::remember($row);UWNext_Auth::$connection='oauth:one';
foreach(UWNext_Store::$rows['sql_session'] as &$session){$session['expires']=time()-1;}unset($session);
check(!UWNext_SQL_Approvals::available($preview)&&!UWNext_SQL_Approvals::take($preview),'30-minute expired session cannot execute');
check(UWNext_SQL_Approvals::TTL===1800&&UWNext_SQL_Approvals::STATEMENTS===25,'GPL documented lifetime and statement cap retained');
echo "RESULT $passed approval checks passed\n";
