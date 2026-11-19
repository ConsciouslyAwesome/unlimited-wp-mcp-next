// Diagnostic outcomes adapted from GPL WPVibe 1.20.5 connection-check and authorize-preflight.
// No vendor probes, fallback credentials, telemetry, or discovery-controlled destinations.
import {canonicalURL,endpoints,routingFromMetadata,REST_FORMS} from './security.js';
import {first,run} from './store.js';
const guidance='Check the security plugin, REST restrictions, maintenance/password gate, CDN and cache rules. Allow the Unlimited OAuth and MCP paths, bypass caching for them, and preserve Authorization headers. Then run Check connection again.';
const markers=[[/sucuri|cloudproxy/i,'Sucuri'],[/wordfence/i,'Wordfence'],[/imunify/i,'Imunify360'],[/mod_security|modsecurity/i,'ModSecurity'],[/cloudflare|cf-ray|attention required/i,'Cloudflare'],[/litespeed/i,'LiteSpeed'],[/siteground|sg-?captcha/i,'SiteGround'],[/solid security|ithemes/i,'Solid Security'],[/cerber/i,'WP Cerber']];
export async function diagnosticGet(url,headers={}) {
  try {
    const r=await fetch(url,{method:'GET',redirect:'manual',signal:AbortSignal.timeout(8000),headers:{accept:'application/json','cache-control':'no-cache',...headers}});
    const reader=r.body?.getReader();let length=0,text='';const decoder=new TextDecoder();
    if(reader){try{while(true){const {value,done}=await reader.read();if(done)break;length+=value.length;if(length>262144){await reader.cancel();return {http:r.status,kind:'oversized'};}text+=decoder.decode(value,{stream:true});}text+=decoder.decode();}finally{reader.releaseLock();}}
    let body;try{body=JSON.parse(text);}catch{}
    const marker=markers.find(([re])=>re.test(text.slice(0,20000)+'\n'+r.headers.get('server')))?.[1]||null;
    return {http:r.status,kind:body&&typeof body==='object'?'json':text.trim()?'html':'empty',body,marker,basic:/basic/i.test(r.headers.get('www-authenticate')||''),cached:Number(r.headers.get('age')||0)>0||/HIT/i.test(r.headers.get('cf-cache-status')||'')};
  }catch{return {http:0,kind:'network'};}
}
function check(stage,status,summary,next='',extra={}){return {stage,status,summary,next,...extra};}
async function probe(canonical,routing,nonce) {
  const ep=endpoints({canonical_url:canonical},routing),probeURL=new URL(ep.preflight);
  probeURL.searchParams.set('challenge',nonce);
  const results=await Promise.all([diagnosticGet(ep.index),diagnosticGet(probeURL.href,{authorization:'Unlimited-Loopback '+nonce})]);
  const checks=[check('WordPress HTTPS','passed','The selected WordPress address uses HTTPS.')];
  for(const [i,r]of results.entries()){
    const stage=i===0?'REST accessibility':'Unlimited authorization readiness';
    if(r.basic&&r.http===401){checks.push(check(stage,'failed','HTTP Basic Auth or a staging password gate blocks access before WordPress.', 'Ask the host to allow this Unlimited gateway through the gate. The gateway does not send a separate gate password.'));continue;}
    if(r.kind!=='json'||r.http>=300&&r.http<400){checks.push(check(stage,'failed',r.http>=300&&r.http<400?'The endpoint redirects; credentials will not be forwarded to a redirect.':r.kind==='network'?'The gateway could not reach the endpoint over HTTPS.':'The endpoint did not return WordPress JSON.',guidance,{http_status:r.http,possible_interference:r.marker||null}));continue;}
    if(i===0){
      if(r.http===200&&Array.isArray(r.body.namespaces)){
        const missing=['wp/v2','unlimited-wp-mcp-next/v1'].filter(n=>!r.body.namespaces.includes(n));
        checks.push(check(stage,missing.length?'failed':'passed',missing.length?'Required WordPress or Unlimited REST namespaces are missing.':'The WordPress REST index is reachable.',missing.length?guidance:''));
      }else checks.push(check(stage,[401,403].includes(r.http)?'limited':'failed','The anonymous REST index is restricted; authorization endpoints need a separate check.',guidance,{http_status:r.http}));
    }else{
      const bound=r.body.site_url===canonical&&r.body.challenge===nonce&&r.body.plugin==='unlimited-wp-mcp-next';
      const available=bound&&r.http===200&&r.body.oauth_available===true&&r.body.https===true&&r.body.oauth?.issuer===ep.issuer&&r.body.oauth?.token_endpoint===ep.token;
      checks.push(check(stage,available&&!r.cached?'passed':'failed',!bound||r.cached?'The readiness response is stale, cached, or belongs to another installation.':!available?'Unlimited OAuth authorization is unavailable.':'The current installation answers and advertises its expected OAuth endpoints.',available&&!r.cached?'':guidance));
      if(bound)checks.push(check('Authorization header',r.body.authorization_seen===true?'passed':'failed',r.body.authorization_seen===true?'The standard Authorization header reaches WordPress; credential validity is not established by this probe.':'The probe Authorization header did not reach WordPress.',r.body.authorization_seen===true?'':'Ask the host to forward Authorization to PHP. Unlimited OAuth requires the standard Bearer header; no vendor fallback is used.'));
    }
  }
  return {site_url:canonical,checked_at:Date.now(),state:checks.some(c=>c.status==='failed')?'failing':checks.some(c=>c.status==='limited')?'limited':'ready',checks,routing:ep.routing,response:results[1]};
}
export async function preflight(siteURL) {
  let canonical;try{canonical=canonicalURL(siteURL);}catch{return {state:'failing',checks:[check('WordPress HTTPS','failed','A public canonical HTTPS WordPress address is required.','Enable a valid HTTPS certificate and confirm WordPress Address in Settings > General.')],checked_at:Date.now()};}
  const nonce=Array.from(crypto.getRandomValues(new Uint8Array(32)),b=>b.toString(16).padStart(2,'0')).join('');
  let firstReport;
  const tried=new Set();
  for(const rest_form of REST_FORMS) {
    const routing={rest_base:canonical,rest_form},key=JSON.stringify(routing);
    if(tried.has(key))continue;tried.add(key);
    let report=await probe(canonical,routing,nonce);firstReport||=report;
    const r=report.response;
    // A reachable plugin response must prove fresh site identity. Do not turn a
    // stale/foreign/disabled authorization response into another route attempt.
    if(r.http===200&&r.kind==='json'&&r.body?.plugin==='unlimited-wp-mcp-next') {
      if(r.cached||r.body.site_url!==canonical||r.body.challenge!==nonce)return clean(report);
      let selected;try{selected=routingFromMetadata({canonical_url:canonical},r.body.oauth);}catch{return clean(report);}
      if(JSON.stringify(selected)!==key) {
        const selectedKey=JSON.stringify(selected);
        if(tried.has(selectedKey))return clean(report);
        tried.add(selectedKey);report=await probe(canonical,selected,nonce);
      }
      return clean(report);
    }
    // An otherwise working pretty REST form remains preferred. Policy failures
    // at its index/header/capability checks are not a reason to route around it.
    if(report.state!=='failing')return clean(report);
  }
  return clean(firstReport);
}
function clean(report) {const {response,...safe}=report;return safe;}
export function verifySiteRead(probe,site) {
  if(probe.identity_matched!==true)return {state:'failing',reason:'stale_or_uncorrelated_read',guidance:'The MCP response does not match the fresh request. Exclude OAuth/MCP responses from caching, then run Check connection again.'};
  if(probe.http<200||probe.http>=300||probe.body?.error||probe.body?.result?.isError)return {state:'failing',reason:'authenticated_read_failed',guidance};
  let info;for(const item of probe.body?.result?.content||[]){if(item.type==='text'){try{const x=JSON.parse(item.text);if(x&&typeof x==='object'&&typeof x.site_url==='string')info=x;}catch{}}}
  if(!info||info.site_url.replace(/\/$/,'')!==site.canonical_url)return {state:'failing',reason:'site_identity_mismatch',guidance:'The authenticated site identity does not match this registry URL. Check cloned-site URLs and cached REST responses, then reconnect to the intended site.'};
  if(info.connected_user?.caps?.edit_theme_options!==true)return {state:'failing',reason:'account_capability_unverified',guidance:'Authorize an account allowed to read site information (edit_theme_options). Sign in as the intended WordPress administrator and check its capabilities.'};
  return info.connected_user.caps.manage_options===true?{state:'connected',reason:'authenticated_site_read',guidance:''}:{state:'limited',reason:'non_administrator_account',guidance:'The authenticated read succeeded, but this account lacks manage_options. Administrator tools will be unavailable.'};
}
export async function observe(env,user,site,credential,verdict,observed=Date.now()) {
  await run(env,'INSERT INTO connection_observations(user_id,site_id,canonical_url,credential_id,observed_at,state,reason) VALUES(?,?,?,?,?,?,?) ON CONFLICT(user_id,site_id) DO UPDATE SET canonical_url=excluded.canonical_url,credential_id=excluded.credential_id,observed_at=excluded.observed_at,state=excluded.state,reason=excluded.reason WHERE connection_observations.observed_at<=excluded.observed_at',user,site.site_id,site.canonical_url,credential||'',observed,verdict.state,verdict.reason||'');
}
export async function currentObservation(env,user,site,credential,now=Date.now()) {
  const row=await first(env,'SELECT * FROM connection_observations WHERE user_id=? AND site_id=?',user,site.site_id);
  const maxAge=row?.state==='awaiting_confirmation'?600000:300000;
  return row&&row.canonical_url===site.canonical_url&&row.credential_id===(credential||'')&&row.observed_at<=now&&now-row.observed_at<maxAge?{state:row.state,reason:row.reason,observed_at:row.observed_at}:{state:credential?'stored_unverified':'not_connected',reason:'fresh_verification_required',observed_at:null};
}
