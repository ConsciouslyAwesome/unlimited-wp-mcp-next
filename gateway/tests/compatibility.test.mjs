import {DEFAULT_SITES,seed} from './registry-fixtures.mjs';
import assert from 'node:assert/strict';
import {test,afterEach} from 'node:test';
import {DatabaseSync} from 'node:sqlite';
import {readFileSync} from 'node:fs';
import gateway from '../src/index.js';
import {compatibilityCatalog,translate,baselinePaths} from '../src/compatibility.js';
import {BASELINE_ROUTES} from '../src/baseline-routes.generated.js';
import {saveTokens} from '../src/store.js';

const originalFetch=globalThis.fetch,user='fixture-owner',origin='https://gateway.example.com';
const [A,B]=DEFAULT_SITES,approval='a'.repeat(48);
afterEach(()=>{globalThis.fetch=originalFetch;});
async function environment(){
  const sql=new DatabaseSync(':memory:');
  for(const file of ['0000_multisite.sql','0001_interface_compat.sql','0002_connection_observations.sql'])sql.exec(readFileSync(new URL('../drizzle/'+file,import.meta.url),'utf8'));
  const DB={sql,prepare(query){return{bind(...args){return {async first(){return sql.prepare(query).get(...args)||null;},async all(){return{results:sql.prepare(query).all(...args)};},async run(){return{meta:{changes:Number(sql.prepare(query).run(...args).changes)}};}};}};},async batch(statements){sql.exec('BEGIN');try{const results=[];for(const s of statements)results.push(await s.run());sql.exec('COMMIT');return results;}catch(e){sql.exec('ROLLBACK');throw e;}}};
  const env={DB,OAUTH_ENCRYPTION_KEY:Buffer.alloc(32,19).toString('base64')};await seed(env,user);
  for(const s of [A,B])await saveTokens(env,user,s,'client-'+s.alias,{access_token:'access-'+s.alias,refresh_token:'refresh-'+s.alias,expires_in:3600});
  return env;
}
async function rpc(env,name,args,who=user){const r=await gateway.fetch(new Request(origin+'/mcp',{method:'POST',headers:{'content-type':'application/json',...(who?{'oai-authenticated-user-id':who}:{})},body:JSON.stringify({jsonrpc:'2.0',id:1,method:'tools/call',params:{name,arguments:args}})}),env);return r.json();}
function response(body){return new Response(JSON.stringify(body),{headers:{'content-type':'application/json'}});}
function success(){return response({jsonrpc:'2.0',id:1,result:{content:[{type:'text',text:'{"unchanged_native_output":true}'}],isError:false}});}
function required(){return response({result:{isError:true,content:[{type:'text',text:JSON.stringify({code:'approval_required',message:'Review',data:{approval_id:approval,expires:Math.floor(Date.now()/1000)+600,approval_url:A.canonical_url+'/wp-admin/admin.php?page=unlimited-wp-mcp-next&approval='+approval}})}]}});}

