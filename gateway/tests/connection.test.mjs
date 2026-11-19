import assert from 'node:assert/strict';
import {test,afterEach} from 'node:test';
import {DatabaseSync} from 'node:sqlite';
import {readFileSync} from 'node:fs';
import gateway from '../src/index.js';
import {preflight,verifySiteRead,observe,currentObservation} from '../src/connection-readiness.js';
import {endpoints} from '../src/security.js';
const originalFetch=globalThis.fetch,origin='https://gateway.example.com',siteURL='https://registration.example.com',user='owner';
afterEach(()=>{globalThis.fetch=originalFetch;});
function env(){const sql=new DatabaseSync(':memory:');for(const f of ['0000_multisite.sql','0001_interface_compat.sql','0002_connection_observations.sql'])sql.exec(readFileSync(new URL('../drizzle/'+f,import.meta.url),'utf8'));return {OAUTH_ENCRYPTION_KEY:Buffer.alloc(32,19).toString('base64'),DB:{sql,prepare(query){return{bind(...args){return{async first(){return sql.prepare(query).get(...args)||null;},async all(){return{results:sql.prepare(query).all(...args)};},async run(){return{meta:{changes:Number(sql.prepare(query).run(...args).changes)}};}};}};},async batch(stmts){sql.exec('BEGIN');try{const out=[];for(const s of stmts)out.push(await s.run());sql.exec('COMMIT');return out;}catch(e){sql.exec('ROLLBACK');throw e;}}}};}
function response(body,status=200,headers={}){return new Response(JSON.stringify(body),{status,headers:{'content-type':'application/json',...headers}});}
function freshInfo(url=siteURL,admin=true){return {site_url:url,site_name:'Fixture',uwnext_plugin_version:'2.0.1',connected_user:{login:'fixture',roles:['administrator'],caps:{read:true,edit_theme_options:true,manage_options:admin}}};}
function mock(options={}){return async(url,init={})=>{
  const u=new URL(url);
  if(u.pathname==='/wp-json/')return options.rest||response({namespaces:['wp/v2','unlimited-wp-mcp-next/v1']});
  if(u.pathname.endsWith('/connection/preflight'))return response({plugin:'unlimited-wp-mcp-next',site_url:options.clone||u.origin,https:true,oauth_available:options.available!==false,authorization_seen:options.header!==false,challenge:options.stale?'stale':u.searchParams.get('challenge'),oauth:{issuer:u.origin+'/wp-json/unlimited-wp-mcp-next/v1/oauth',token_endpoint:u.origin+'/wp-json/unlimited-wp-mcp-next/v1/oauth/token'}},200,options.cached?{age:'12'}:{});
  if(u.pathname.endsWith('/register-path'))return response({client_id:'registered-client'});
  if(u.pathname.endsWith('/token'))return response({access_token:'oauth-access',refresh_token:'oauth-refresh',expires_in:3600});
  if(u.pathname.endsWith('/mcp')){
    const q=JSON.parse(init.body);assert.equal(q.params.name,'site_info');assert.deepEqual(q.params.arguments,{});
    assert.equal(init.headers.authorization,'Bearer oauth-access');assert.equal(init.redirect,'manual');
    return response({jsonrpc:'2.0',id:options.staleRead?'old-id':q.id,result:{isError:!!options.readFailure,content:[{type:'text',text:JSON.stringify(options.readFailure?{code:'capability_denied'}:freshInfo(options.identity||siteURL,options.admin!==false))}]}});
  }throw new Error('Unexpected target '+u.pathname);
};}
async function rpc(e,name,args){return (await gateway.fetch(new Request(origin+'/mcp',{method:'POST',headers:{'content-type':'application/json','oai-authenticated-user-id':user},body:JSON.stringify({jsonrpc:'2.0',id:1,method:'tools/call',params:{name,arguments:args}})}),e)).json();}
async function start(e){const result=await rpc(e,'connect_site',{site_url:siteURL});assert.ok(result.result,JSON.stringify(result));return JSON.parse(result.result.content[0].text);}
async function finish(e,authorization){return gateway.fetch(new Request(origin+'/oauth/callback?code=code&state='+new URL(authorization.authorization_url).searchParams.get('state'),{headers:{'oai-authenticated-user-id':user}}),e);}

