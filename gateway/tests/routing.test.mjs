import assert from 'node:assert/strict';
import {test,afterEach} from 'node:test';
import gateway from '../src/index.js';
import {environment,rpc} from './routing-fixtures.mjs';
import {canonicalURL,endpoints,open,seal,routingFromMetadata} from '../src/security.js';
import {credentialScope,bindApproval,consumeApproval} from '../src/store.js';
import {preflight} from '../src/connection-readiness.js';
const originalFetch=globalThis.fetch,origin='https://gateway.example.com',user='routing-owner';
afterEach(()=>{globalThis.fetch=originalFetch;});
const response=(body,status=200)=>new Response(JSON.stringify(body),{status,headers:{'content-type':'application/json'}});

function wordpress(site,home,form){
  const ns='/unlimited-wp-mcp-next/v1',plain=form!=='pretty';
  const rest=route=>plain?home+(form==='plain-index'?'/index.php':'/')+'?rest_route='+route:home+'/wp-json'+route;
  const issuer=plain?site:rest(ns+'/oauth');
  const metadata={issuer,token_endpoint:rest(ns+'/oauth/token'),registration_endpoint:rest(ns+'/oauth/register-path'),revocation_endpoint:rest(ns+'/oauth/revoke'),authorization_endpoint:site+'/wp-admin/admin-post.php?action=uwnext_authorize'};
  const calls=[],access='access:'+site,refresh='refresh:'+site;
  let tokens=0;
  return {metadata,calls,rest,fetch:async(value,init={})=>{
    const url=new URL(value);calls.push({url:url.href,init});assert.equal(init.redirect,'manual');
    let route;
    if(!plain&&url.href.startsWith(home+'/wp-json/'))route=url.pathname.slice(new URL(home).pathname.replace(/\/$/,'').length+'/wp-json'.length);
    if(url.searchParams.has('rest_route')&&[site+'/',site+'/index.php',home+'/',home+'/index.php'].includes(url.origin+url.pathname))route=url.searchParams.get('rest_route');
    if(!route)return response({code:'rest_no_route'},404);
    if(route==='/')return response({namespaces:['wp/v2','unlimited-wp-mcp-next/v1']});
    if(route===ns+'/connection/preflight')return response({plugin:'unlimited-wp-mcp-next',site_url:site,challenge:url.searchParams.get('challenge'),https:true,oauth_available:true,authorization_seen:init.headers.authorization==='Unlimited-Loopback '+url.searchParams.get('challenge'),oauth:metadata});
    if(route===ns+'/oauth/register-path')return response({client_id:Buffer.from(JSON.stringify({issuer})).toString('base64url')+'.fixture'});
    if(route===ns+'/oauth/token'){
      assert.ok([metadata.token_endpoint,...(!plain?[home+'/?rest_route='+ns+'/oauth/token']:[])].includes(url.href),'token profile stays pinned, including legacy pretty-profile refresh alias');
      assert.equal(new URLSearchParams(init.body).get('resource'),rest(ns+'/mcp'));
      tokens++;return response({access_token:access+tokens,refresh_token:refresh+tokens,expires_in:3600});
    }
    if(route===ns+'/mcp'){
      assert.equal(url.href,rest(ns+'/mcp'));assert.equal(init.headers.authorization,'Bearer '+access+tokens);
      const q=JSON.parse(init.body);
      return response({jsonrpc:'2.0',id:q.id,result:{content:[{type:'text',text:JSON.stringify({site_url:site,connected_user:{caps:{manage_options:true,edit_theme_options:true}}})}]}});
    }
    if(route===ns+'/oauth/revoke'){assert.equal(url.href,metadata.revocation_endpoint);assert.equal(new URLSearchParams(init.body).get('token'),refresh+tokens);return response({revoked:true});}
    return response({code:'rest_no_route'},404);
  }};
}

