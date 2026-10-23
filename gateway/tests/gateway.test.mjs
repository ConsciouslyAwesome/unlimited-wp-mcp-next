import assert from 'node:assert/strict';
import { test, afterEach } from 'node:test';
import { DatabaseSync } from 'node:sqlite';
import { readFileSync } from 'node:fs';
import gateway from '../src/index.js';
import { canonicalURL, endpoints, open } from '../src/security.js';
import { DEFAULT_SITES, connection, credentialScope, resolve, saveTokens, seed } from '../src/store.js';
import { UPSTREAM_TOOLS } from '../src/upstream-tools.generated.js';

const origin='https://central-gateway.chatgpt.site',user='owner',originalFetch=globalThis.fetch;
const [A,B]=DEFAULT_SITES,approval='a'.repeat(48);
afterEach(()=>{globalThis.fetch=originalFetch;});
function db() {
  const sql=new DatabaseSync(':memory:');
  const migration=readFileSync(new URL('../drizzle/0000_multisite.sql',import.meta.url),'utf8');
  sql.exec('PRAGMA foreign_keys=ON');sql.exec(migration);sql.exec(migration);
  function prepare(query) { return {bind(...args) {return {query,args,async first(){return sql.prepare(query).get(...args)||null;},async all(){return {results:sql.prepare(query).all(...args)};},async run(){const x=sql.prepare(query).run(...args);return {meta:{changes:Number(x.changes)}};}};}}; }
  return {sql,prepare,async batch(statements){sql.exec('BEGIN');try{const result=[];for(const s of statements) result.push(await s.run());sql.exec('COMMIT');return result;}catch(e){sql.exec('ROLLBACK');throw e;}}};
}
async function env() {
  const e={DB:db(),OAUTH_ENCRYPTION_KEY:Buffer.alloc(32,19).toString('base64')};
  await seed(e,user);
  for(const s of [A,B]) await saveTokens(e,user,s,'client-'+s.alias,{access_token:'access-'+s.alias,refresh_token:'refresh-'+s.alias,expires_in:3600});
  return e;
}
function request(path,body,who=user) {return new Request(origin+path,{method:'POST',headers:{'content-type':'application/json',origin,...(who?{'oai-authenticated-user-id':who}:{})},body:JSON.stringify(body)});}
async function rpc(e,name,args,who=user) {const r=await gateway.fetch(request('/mcp',{jsonrpc:'2.0',id:7,method:'tools/call',params:{name,arguments:args}},who),e);return {response:r,body:await r.json()};}
function response(body,status=200) {return new Response(JSON.stringify(body),{status,headers:{'content-type':'application/json'}});}
function approvalResponse(site=B) {return response({jsonrpc:'2.0',id:1,result:{isError:true,content:[{type:'text',text:JSON.stringify({code:'approval_required',message:'Review',data:{status:409,approval_id:approval,expires:Math.floor(Date.now()/1000)+600,approval_url:site.canonical_url+'/wp-admin/admin.php?page=unlimited-wp-mcp-next&approval='+approval}})}]}});}
async function issue(e,site=B) {globalThis.fetch=async()=>approvalResponse(site);const result=await rpc(e,'content_edit',{site:site.alias,target_type:'post',post_id:123,old_content:'before',new_content:'after'});assert.ok(result.body.result?.isError,'Fixture must reach approval gate');assert.equal(e.DB.sql.prepare('SELECT count(*) AS n FROM approval_scopes').get().n,1);return result;}