test('clean successful connection uses HTTPS preflight, PKCE, encryption and a real post-connect site read',async()=>{
  const e=env();globalThis.fetch=mock();const authorization=await start(e);assert.equal(authorization.status,'authorization_required');
  const target=new URL(authorization.authorization_url);assert.equal(target.searchParams.get('code_challenge_method'),'S256');
  const pending=e.DB.sql.prepare('SELECT * FROM connection_observations').get();assert.equal(pending.state,'awaiting_confirmation');
  const result=await finish(e,authorization);assert.match(await result.text(),/Connected to/);
  const c=e.DB.sql.prepare('SELECT * FROM oauth_connections').get();assert.ok(!JSON.stringify(c).includes('oauth-access'));assert.ok(!JSON.stringify(c).includes('oauth-refresh'));
  const sites=JSON.parse((await rpc(e,'list_sites',{})).result.content[0].text);assert.equal(sites[0].connection_status,'connected');assert.equal(sites[0].verification.state,'connected');
  assert.equal((await finish(e,authorization)).status,400,'OAuth state stays one-use');
});
test('HTTPS failure is diagnosed without issuing an outbound request',async()=>{
  globalThis.fetch=()=>assert.fail('No HTTP request');assert.equal((await preflight('http://registration.example.com')).state,'failing');
  assert.ok((await rpc(env(),'connect_site',{site_url:'http://registration.example.com'})).error);
});
test('REST blocked or namespaces removed prevents claiming readiness',async()=>{
  globalThis.fetch=mock({rest:response({namespaces:['wp/v2']})});assert.equal((await preflight(siteURL)).state,'failing');
  globalThis.fetch=mock({rest:new Response('<html>Wordfence blocked a request</html>',{status:403})});
  const report=await preflight(siteURL);assert.equal(report.state,'failing');assert.equal(report.checks.find(c=>c.stage==='REST accessibility').possible_interference,'Wordfence');
});
test('authorization unavailable blocks the new authorization link with recovery guidance',async()=>{
  globalThis.fetch=mock({available:false});const e=env(),out=await rpc(e,'connect_site',{site_url:siteURL});assert.match(out.error.message,/authorization is unavailable/i);assert.match(out.error.message,/security plugin/);assert.equal(e.DB.sql.prepare('SELECT count(*) AS n FROM oauth_connections').get().n,0);
});
test('HTTP Basic Auth/password gate is distinct from WordPress credential rejection',async()=>{
  globalThis.fetch=mock({rest:new Response('Protected',{status:401,headers:{'www-authenticate':'Basic realm="Staging"'}})});
  const report=await preflight(siteURL);assert.equal(report.state,'failing');assert.match(report.checks[1].summary,/password gate/);assert.match(report.checks[1].next,/does not send/);
});
test('connected credential with failed verification reports authorization success and clear recovery',async()=>{
  const e=env();globalThis.fetch=mock();const authorization=await start(e);globalThis.fetch=mock({readFailure:true});
  const html=await (await finish(e,authorization)).text();assert.match(html,/Authorized; verification failed/);assert.match(html,/Check connection again/);
  const rows=JSON.parse((await rpc(e,'list_sites',{})).result.content[0].text);assert.equal(rows[0].connection_status,'connected');assert.equal(rows[0].verification.state,'failing');
  globalThis.fetch=mock();const r=await gateway.fetch(new Request(origin+'/connection/check',{method:'POST',headers:{origin,'oai-authenticated-user-id':user},body:new URLSearchParams({site:authorization.site_id})}),e);
  assert.match(await r.text(),/Connection check: connected/);
});
test('successful post-connect read with reduced account permissions reports limited access',async()=>{
  const e=env();globalThis.fetch=mock({admin:false});const a=await start(e);assert.match(await (await finish(e,a)).text(),/limited permissions/);
  const row=e.DB.sql.prepare('SELECT * FROM connection_observations').get();assert.equal(row.state,'limited');
});
test('stale/cached or cloned-site preflight responses are never treated as current readiness',async()=>{
  for(const options of [{stale:true},{cached:true},{clone:'https://clone.example.com'}]){globalThis.fetch=mock(options);assert.equal((await preflight(siteURL)).state,'failing');}
});
test('post-connect verification rejects cloned site identity and stale MCP response IDs',async()=>{
  for(const options of [{identity:'https://clone.example.com'},{staleRead:true}]){
    const e=env();globalThis.fetch=mock();const a=await start(e);globalThis.fetch=mock(options);assert.match(await (await finish(e,a)).text(),/verification failed/);
    assert.equal(e.DB.sql.prepare('SELECT state FROM connection_observations').get().state,'failing');
  }
});
test('observations expire and cannot cross users, sites, credentials or replace newer evidence',async()=>{
  const e=env(),site={site_id:'fixture',canonical_url:siteURL},now=Date.now();
  await observe(e,user,site,'grant',{state:'connected',reason:'read'},now);
  await observe(e,user,site,'grant',{state:'failing',reason:'old'},now-1);
  assert.equal((await currentObservation(e,user,site,'grant',now)).state,'connected');
  assert.equal((await currentObservation(e,user,site,'grant',now+300000)).state,'stored_unverified');
  assert.equal((await currentObservation(e,'other',site,'grant',now)).state,'stored_unverified');
  assert.equal((await currentObservation(e,user,{...site,canonical_url:'https://clone.example.com'},'grant',now)).state,'stored_unverified');
  assert.equal((await currentObservation(e,user,site,'new-grant',now)).state,'stored_unverified');
  assert.equal((await currentObservation(e,user,site,'grant',now-1)).state,'stored_unverified');
});
test('stripped Authorization is identified without a vendor fallback or credential disclosure',async()=>{
  globalThis.fetch=mock({header:false});const report=await preflight(siteURL);assert.equal(report.state,'failing');assert.match(report.checks.find(c=>c.stage==='Authorization header').next,/forward Authorization/);
});
test('anonymous REST restriction is limited when the actual OAuth readiness endpoint works',async()=>{
  globalThis.fetch=mock({rest:response({code:'rest_login_required'},403)});assert.equal((await preflight(siteURL)).state,'limited');
});
test('connection checks require the existing authenticated same-origin gateway form',async()=>{
  const e=env();globalThis.fetch=mock();const a=await start(e);
  for(const headers of [{'oai-authenticated-user-id':user,origin:'https://evil.example.com'},{origin}]){
    const r=await gateway.fetch(new Request(origin+'/connection/check',{method:'POST',headers,body:new URLSearchParams({site:a.site_id})}),e);assert.ok(r.status>=400);
  }
});
test('blocked OAuth POST after browser consent reports incomplete authorization and never reuses the callback',async()=>{
  const e=env();globalThis.fetch=mock();const a=await start(e),normal=mock();
  globalThis.fetch=async(url,init)=>new URL(url).pathname.endsWith('/token')?new Response('<html>blocked</html>',{status:403}):normal(url,init);
  assert.match(await (await finish(e,a)).text(),/token exchange failed/);
  assert.equal(e.DB.sql.prepare('SELECT count(*) AS n FROM oauth_connections').get().n,0);
  assert.equal(e.DB.sql.prepare('SELECT state FROM connection_observations').get().state,'failing');
  assert.equal((await finish(e,a)).status,400);
});