for(const [label,site,home,form]of [
  ['root pretty','https://routing.example.com','https://routing.example.com','pretty'],
  ['subdirectory pretty','https://routing.example.com/wordpress','https://routing.example.com/wordpress','pretty'],
  ['root plain','https://routing.example.com','https://routing.example.com','plain'],
  ['subdirectory plain','https://routing.example.com/wordpress','https://routing.example.com/wordpress','plain'],
  ['subdirectory index query','https://routing.example.com/wordpress','https://routing.example.com/wordpress','plain-index'],
  ['WordPress directory with homepage at root','https://routing.example.com/wordpress','https://routing.example.com','pretty'],
  ['WordPress directory with plain homepage at root','https://routing.example.com/wordpress','https://routing.example.com','plain-index'],
])test(label+' pins identity and routing through OAuth, verification, refresh and revocation',async()=>{
  const e=environment(),wp=wordpress(site,home,form);globalThis.fetch=wp.fetch;
  const result=await rpc(e,'connect_site',{site_url:site+'/'});assert.ok(result.result,JSON.stringify(result));
  const data=JSON.parse(result.result.content[0].text),auth=new URL(data.authorization_url);
  assert.equal(data.site_url,site);assert.equal(auth.origin+auth.pathname,site+'/wp-admin/admin-post.php');assert.equal(auth.searchParams.get('resource'),wp.rest('/unlimited-wp-mcp-next/v1/mcp'));
  const callback=new URL(origin+'/oauth/callback');for(const [k,v]of Object.entries({code:'fixture',state:auth.searchParams.get('state'),iss:wp.metadata.issuer}))callback.searchParams.set(k,v);
  const connected=await gateway.fetch(new Request(callback,{headers:{'oai-authenticated-user-id':user}}),e);assert.match(await connected.text(),/Connected to/);
  const c=e.DB.sql.prepare('SELECT * FROM oauth_connections').get();assert.equal(c.canonical_url,site);
  const decoded=await open(e,c.token_ciphertext,c.token_iv,credentialScope(user,{site_id:c.site_id,canonical_url:site},c.credential_id));
  assert.deepEqual(decoded.routing,{rest_base:home,rest_form:form});
  assert.equal(JSON.parse((await rpc(e,'list_sites',{})).result.content[0].text)[0].verification.state,'connected');
  assert.ok((await rpc(e,'site_info',{site_url:site})).result);
  e.DB.sql.exec('UPDATE oauth_connections SET expires_at=1');assert.ok((await rpc(e,'site_info',{site_url:site})).result);
  const updated=e.DB.sql.prepare('SELECT * FROM oauth_connections').get();assert.equal(updated.credential_id,c.credential_id);
  const after=await open(e,updated.token_ciphertext,updated.token_iv,credentialScope(user,{site_id:c.site_id,canonical_url:site},c.credential_id));assert.deepEqual(after.routing,decoded.routing);
  const identity=e.DB.sql.prepare('SELECT * FROM sites').get(),aliases=e.DB.sql.prepare('SELECT * FROM site_aliases').all();
  const registrations=()=>wp.calls.filter(c=>new URL(c.url).pathname.endsWith('/register-path')||new URL(c.url).searchParams.get('rest_route')==='/unlimited-wp-mcp-next/v1/oauth/register-path').length;
  assert.equal(registrations(),1);
  const reconnect=await gateway.fetch(new Request(origin+'/oauth/connect',{method:'POST',headers:{origin,'oai-authenticated-user-id':user},body:new URLSearchParams({site:data.site_id})}),e);
  assert.equal(reconnect.status,200);assert.equal(registrations(),2);
  assert.deepEqual(e.DB.sql.prepare('SELECT * FROM sites').get(),identity);assert.deepEqual(e.DB.sql.prepare('SELECT * FROM site_aliases').all(),aliases);
  assert.deepEqual(e.DB.sql.prepare('SELECT * FROM oauth_connections').get(),updated);
  assert.equal(JSON.parse((await rpc(e,'remove_site',{site_url:site})).result.content[0].text).wordpress_revocation_confirmed,true);
  assert.equal(e.DB.sql.prepare('SELECT count(*) AS n FROM sites').get().n,0);
});

