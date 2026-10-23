<?php
// All filesystem mutations stay inside the disposable .runtime WordPress fixture.
$theme_dir=WP_CONTENT_DIR.'/themes/fixture-classic';wp_mkdir_p($theme_dir);
file_put_contents($theme_dir.'/style.css',"/*\nTheme Name: Fixture Classic\nVersion: 1.0\n*/\n");
file_put_contents($theme_dir.'/index.php','<?php echo "fixture";');
file_put_contents($theme_dir.'/functions.php','<?php // fixture');
wp_clean_themes_cache();switch_theme('fixture-classic');
function approved_tool($name,$args=array()) {
    global $admin;
    $first=data(tool($name,$args));
    if(($first['code']??'')!=='approval_required'){return $first;}
    $id=$first['data']['approval_id'];UWNext_Admin::approve($id,$admin->ID);
    return data(tool($name,$args+array('approval_id'=>$id)));
}
$draft=approved_tool('create_draft_theme');
check(!isset($draft['code']) && !!get_option('uwnext_draft_theme'),'draft theme creation');
$bad=approved_tool('file_write',array('path'=>'broken.php','content'=>'<?php function bad( {'));
check(isset($bad['code']) && !file_exists(get_theme_root().'/'.get_option('uwnext_draft_theme').'/broken.php'),'actual file write rejects invalid PHP');
$good=approved_tool('file_write',array('path'=>'fixture.txt','content'=>'isolated draft content'));
check(!isset($good['code']),'approved draft file write');
$svg=approved_tool('file_write',array('path'=>'fixture.svg','content'=>'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="10" height="10"/></svg>'));
$svg_file=data(tool('file_read',array('path'=>'fixture.svg')));
check(!isset($svg['code'])&&!isset($svg_file['code'])&&!str_contains($svg_file['content']??'','<script')&&str_contains($svg_file['content']??'','<rect'),'new SVG support preserves sanitization under bounded atomic writes');
$file=data(tool('file_read',array('path'=>'fixture.txt')));
check(!isset($file['code']) && str_contains(wp_json_encode($file),'isolated draft content'),'draft file read');
$escape=approved_tool('file_write',array('path'=>'../escape.php','content'=>'<?php // no'));
check(isset($escape['code']),'approved write still rejects sandbox escape');
require __DIR__.'/file-mutations.php';
require __DIR__.'/file-delete.php';
$deleted=approved_tool('file_delete',array('path'=>'fixture.txt'));check(!isset($deleted['code']),'approved draft file deletion');
$preview=data(tool('draft_theme_preview'));
check(!isset($preview['code']),'draft preview URL');
$ptoken=uwnext_preview_token();check($ptoken && get_option('uwnext_preview_token')!==$ptoken,'preview token encrypted at rest');
$pending=data(tool('draft_theme_publish'));$pid=$pending['data']['approval_id'];UWNext_Admin::approve($pid,$admin->ID);
file_put_contents(get_theme_root().'/'.get_option('uwnext_draft_theme').'/changed.txt','changed after approval');
$drift=data(tool('draft_theme_publish',array('approval_id'=>$pid)));
check(($drift['code']??'')==='approval_state_changed','publish binds the complete draft file snapshot');
UWNext_Change_Tracker::mark(array('summary'=>'secret-value-not-to-log','url'=>home_url('/?uwnext_preview=private-preview-token')));
$activity=wp_json_encode(get_option('uwnext_recent_activity'));check(!str_contains($activity,'secret-value-not-to-log') && !str_contains($activity,'private-preview-token'),'change tracking excludes supplied content and preview credentials');
$published=approved_tool('draft_theme_publish');
check(($published['code']??'')==='publish_rolled_back' && !!get_option('uwnext_draft_theme'),'publish rolls back when host render check fails');
$deleted=approved_tool('delete_draft_theme');check(!isset($deleted['code']) && !get_option('uwnext_draft_theme'),'approved draft cleanup');
// Dynamic abilities use the owning plugin's core permission checks.
if(function_exists('wp_get_abilities')) { check(!empty(data(tool('abilities'))['abilities']),'WordPress Abilities API listing'); }