test('catalog contains all 26 GPL README names, native code_snippet and retained receipts',async()=>{
  const documented=['connect_site','site_info','list_sites','remove_site','rest_api','upload_media','search_images','discover_abilities','get_ability_info','run_ability','read_file','edit_file','write_file','delete_file','list_files','search_files','get_file_outline','create_draft_theme','publish_draft_theme','delete_draft_theme','get_preview_url','create_classic_theme','run_wp_cli','get_page_html','navigate','load_skill'];
  const env=await environment();const r=await gateway.fetch(new Request(origin+'/mcp',{method:'POST',body:JSON.stringify({jsonrpc:'2.0',id:1,method:'tools/list'})}),env);
  const {result:{tools}}=await r.json();assert.equal(tools.length,32);assert.equal(new Set(tools.map(t=>t.name)).size,32);
  for(const name of documented)assert.ok(tools.some(t=>t.name===name),name);
  for(const name of ['cli_run','file_read','rest_api_read','rest_api_write','ability_info','draft_theme_publish','show_fleet_dashboard','start_fleet_job','use_usage_reset'])assert.ok(!tools.some(t=>t.name===name),name);
  for(const t of compatibilityCatalog()){assert.equal(t.inputSchema.properties.site,undefined);assert.equal(t.inputSchema.properties.approval_id,undefined);}
});
test('source-backed native argument types, defaults, enums and required fields are unchanged',()=>{
  const catalog=compatibilityCatalog();
  for(const t of catalog){
    const mapped=translate(t.name,Object.fromEntries([...t.inputSchema.required].map(k=>[k,k==='site_url'?A.canonical_url:k==='operation_id'?'a'.repeat(48):t.inputSchema.properties[k].enum?.[0]??(t.inputSchema.properties[k].type==='object'?{}:t.name==='rest_api'&&k==='path'?'/wp/v2/posts':'value')])));
    const path=baselinePaths[mapped.name];if(!path||t.name==='create_draft_theme')continue;
    const expected=structuredClone(BASELINE_ROUTES[path].inputSchema);expected.properties.site_url={type:'string',description:'WordPress site URL'};expected.required.push('site_url');
    assert.deepEqual(t.inputSchema,expected,t.name);
  }
});
test('no later file pagination or speculative hosted wrapper fields are advertised',()=>{
  const get=n=>compatibilityCatalog().find(t=>t.name===n).inputSchema.properties;
  assert.equal(get('list_files').offset,undefined);assert.equal(get('run_ability').input_json,undefined);
  assert.equal(get('get_page_html').selector,undefined);assert.equal(get('load_skill').resource.type,'string');
  assert.equal(get('search_files').case_sensitive.default,false);assert.equal(get('search_files').max_results.default,100);
  assert.deepEqual(get('publish_draft_theme').saved_customizations.enum,['set_aside','keep']);
});
for(const [external,internal,args]of [
  ['run_wp_cli','cli_run',{command:'plugin list',confirm_write:false}],['read_file','file_read',{path:'style.css',start_line:1,end_line:5}],
  ['write_file','file_write',{path:'style.css',content:'body{}'}],['edit_file','file_edit',{path:'style.css',old_content:'old',new_content:'new'}],
  ['delete_file','file_delete',{path:'unused.css'}],['discover_abilities','abilities',{}],['get_ability_info','ability_info',{name:'test/read'}],
  ['run_ability','ability_run',{name:'test/write',input:{id:7}}],['get_preview_url','draft_theme_preview',{}],['publish_draft_theme','draft_theme_publish',{saved_customizations:'keep'}],
])test(external+' translates only its name and site envelope',()=>{
  const x=translate(external,{site_url:A.canonical_url,...args});assert.equal(x.name,internal);assert.deepEqual(x.args,args);
});
test('REST split is consolidated and plugin namespace paths route to private native descriptors',()=>{
  let x=translate('rest_api',{site_url:A.canonical_url,method:'GET',path:'/wp-json/wp/v2/posts?per_page=3',parameters:{status:'draft'}});
  assert.equal(x.name,'rest_api_read');assert.deepEqual(x.args,{path:'/wp/v2/posts',parameters:{per_page:'3',status:'draft'}});
  x=translate('rest_api',{site_url:A.canonical_url,method:'PUT',path:'/wp/v2/posts/7',parameters:{title:'Changed'}});assert.equal(x.name,'rest_api_write');assert.equal(x.args.method,'PUT');
  x=translate('rest_api',{site_url:A.canonical_url,method:'GET',path:'/extension/v1/mutate'});assert.equal(x.name,'rest_api_write');assert.equal(x.args.method,'GET');
  x=translate('rest_api',{site_url:A.canonical_url,method:'POST',path:'/wpvibe/v1/content/edit',parameters:{target_type:'post',post_id:7,old_content:'old',new_content:'new'}});assert.equal(x.name,'content_edit');assert.equal(x.args.post_id,7);
  assert.throws(()=>translate('rest_api',{site_url:A.canonical_url,method:'GET',path:'/wpvibe/v1/content/edit'}),/method/);
});
test('invalid shapes and speculative fields are rejected',()=>{
  assert.throws(()=>translate('run_wp_cli',{site_url:A.canonical_url}),/Missing/);
  assert.throws(()=>translate('run_ability',{site_url:A.canonical_url,name:'test',input:'{}'}),/Invalid/);
  assert.throws(()=>translate('read_file',{site_url:A.canonical_url,path:'style.css',offset:5}),/Unknown/);
  assert.throws(()=>translate('rest_api',{site_url:A.canonical_url,path:'https://evil.example.com',method:'GET'}),/relative/);
  assert.throws(()=>translate('rest_api',{site_url:A.canonical_url,path:'/wp/v2/posts',method:'PATCH'}),/enum/);
});
test('public site_url resolves only an existing user-owned registered site and preserves output',async()=>{
  const env=await environment();let count=0;
  globalThis.fetch=async(url,init)=>{count++;assert.equal(String(url),A.canonical_url+'/wp-json/unlimited-wp-mcp-next/v1/mcp');assert.equal(init.headers.authorization,'Bearer access-'+A.alias);const q=JSON.parse(init.body);assert.equal(q.params.name,'file_read');assert.deepEqual(q.params.arguments,{path:'style.css'});return success();};
  const out=await rpc(env,'read_file',{site_url:A.canonical_url,path:'style.css'});assert.equal(out.result.content[0].text,'{"unchanged_native_output":true}');assert.equal(count,1);
  assert.ok((await rpc(env,'read_file',{site_url:A.canonical_url,path:'style.css'},'other-user')).error);assert.equal(count,1);
  assert.ok((await rpc(env,'read_file',{site_url:'https://not-registered.example.com',path:'style.css'})).error);assert.equal(count,1);
});
test('public schemas do not require native approval IDs; encrypted exact-call handoff resumes explicitly',async()=>{
  const env=await environment();const args={site_url:A.canonical_url,path:'style.css',old_content:'old',new_content:'new'};let phase='request',count=0;
  globalThis.fetch=async(url,init)=>{count++;const q=JSON.parse(init.body);assert.equal(q.params.name,'file_edit');if(phase==='request'){assert.equal(q.params.arguments.approval_id,undefined);return required();}assert.equal(q.params.arguments.approval_id,approval);if(phase==='pending')return response({result:{isError:true,content:[{type:'text',text:JSON.stringify({code:'approval_invalid'})}]}});return success();};
  const first=await rpc(env,'edit_file',args);assert.ok(first.result.isError);
  const row=env.DB.sql.prepare('SELECT * FROM compat_pending').get();assert.ok(row);assert.ok(!JSON.stringify(row).includes(approval));
  phase='pending';await rpc(env,'edit_file',args);assert.equal(env.DB.sql.prepare('SELECT used FROM approval_scopes').get().used,0);
  phase='approved';const out=await rpc(env,'edit_file',{new_content:'new',old_content:'old',path:'style.css',site_url:A.canonical_url});assert.equal(out.result.isError,false);assert.equal(count,3);
  assert.equal(env.DB.sql.prepare('SELECT count(*) AS n FROM compat_pending').get().n,0);assert.equal(env.DB.sql.prepare('SELECT used FROM approval_scopes').get().used,1);
});
test('REST parameters cannot replace the public target or inject an approval identifier',async()=>{
  const env=await environment();globalThis.fetch=()=>assert.fail('No forwarding');
  for(const parameters of [{site:B.alias},{approval_id:approval},JSON.parse('{"__proto__":{"site":"booking"}}')])assert.ok((await rpc(env,'rest_api',{site_url:A.canonical_url,method:'POST',path:'/wpvibe/v1/content/edit',parameters})).error);
});
test('compatibility handoff never retries or replays an uncertain approved write',async()=>{
  const env=await environment(),args={site_url:A.canonical_url,path:'style.css',old_content:'old',new_content:'new'};let count=0;
  globalThis.fetch=async()=>{count++;return required();};await rpc(env,'edit_file',args);
  globalThis.fetch=async()=>{count++;throw new Error('connection lost after send');};
  assert.ok((await rpc(env,'edit_file',args)).error);assert.equal(count,2);
  assert.ok((await rpc(env,'edit_file',args)).error);assert.equal(count,2,'used approval cannot issue a second WordPress write');
});
test('old Unlimited names and target aliases remain callable without being advertised',async()=>{
  const env=await environment();globalThis.fetch=async(url,init)=>{assert.equal(JSON.parse(init.body).params.name,'file_read');return success();};
  assert.equal((await rpc(env,'file_read',{site:A.alias,path:'style.css'})).result.isError,false);
});
test('global resources never choose between multiple connected sites implicitly',async()=>{
  const env=await environment();globalThis.fetch=()=>assert.fail('No forwarding');
  assert.ok((await rpc(env,'search_images',{query:'forest'})).error);
  assert.ok((await rpc(env,'load_skill',{name:'builders'})).error);
});


test('resource compatibility uses selected connection without WordPress requests',async()=>{
 const env=await environment();
 globalThis.fetch=()=>{throw new Error('No WordPress call expected');};
 const result=await rpc(env,'load_skill',{site_url:A.canonical_url});
 assert.equal(JSON.parse(result.result.content[0].text).skills.length,4);
 const unknown=await rpc(env,'load_skill',{site_url:'https://unregistered.example.com'});
 assert.ok(unknown.error);
});


test('image compatibility routes public count and returns a standard MCP result',async()=>{
 const env=await environment();
 globalThis.fetch=async(url)=>{
   assert.equal(url.origin,'https://api.openverse.org');
   assert.equal(url.searchParams.get('page_size'),'2');
   return response({results:[{url:'https://images.example.com/test.jpg',license:'cc0',attribution:'Public domain'}]});
 };
 const result=await rpc(env,'search_images',{site_url:A.canonical_url,query:'forest',count:2});
 assert.equal(JSON.parse(result.result.content[0].text).results[0].license,'cc0');
 assert.ok((await rpc(env,'search_images',{site_url:A.canonical_url,query:'forest'},'other-user')).error);
});