test('canonical installation paths retain case and reject traversal, encoding and destination tricks',()=>{
  assert.equal(canonicalURL('https://Routing.Example.com/WordPress/'),'https://routing.example.com/WordPress');
  for(const suffix of ['/../other','/wordpress/./other','/wordpress//other','/%2e%2e/other','/%2Fother','/a\\other','/a?x=1','/a#x','/a\n'])assert.throws(()=>canonicalURL('https://routing.example.com'+suffix));
  for(const address of ['https://routing.example.com:443/wordpress','https://user@routing.example.com/wordpress','https://127.0.0.1/wordpress'])assert.throws(()=>canonicalURL(address));
});

test('metadata cannot move a grant to another host, sibling installation or arbitrary endpoint',()=>{
  const site={canonical_url:'https://routing.example.com/wordpress'};
  for(const routing of [{rest_base:'https://evil.example.com',rest_form:'plain'},{rest_base:'https://routing.example.com/sibling',rest_form:'plain'},{rest_base:site.canonical_url,rest_form:'custom'}])assert.throws(()=>endpoints(site,routing));
  const good=wordpress(site.canonical_url,site.canonical_url,'pretty').metadata;
  for(const bad of [{...good,token_endpoint:'https://evil.example.com/token'},{...good,authorization_endpoint:'https://routing.example.com/sibling/wp-admin/admin-post.php?action=uwnext_authorize'},{...good,issuer:'https://routing.example.com'}])assert.throws(()=>routingFromMetadata(site,bad));
});

test('subdirectory approvals require the exact admin path and stay bound to site, user and grant',async()=>{
  const e=environment(),site={site_id:'one',canonical_url:'https://routing.example.com/wordpress'},c={credential_id:'grant-one'},id='a'.repeat(48),args={command:'post list'};
  const data={approval_id:id,expires:Math.floor(Date.now()/1000)+300,approval_url:site.canonical_url+'/wp-admin/admin.php?page=unlimited-wp-mcp-next&approval='+id};
  for(const base of ['https://routing.example.com','https://routing.example.com/other'])await assert.rejects(()=>bindApproval(e,user,site,'cli_run',args,c,{...data,approval_url:base+'/wp-admin/admin.php?page=unlimited-wp-mcp-next&approval='+id}));
  await bindApproval(e,user,site,'cli_run',args,c,data);
  await assert.rejects(()=>consumeApproval(e,user,{...site,canonical_url:'https://routing.example.com/other'},'cli_run',args,c,id));
  await assert.rejects(()=>consumeApproval(e,'other',site,'cli_run',args,c,id));
  await assert.rejects(()=>consumeApproval(e,user,site,'cli_run',args,{credential_id:'other'},id));
  await consumeApproval(e,user,site,'cli_run',args,c,id);
  await assert.rejects(()=>consumeApproval(e,user,site,'cli_run',args,c,id));
});

test('encrypted routing cannot cross same-host installations',async()=>{
  const e=environment(),site='https://routing.example.com/one',wp=wordpress(site,site,'plain');globalThis.fetch=wp.fetch;
  const a=JSON.parse((await rpc(e,'connect_site',{site_url:site})).result.content[0].text),auth=new URL(a.authorization_url);
  await gateway.fetch(new Request(origin+'/oauth/callback?code=code&state='+auth.searchParams.get('state'),{headers:{'oai-authenticated-user-id':user}}),e);
  const c=e.DB.sql.prepare('SELECT * FROM oauth_connections').get();
  await assert.rejects(()=>open(e,c.token_ciphertext,c.token_iv,credentialScope(user,{site_id:c.site_id,canonical_url:'https://routing.example.com/two'},c.credential_id)));
});