for(const wrongPage of ['unlimited-wp-mcp','wpvibe']) test('reject approval for another product page '+wrongPage,async()=>{
  const e=await env();let count=0;
  globalThis.fetch=async()=>{
    count++;const body=await approvalResponse(B).json();
    const payload=JSON.parse(body.result.content[0].text);
    const url=new URL(payload.data.approval_url);url.searchParams.set('page',wrongPage);
    payload.data.approval_url=url.href;body.result.content[0].text=JSON.stringify(payload);
    return response(body);
  };
  const x=await rpc(e,'content_edit',{site:B.alias,target_type:'post',post_id:123,old_content:'before',new_content:'after'});
  assert.match(x.body.error.message,/Approval hostname or identifier mismatch/);
  assert.equal(count,1);assert.equal(e.DB.sql.prepare('SELECT count(*) AS n FROM approval_scopes').get().n,0);
});

test('one shared 51-tool catalog plus discovery, explicit site on all tools',async()=>{
  const e=await env(),r=await gateway.fetch(request('/mcp',{jsonrpc:'2.0',id:1,method:'tools/list'},''),e),body=await r.json();
  assert.equal(body.result.tools.length,52);assert.equal(UPSTREAM_TOOLS.length,51);
  assert.equal(UPSTREAM_TOOLS.filter(t=>t.annotations?.readOnlyHint).length,29);
  for(const t of body.result.tools.filter(t=>t.name!=='list_sites')) assert.ok(t.inputSchema.required.includes('site'));
  assert.ok(body.result.tools.some(t=>t.name==='delete_draft_theme' && !t.annotations.readOnlyHint));
});
test('idempotent schema and registry seeding preserve populated credentials and disabled state',async()=>{
  const e=await env(),before=e.DB.sql.prepare('SELECT * FROM oauth_connections').all();
  e.DB.sql.prepare('UPDATE sites SET enabled=0 WHERE site_id=?').run(B.site_id);
  await seed(e,user);assert.deepEqual(e.DB.sql.prepare('SELECT * FROM oauth_connections').all(),before);
  assert.equal(e.DB.sql.prepare('SELECT count(*) AS n FROM sites').get().n,2);
  await assert.rejects(()=>resolve(e,user,B.alias),/disabled/);
});
for(const site of [A,B]) test(site.alias+' routes only its credential to its canonical endpoint',async()=>{
  const e=await env();let count=0;
  globalThis.fetch=async(url,init)=>{count++;assert.equal(String(url),endpoints(site).mcp);assert.equal(init.redirect,'manual');assert.equal(init.headers.authorization,'Bearer access-'+site.alias);assert.equal(JSON.parse(init.body).params.arguments.site,undefined);return response({result:{content:[{type:'text',text:site.alias}]}});};
  const x=await rpc(e,'site_info',{site:site.alias});assert.ok(x.body.result);assert.equal(count,1);assert.equal(x.body._meta.site_id,site.site_id);
});
for(const selector of [undefined,'unknown','Booking','booking%00','https://booking.example.com','../booking']) test('reject selector '+String(selector),async()=>{
  const e=await env();globalThis.fetch=()=>assert.fail('No network call permitted');assert.ok((await rpc(e,'site_info',{site:selector})).body.error);
});
test('user identity and authorization do not cross users',async()=>{
  const e=await env();globalThis.fetch=()=>assert.fail('No network');
  assert.ok((await rpc(e,'site_info',{site:B.alias},'other-user')).body.error);
  assert.ok((await rpc(e,'site_info',{site:B.alias},'')).body.error);
  const list=await rpc(e,'list_sites',{},'other-user');assert.deepEqual(JSON.parse(list.body.result.content[0].text),[]);
});
test('aliases resolve stable IDs; duplicates roll back; ambiguous ID/alias rejected',async()=>{
  const e=await env();assert.equal((await resolve(e,user,B.alias)).site_id,B.site_id);
  assert.equal((await resolve(e,user,B.site_id)).site_id,B.site_id);
  assert.throws(()=>e.DB.sql.prepare('INSERT INTO site_aliases VALUES(?,?,?)').run(user,B.alias,A.site_id));
  e.DB.sql.prepare('INSERT INTO site_aliases VALUES(?,?,?)').run(user,A.site_id,B.site_id);
  await assert.rejects(()=>resolve(e,user,A.site_id),/ambiguous/);
});
for(const url of ['http://original.example.com','https://original.example.com:443','https://original.example.com:8443','https://localhost','https://127.0.0.1','https://10.0.0.1','https://[::1]','https://%63onsciouslyawesome.com','https://original.example.com@evil.com','https://original.example.com/path','https://original.example.com?x=1','https://original.example.com.','https://site.internal']) test('reject malformed/private URL '+url,()=>assert.throws(()=>canonicalURL(url)));
test('mixed-case and trailing slash normalize at registration; noncanonical database records rejected',async()=>{
  assert.equal(canonicalURL('https://Original.Example.com/'),A.canonical_url);
  const e=await env();e.DB.sql.prepare('UPDATE sites SET canonical_url=? WHERE site_id=?').run('https://Booking.ConsciouslyAwesome.com/',B.site_id);
  globalThis.fetch=()=>assert.fail('No network');assert.ok((await rpc(e,'site_info',{site:B.alias})).body.error);
});
test('tool arguments and envelope cannot replace connection destination',async()=>{
  const e=await env();globalThis.fetch=()=>assert.fail('No network');
  for(const key of ['url','canonical_url','site_id','endpoint','base_url']) assert.ok((await rpc(e,'site_info',{site:A.alias,[key]:B.canonical_url})).body.error);
});
test('redirects are never followed and credentials never sent to redirect target',async()=>{
  const e=await env();let count=0;globalThis.fetch=async(url,init)=>{count++;assert.equal(String(url),endpoints(A).mcp);assert.equal(init.redirect,'manual');return new Response(null,{status:307,headers:{location:endpoints(B).mcp}});};
  assert.match((await rpc(e,'site_info',{site:A.alias})).body.error.message,/redirect/);assert.equal(count,1);
});
test('AES-GCM additional authenticated data prevents copied ciphertext crossing site/user/grant',async()=>{
  const e=await env(),c=await connection(e,user,A);
  for(const scope of [credentialScope(user,B,c.credential_id),credentialScope('other',A,c.credential_id),credentialScope(user,A,'different')]) await assert.rejects(()=>open(e,c.token_ciphertext,c.token_iv,scope));
  e.DB.sql.prepare('UPDATE oauth_connections SET token_ciphertext=?,token_iv=? WHERE site_id=?').run(c.token_ciphertext,c.token_iv,B.site_id);
  globalThis.fetch=()=>assert.fail('No network');assert.ok((await rpc(e,'site_info',{site:B.alias})).body.error);
});
test('approval gate contains hostname, site, operation, digest and expiry with exactly one upstream call',async()=>{
  const e=await env();let count=0;globalThis.fetch=async()=>{count++;return approvalResponse(B);};
  const x=await rpc(e,'content_edit',{site:B.alias,target_type:'post',post_id:123,old_content:'before',new_content:'after'});
  const data=JSON.parse(x.body.result.content[0].text);assert.match(data.message,/booking.example.com/);
  assert.equal(data.data.approval_scope.site_id,B.site_id);assert.equal(data.data.approval_scope.tool,'content_edit');assert.ok(data.data.approval_scope.arguments_hash);assert.ok(data.data.approval_scope.expires_at>Date.now());assert.equal(count,1);
  const stored=e.DB.sql.prepare('SELECT * FROM approval_scopes').get();assert.notEqual(stored.approval_hash,approval);assert.equal(stored.used,0);
});
for(const change of ['site','tool','arguments','expired','used','tampered','disabled','removed-credential','new-grant','changed-canonical','user']) test('approval fails safely after '+change,async()=>{
  const e=await env();await issue(e);let name='content_edit',args={site:B.alias,target_type:'post',post_id:123,old_content:'before',new_content:'after',approval_id:approval},who=user;
  if(change==='site') args.site=A.alias;
  if(change==='tool') {name='file_write';args={site:B.alias,path:'x.txt',content:'x',approval_id:approval};}
  if(change==='arguments') args.new_content='changed';
  if(change==='expired') e.DB.sql.exec('UPDATE approval_scopes SET expires_at=1');
  if(change==='used') e.DB.sql.exec('UPDATE approval_scopes SET used=1');
  if(change==='tampered') args.approval_id='b'.repeat(48);
  if(change==='disabled') e.DB.sql.prepare('UPDATE sites SET enabled=0 WHERE site_id=?').run(B.site_id);
  if(change==='removed-credential') e.DB.sql.prepare('DELETE FROM oauth_connections WHERE site_id=?').run(B.site_id);
  if(change==='new-grant') await saveTokens(e,user,B,'new-client',{access_token:'new-a',refresh_token:'new-r'});
  if(change==='changed-canonical') e.DB.sql.prepare('UPDATE sites SET canonical_url=? WHERE site_id=?').run('https://another-public-site.com',B.site_id);
  if(change==='user') who='other';
  globalThis.fetch=()=>assert.fail('Rejected approval must never reach WordPress');
  assert.ok((await rpc(e,name,args,who)).body.error);
});
test('matching local approval forwarded once; reuse rejected; no automatic retry',async()=>{
  const e=await env();await issue(e);let count=0;
  globalThis.fetch=async(url,init)=>{count++;assert.equal(String(url),endpoints(B).mcp);assert.equal(JSON.parse(init.body).params.arguments.approval_id,approval);return response({result:{content:[],isError:false}});};
  const args={site:B.alias,target_type:'post',post_id:123,old_content:'before',new_content:'after',approval_id:approval};
  assert.ok((await rpc(e,'content_edit',args)).body.result);assert.ok((await rpc(e,'content_edit',args)).body.error);assert.equal(count,1);
});
test('approval URL for a different site is never returned to the user',async()=>{
  const e=await env();globalThis.fetch=async()=>approvalResponse(A);
  const x=await rpc(e,'content_edit',{site:B.alias,target_type:'post',post_id:123,old_content:'before',new_content:'after'});assert.ok(x.body.error);assert.equal(e.DB.sql.prepare('SELECT count(*) AS n FROM approval_scopes').get().n,0);
});
test('list_sites and audit omit token material and approval identifiers',async()=>{
  const e=await env();await issue(e);const x=JSON.stringify((await rpc(e,'list_sites',{})).body);
  for(const value of ['access-booking','refresh-booking','token_ciphertext',approval]) {assert.ok(!x.includes(value));assert.ok(!JSON.stringify(e.DB.sql.prepare('SELECT * FROM bridge_diagnostics').all()).includes(value));}
});
test('OAuth connect pins site, PKCE, callback and encrypted per-site flow',async()=>{
  const e=await env();let count=0;
  globalThis.fetch=async(url,init)=>{count++;assert.equal(String(url),endpoints(B).register);assert.equal(init.redirect,'manual');assert.deepEqual(JSON.parse(init.body).redirect_uris,[origin+'/oauth/callback']);return response({client_id:'oauth-booking'});};
  const r=await gateway.fetch(new Request(origin+'/oauth/connect',{method:'POST',headers:{origin,'oai-authenticated-user-id':user},body:new URLSearchParams({site:B.alias})}),e);
  assert.equal(r.status,200);assert.equal(r.headers.get('referrer-policy'),'no-referrer');
  assert.match(r.headers.get('content-security-policy'),/form-action 'self'/);
  const html=await r.text(),href=html.match(/href="(https:\/\/booking\.example\.com[^\"]+)"/)[1];
  const target=new URL(href.replace(/&amp;/g,'&'));
  assert.equal(target.origin,B.canonical_url);assert.equal(target.searchParams.get('code_challenge_method'),'S256');assert.equal(target.searchParams.get('resource'),endpoints(B).mcp);
  const f=e.DB.sql.prepare('SELECT * FROM oauth_flows').get();assert.equal(f.site_id,B.site_id);assert.equal(f.canonical_url,B.canonical_url);assert.equal(count,1);
  // Complete a synthetic callback and prove state is single use.
  globalThis.fetch=async(url)=>String(url)===endpoints(B).token?response({access_token:'oauth-a',refresh_token:'oauth-r',expires_in:3600}):response({result:{content:[{type:'text',text:'identity'}]}});
  const cb=origin+'/oauth/callback?code=fixture&state='+target.searchParams.get('state');
  assert.equal((await gateway.fetch(new Request(cb,{headers:{'oai-authenticated-user-id':user}}),e)).status,200);
  assert.equal((await gateway.fetch(new Request(cb,{headers:{'oai-authenticated-user-id':user}}),e)).status,400);
});
test('refresh is site scoped and independent from other grants',async()=>{
  const e=await env(),other=e.DB.sql.prepare('SELECT * FROM oauth_connections WHERE site_id=?').get(A.site_id);
  e.DB.sql.prepare('UPDATE oauth_connections SET expires_at=1 WHERE site_id=?').run(B.site_id);
  const urls=[];globalThis.fetch=async(url,init)=>{urls.push(String(url));if(String(url)===endpoints(B).refresh){assert.equal(new URLSearchParams(init.body).get('refresh_token'),'refresh-booking');return response({access_token:'refreshed-a',refresh_token:'refreshed-r',expires_in:3600});}assert.equal(init.headers.authorization,'Bearer refreshed-a');return response({result:{content:[]}});};
  assert.ok((await rpc(e,'site_info',{site:B.alias})).body.result);assert.deepEqual(urls,[endpoints(B).refresh,endpoints(B).mcp]);assert.deepEqual(e.DB.sql.prepare('SELECT * FROM oauth_connections WHERE site_id=?').get(A.site_id),other);
});
test('50 registered sites retain a single catalog and deterministic indexed resolution',async()=>{
  const e=await env();for(let n=0;n<48;n++){e.DB.sql.prepare('INSERT INTO sites VALUES(?,?,?,?,1,?,?)').run(user,'fixture-'+n,'Fixture '+n,'https://site'+n+'.agency.com',1,1);e.DB.sql.prepare('INSERT INTO site_aliases VALUES(?,?,?)').run(user,'client-'+n,'fixture-'+n);}
  assert.equal(JSON.parse((await rpc(e,'list_sites',{})).body.result.content[0].text).length,50);
  assert.equal((await resolve(e,user,'client-47')).site_id,'fixture-47');
});
test('registry forms preserve same-origin POST Origin; OAuth result pages suppress referrers',async()=>{
  const e=await env();
  const home=await gateway.fetch(new Request(origin+'/',{headers:{'oai-authenticated-user-id':user}}),e);
  assert.equal(home.status,200);assert.equal(home.headers.get('referrer-policy'),'same-origin');
  assert.match(home.headers.get('content-security-policy'),/form-action 'self'/);
  assert.match(await home.text(),/method="post" action="\/oauth\/connect"/);
  const failed=await gateway.fetch(new Request(origin+'/oauth/callback',{headers:{'oai-authenticated-user-id':user}}),e);
  assert.equal(failed.headers.get('referrer-policy'),'no-referrer');
});
test('connect keeps strict origin validation for null, missing and cross-site origins',async()=>{
  const e=await env();globalThis.fetch=()=>assert.fail('Rejected origin must never register a WordPress client');
  for(const incoming of [null,'null','https://another-site.com']) {
    const headers={'oai-authenticated-user-id':user};if(incoming!==null)headers.origin=incoming;
    const r=await gateway.fetch(new Request(origin+'/oauth/connect',{method:'POST',headers,body:new URLSearchParams({site:B.alias})}),e);
    assert.equal(r.status,400);assert.match(await r.text(),/origin validation failed/);
  }
});
