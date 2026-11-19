// Public-name evidence: WPVibe 1.20.5 README at 62bc77c; argument evidence:
// that commit's PHP REST descriptors. Hosted MCP descriptions/schemas are absent.
// Do not regenerate this contract from a current/later WPVibe service catalog.
import { UPSTREAM_TOOLS } from './upstream-tools.generated.js';
import { Denied } from './security.js';
import { BASELINE_ROUTES } from './baseline-routes.generated.js';
const internal=new Map(UPSTREAM_TOOLS.map(t=>[t.name,t]));
const string={type:'string'};
const object={type:'object',additionalProperties:true};
const definitions=[
  ['site_info','site_info','Get metadata about a connected WordPress site.'],
  ['discover_abilities','abilities','Discover abilities registered by plugins on a connected WordPress site.'],
  ['get_ability_info','ability_info','Get the input schema, output schema and details of a WordPress ability.'],
  ['run_ability','ability_run','Execute a registered WordPress ability using its input schema and permission checks.'],
  ['read_file','file_read','Read a theme file or inspect wp-content source and logs read-only.'],
  ['write_file','file_write','Create or overwrite a file in the draft theme.'],
  ['edit_file','file_edit','Replace exact text in a draft theme file.'],
  ['delete_file','file_delete','Delete a file from the draft theme.'],
  ['list_files','file_list','List theme files or read-only wp-content source and log files.'],
  ['search_files','file_search','Search draft theme file contents for matching lines.'],
  ['get_file_outline','file_outline','Get a structural outline of a theme file with line numbers.'],
  ['create_draft_theme','create_draft_theme','Clone the active theme into a draft for safe editing.'],
  ['publish_draft_theme','draft_theme_publish','Publish the draft theme to the live site after reviewing the preview.'],
  ['delete_draft_theme','delete_draft_theme','Delete the draft theme and discard its changes.'],
  ['get_preview_url','draft_theme_preview','Get a tokenized preview URL for an existing draft theme.'],
  ['create_classic_theme','create_classic_theme_safe','Create a classic theme as a draft.'],
  ['run_wp_cli','cli_run','Run a WP-CLI command through native WordPress PHP APIs, without a shell.'],
  ['rest_api','rest_api_write','Call a WordPress REST API endpoint using GET, POST, PUT or DELETE.'],
  ['get_page_html','rendered_html','Get page HTML. The self-hosted adapter supports server-rendered HTML; hosted JavaScript rendering and CSS selectors are unavailable.'],
  ['navigate','navigate','Navigate to a URL on the connected WordPress site.'],
  ['upload_media','upload_media','Upload an image from a URL to the WordPress media library.'],
  ['search_images','search_images','Search stock images with attribution through the independent image provider.'],
  ['load_skill','load_skill','Load locally maintained WordPress workflow guidance.'],
  // Present as native handlers in the pinned plugin, although omitted from its README table.
  ['code_snippet','code_snippet','Create, update, deactivate or delete a WPCode code snippet.'],
  // Retained Unlimited extension; never renamed or removed by compatibility.
  ['operation_receipt','operation_receipt','Read an operation receipt belonging to the connected user and connection.'],
];
const targets=new Map(definitions.map(([name,target])=>[name,target]));
export const COMPATIBILITY_TARGETS=Object.fromEntries(targets);
export const baselinePaths={site_info:'/site-info',file_read:'/file/read',file_write:'/file/write',file_edit:'/file/edit',file_delete:'/file/delete',file_list:'/file/list',file_search:'/file/search',file_outline:'/file/outline',create_draft_theme:'/draft-theme',draft_theme_publish:'/draft-theme/publish',delete_draft_theme:'/draft-theme/delete',draft_theme_preview:'/draft-theme/preview',create_classic_theme_safe:'/create-classic-theme-safe',cli_run:'/cli/run',rendered_html:'/rendered-html',navigate:'/navigate',upload_media:'/upload-media',code_snippet:'/code-snippet'};
export function compatibilityCatalog() {
  return definitions.map(([name,target,description])=>{
    const base=internal.get(target),schema=structuredClone(BASELINE_ROUTES[baselinePaths[target]]?.inputSchema||base.inputSchema),props=schema.properties;
    // The pinned draft-theme route has both GET/POST declarations; cloning has no arguments.
    if(name==='create_draft_theme') {schema.properties={};schema.required=[];}
    for(const key of ['approval_id','detach']) delete props[key];
    schema.required=schema.required.filter(k=>Object.hasOwn(props,k));
    if(name==='rest_api') {
      schema.properties={method:{type:'string',enum:['GET','POST','PUT','DELETE']},path:string,parameters:object};
      schema.required=['method','path'];
    }
    if(name==='search_images') {
      schema.properties={query:{type:'string',minLength:1,maxLength:200},count:{type:'integer',minimum:1,maximum:10,default:10},orientation:{type:'string',enum:['landscape','portrait','squarish']}};
      schema.required=['query'];
    }
    if(name==='load_skill') {
      schema.properties={name:{type:'string',minLength:1,maxLength:100},query:{type:'string',minLength:1,maxLength:1000},resource:{type:'string',minLength:1,maxLength:100}};
      schema.required=[];
    }
    // Hosted schemas are absent from the GPL source. Resource adapters above use
    // the requested shapes; unverifiable historical fields are documented separately.
    // No selected-site state: site_url is the public target, resolved against this user's registry.
    schema.properties.site_url={type:'string',description:'WordPress site URL'};
    if(!['search_images','load_skill'].includes(name))schema.required.push('site_url');
    return {name,description,inputSchema:schema,annotations:base.annotations};
  });
}
const catalog=new Map(compatibilityCatalog().map(t=>[t.name,t]));
function validate(value,schema,path='arguments') {
  if(value===null)throw new Denied(path+' cannot be null.');
  const type=schema.type;
  if(type==='string'&&typeof value!=='string'||type==='boolean'&&typeof value!=='boolean'||type==='number'&&(typeof value!=='number'||!Number.isFinite(value))||type==='integer'&&!Number.isInteger(value)||type==='array'&&!Array.isArray(value)||type==='object'&&(typeof value!=='object'||Array.isArray(value)))throw new Denied('Invalid '+path+'.');
  if(schema.enum&&!schema.enum.includes(value))throw new Denied('Invalid '+path+' enum.');
  if(schema.pattern&&typeof value==='string'&&!new RegExp(schema.pattern).test(value))throw new Denied('Invalid '+path+' pattern.');
  if(typeof value==='number'&&(schema.minimum!==undefined&&value<schema.minimum||schema.maximum!==undefined&&value>schema.maximum))throw new Denied('Out of range '+path+'.');
  if(typeof value==='string'&&(schema.minLength!==undefined&&value.length<schema.minLength||schema.maxLength!==undefined&&value.length>schema.maxLength))throw new Denied('Invalid length '+path+'.');
  if(type==='object') {
    for(const k of schema.required||[])if(!Object.hasOwn(value,k))throw new Denied('Missing '+path+'.'+k);
    for(const [k,v]of Object.entries(value)){const child=schema.properties&&Object.hasOwn(schema.properties,k)?schema.properties[k]:undefined;if(child)validate(v,child,path+'.'+k);else if(schema.additionalProperties===false)throw new Denied('Unknown '+path+'.'+k);}
  }
  if(type==='array'&&schema.items)for(const v of value)validate(v,schema.items,path+'[]');
}
const privateRoutes=new Map([
  ['/site-info','site_info'],['/abilities','abilities'],['/ability/info','ability_info'],['/ability/run','ability_run'],
  ['/content/search','content_search'],['/content/edit','content_edit'],['/registered-meta','registered_meta'],
  ['/code-snippet','code_snippet'],['/code-snippet/dormant','code_snippet_dormant'],
  ['/elementor/save-page','elementor_save_page'],['/elementor/save-template','elementor_save_template'],
  ['/elementor/widgets','elementor_widgets'],['/elementor/schema','elementor_schema'],['/elementor/style-schema','elementor_style_schema'],
  ['/beaver/modules','beaver_modules'],['/beaver/schema','beaver_schema'],['/beaver/save-page','beaver_save_page'],
  ['/breakdance/elements','breakdance_elements'],['/breakdance/get-page','breakdance_get_page'],['/breakdance/save-page','breakdance_save_page'],
  ['/bricks/elements','bricks_elements'],['/bricks/get-page','bricks_get_page'],['/bricks/save-page','bricks_save_page'],
  ['/classic-theme-safety','classic_theme_safety'],['/draft-theme/compile-sources','draft_theme_compile_sources'],
  ['/audit-log','audit_log'],['/cli/status','cli_status'],['/builder-login','builder_login'],
  ['/create-classic-theme','create_classic_theme'],['/last-change','last_change'],
  ...Object.entries(baselinePaths).filter(([name])=>!['create_draft_theme','delete_draft_theme'].includes(name)).map(([name,path])=>[path,name]),
]);
/** Legacy aliases remain callable with `site`; compatibility names use the public site_url shape. */
export function translate(name,input) {
  if(!catalog.has(name)||input&&Object.hasOwn(input,'site'))return null;
  const contract=catalog.get(name);validate(input,contract.inputSchema);
  const {site_url,...args}=input;let target=targets.get(name);
  if(name==='rest_api') {
    if(!args.path.startsWith('/')||args.path.startsWith('//')||args.path.includes('://')||args.path.includes('#')||/[\\\x00-\x20]/.test(args.path))throw new Denied('Use a relative WordPress REST path.');
    const url=new URL(args.path,'https://route.invalid');
    const path=url.pathname.replace(/^\/wp-json(?=\/|$)/,'')||'/';
    const parameters={...Object.fromEntries(url.searchParams),...(args.parameters||{})};
    const own=path.match(/^\/(?:wpvibe|unlimited-wp-mcp-next)\/v1(\/.*)$/);
    const privateTarget=own&&(own[1]==='/draft-theme'?(args.method==='DELETE'?'delete_draft_theme':args.method==='POST'?'create_draft_theme':null):privateRoutes.get(own[1]));
    if(privateTarget) {
      const read=internal.get(privateTarget).annotations?.readOnlyHint===true;
      const expected=own[1]==='/draft-theme'?args.method:BASELINE_ROUTES[own[1]]?.method || (read?'GET':'POST');
      if(args.method!==expected)throw new Denied('REST method does not match this plugin handler.');
      target=privateTarget;Object.keys(args).forEach(k=>delete args[k]);
      for(const [k,v]of Object.entries(parameters))Object.defineProperty(args,k,{value:v,enumerable:true,writable:true,configurable:true});
    }else {
      // Keep Unlimited's existing approval rule for extension GETs that can mutate.
      target=args.method==='GET'&&path.startsWith('/wp/v2/')?'rest_api_read':'rest_api_write';
      const method=args.method;Object.keys(args).forEach(k=>delete args[k]);Object.assign(args,{path,parameters,...(target==='rest_api_read'?{}:{method})});
    }
  }
  return {name:target,args,site_url,publicName:name,contract};
}