test('two installations on one hostname retain independent registry entries, aliases and ciphertext',async()=>{
  const e=environment(),sites=['https://routing.example.com/one','https://routing.example.com/two'];
  for(const site of sites) {
    globalThis.fetch=wordpress(site,site,'plain').fetch;
    const result=await rpc(e,'connect_site',{site_url:site}),authorization=JSON.parse(result.result.content[0].text),auth=new URL(authorization.authorization_url);
    const connected=await gateway.fetch(new Request(origin+'/oauth/callback?code=code&state='+auth.searchParams.get('state'),{headers:{'oai-authenticated-user-id':user}}),e);
    assert.match(await connected.text(),/Connected to/);
  }
  const rows=JSON.parse((await rpc(e,'list_sites',{})).result.content[0].text);
  assert.equal(rows.length,2);assert.deepEqual(new Set(rows.map(r=>r.canonical_url)),new Set(sites));assert.equal(new Set(rows.map(r=>r.aliases)).size,2);
  const [a,b]=e.DB.sql.prepare('SELECT * FROM oauth_connections ORDER BY canonical_url').all();
  e.DB.sql.prepare('UPDATE oauth_connections SET token_ciphertext=?,token_iv=? WHERE site_id=?').run(a.token_ciphertext,a.token_iv,b.site_id);
  globalThis.fetch=()=>assert.fail('Copied grant must fail before any WordPress request');
  assert.ok((await rpc(e,'site_info',{site_url:b.canonical_url})).error);
});

test('fallback is pre-authorization only; an uncertain MCP failure is sent once to its pinned endpoint',async()=>{
  const e=environment(),site='https://routing.example.com/wordpress',wp=wordpress(site,site,'plain');globalThis.fetch=wp.fetch;
  const a=JSON.parse((await rpc(e,'connect_site',{site_url:site})).result.content[0].text),auth=new URL(a.authorization_url);
  await gateway.fetch(new Request(origin+'/oauth/callback?code=code&state='+auth.searchParams.get('state'),{headers:{'oai-authenticated-user-id':user}}),e);
  let calls=0;globalThis.fetch=async url=>{calls++;assert.equal(String(url),wp.rest('/unlimited-wp-mcp-next/v1/mcp'));throw new Error('Uncertain transport');};
  assert.ok((await rpc(e,'run_wp_cli',{site_url:site,command:'post list'})).error);assert.equal(calls,1);
});

test('pretty REST is preferred without probing plain when healthy',async()=>{
  const site='https://routing.example.com/wordpress',wp=wordpress(site,site,'pretty');globalThis.fetch=wp.fetch;
  assert.equal((await preflight(site)).routing.rest_form,'pretty');assert.equal(wp.calls.length,2);
});

test('a root-site PKCE flow created by the previous gateway still completes',async()=>{
  const e=environment(),site='https://routing.example.com',wp=wordpress(site,site,'pretty');globalThis.fetch=wp.fetch;
  const result=await rpc(e,'connect_site',{site_url:site}),authorization=JSON.parse(result.result.content[0].text),auth=new URL(authorization.authorization_url);
  const flow=e.DB.sql.prepare('SELECT * FROM oauth_flows').get(),scope={user,site_id:flow.site_id,canonical_url:site,state_hash:flow.state_hash};
  const pending=await open(e,flow.verifier_ciphertext,flow.verifier_iv,scope);
  const legacy=await seal(e,pending.verifier,scope);
  e.DB.sql.prepare('UPDATE oauth_flows SET verifier_ciphertext=?,verifier_iv=? WHERE state_hash=?').run(legacy.ciphertext,legacy.iv,flow.state_hash);
  const connected=await gateway.fetch(new Request(origin+'/oauth/callback?code=code&state='+auth.searchParams.get('state')+'&iss='+encodeURIComponent(wp.metadata.issuer),{headers:{'oai-authenticated-user-id':user}}),e);
  assert.match(await connected.text(),/Connected to/);
});
