import assert from 'node:assert/strict';
import {test,afterEach} from 'node:test';
import {execFileSync} from 'node:child_process';
import {mkdtempSync,readFileSync,existsSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {resolve,join} from 'node:path';
import gateway from '../gateway/src/index.js';
import {environment,rpc} from '../gateway/tests/routing-fixtures.mjs';
const originalFetch=globalThis.fetch,origin='https://gateway.example.com',user='routing-owner';
const repo=fileURLToPath(new URL('../',import.meta.url)),runtime=resolve(repo,'../.runtime'),php=process.env.PHP_BINARY||join(runtime,'php-update-tests/php.exe');
afterEach(()=>{globalThis.fetch=originalFetch;});

for(const [label,path,pretty,homePath]of [
  ['root pretty','',true,''],['subdirectory pretty','/wordpress',true,'/wordpress'],
  ['root plain','',false,''],['subdirectory plain','/wordpress',false,'/wordpress'],
  ['WordPress directory, root home, pretty','/wordpress',true,''],['WordPress directory, root home, plain','/wordpress',false,''],
])test('real WordPress routing acceptance: '+label,async()=>{
  const fixture=mkdtempSync(join(runtime,'routing-acceptance-')),site='https://routing.example.com'+path,home='https://routing.example.com'+homePath,e=environment(),requests=[];
  function wordpress(operation,extra={}) {
    const result=execFileSync(php,[join(repo,'tests/routing-wordpress.php'),fixture],{input:JSON.stringify({site_url:site,home_url:home,pretty,operation,...extra}),encoding:'utf8',maxBuffer:4*1024*1024});
    return JSON.parse(result);
  }
  globalThis.fetch=async(value,init={})=>{
    const url=String(value);assert.equal(new URL(url).origin,new URL(site).origin);assert.equal(init.redirect,'manual');requests.push({url,method:init.method||'GET'});
    const result=wordpress('request',{request:{url,method:init.method||'GET',headers:init.headers,body:init.body}});
    return new Response(JSON.stringify(result.body),{status:result.status,headers:result.headers});
  };
  const start=await rpc(e,'connect_site',{site_url:site});assert.ok(start.result,JSON.stringify(start));
  const authorization=JSON.parse(start.result.content[0].text),auth=new URL(authorization.authorization_url);assert.equal(authorization.site_url,site);assert.equal(auth.origin+auth.pathname,site+'/wp-admin/admin-post.php');
  const consent=wordpress('consent',{authorization_url:authorization.authorization_url});
  const callback=new URL(origin+'/oauth/callback');for(const [k,v]of Object.entries(consent.body))callback.searchParams.set(k,v);
  const connected=await gateway.fetch(new Request(callback,{headers:{'oai-authenticated-user-id':user}}),e);assert.match(await connected.text(),/Connected to/);
  const sites=JSON.parse((await rpc(e,'list_sites',{})).result.content[0].text);assert.equal(sites.length,1);assert.equal(sites[0].canonical_url,site);assert.equal(sites[0].verification.state,'connected');
  const read=await rpc(e,'site_info',{site_url:site});assert.equal(JSON.parse(read.result.content[0].text).site_url.replace(/\/$/,''),site);
  // Native WordPress exact-operation gate and the gateway's encrypted approval
  // adapter must still agree on a path-bearing admin URL and original scope.
  const command={site_url:site,command:'post list --format=json'};
  const approval=await rpc(e,'run_wp_cli',command),required=JSON.parse(approval.result.content[0].text);assert.equal(required.code,'approval_required');
  assert.equal(new URL(required.data.approval_url).pathname,path+'/wp-admin/admin.php');
  assert.equal(wordpress('approve',{approval_id:required.data.approval_id}).body.approved,true);
  const executed=await rpc(e,'run_wp_cli',command);assert.equal(executed.result.isError,false,JSON.stringify(executed));
  e.DB.sql.exec('UPDATE oauth_connections SET expires_at=1');
  const refreshed=await rpc(e,'site_info',{site_url:site});assert.equal(refreshed.result.isError,false,JSON.stringify(refreshed));
  const removed=JSON.parse((await rpc(e,'remove_site',{site_url:site})).result.content[0].text);assert.equal(removed.wordpress_revocation_confirmed,true);
  assert.equal(JSON.parse((await rpc(e,'list_sites',{})).result.content[0].text).length,0);
  if(!pretty)for(const r of requests.filter(r=>r.method==='POST'))assert.ok(new URL(r.url).searchParams.has('rest_route'),'every POST uses detected plain REST form');
  assert.ok(!existsSync(join(fixture,'debug.log'))||!readFileSync(join(fixture,'debug.log'),'utf8'),'no fresh PHP diagnostics');
});
