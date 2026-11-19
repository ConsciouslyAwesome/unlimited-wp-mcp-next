const E = new TextEncoder(), D = new TextDecoder();
export class Denied extends Error {
  constructor(message, code = -32602) { super(message); this.code = code; }
}
export function canonicalURL(value) {
  if (typeof value !== 'string' || value.length>2048 || !/^https:\/\/[a-zA-Z0-9.-]+(?:\/[A-Za-z0-9._~-]+)*\/?$/.test(value) || value.split('/').slice(3).some(p=>p==='.'||p==='..')) throw new Denied('Use a public HTTPS WordPress address with a clean installation path and no port, query or fragment.');
  const u = new URL(value), host = u.hostname;
  if (!host.includes('.') || host.endsWith('.') || host.split('.').some(x => !/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/.test(x)) || /(?:^|\.)(?:localhost|local|internal|test|invalid|example)$/.test(host) || /^[\d.]+$/.test(host)) throw new Denied('Private or malformed hostnames are forbidden.');
  return u.origin+u.pathname.replace(/\/$/,'');
}
export function alias(value) {
  if (typeof value !== 'string' || !/^[a-z0-9][a-z0-9-]{0,62}$/.test(value)) throw new Denied('Use a registered site ID or lowercase alias.');
  return value;
}
export function stable(value) {
  if (Array.isArray(value)) return '[' + value.map(stable).join(',') + ']';
  if (value && typeof value === 'object') return '{' + Object.keys(value).sort().map(k => JSON.stringify(k)+':'+stable(value[k])).join(',') + '}';
  return JSON.stringify(value);
}
export function b64(bytes) { let s=''; for(const x of bytes) s+=String.fromCharCode(x); return btoa(s); }
function bytes(s) { return Uint8Array.from(atob(s), c=>c.charCodeAt(0)); }
export function random() { return b64(crypto.getRandomValues(new Uint8Array(32))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,''); }
export async function hash(s) { return b64(new Uint8Array(await crypto.subtle.digest('SHA-256', E.encode(s)))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,''); }
async function key(env) {
  const raw = bytes(env.OAUTH_ENCRYPTION_KEY || '');
  if(raw.length!==32) throw new Denied('Gateway encryption is not configured.', -32603);
  return crypto.subtle.importKey('raw',raw,'AES-GCM',false,['encrypt','decrypt']);
}
export async function seal(env, value, scope) {
  const iv=crypto.getRandomValues(new Uint8Array(12));
  return {ciphertext:b64(new Uint8Array(await crypto.subtle.encrypt({name:'AES-GCM',iv,additionalData:E.encode(stable(scope))},await key(env),E.encode(JSON.stringify(value))))),iv:b64(iv)};
}
export async function open(env, ciphertext, iv, scope) {
  try { return JSON.parse(D.decode(await crypto.subtle.decrypt({name:'AES-GCM',iv:bytes(iv),additionalData:E.encode(stable(scope))},await key(env),bytes(ciphertext)))); }
  catch { throw new Denied('Credential scope or encryption validation failed.', -32013); }
}
// Only standard same-origin REST forms at the installation or an ancestor home
// path are allowed. Discovery cannot supply arbitrary URLs or sibling-site paths.
export const REST_FORMS=['pretty','plain','plain-index','pretty-index'];
export function endpoints(site,routing) {
  const canonical=canonicalURL(site.canonical_url);
  if(canonical!==site.canonical_url) throw new Denied('Registry URL is not canonical.');
  const profile=routing||{rest_base:canonical,rest_form:'pretty'};
  if(!profile||typeof profile!=='object'||Array.isArray(profile)||Object.keys(profile).some(k=>!['rest_base','rest_form'].includes(k)))throw new Denied('Invalid stored REST routing.');
  const base=canonicalURL(profile.rest_base),origin=new URL(canonical).origin;
  if(base!==profile.rest_base||new URL(base).origin!==origin||!(canonical===base||canonical.startsWith(base+'/'))||!REST_FORMS.includes(profile.rest_form))throw new Denied('REST routing is outside this WordPress installation.');
  const plain=profile.rest_form.startsWith('plain');
  const rest=route=>plain?base+(profile.rest_form==='plain-index'?'/index.php':'/')+'?rest_route='+route:base+(profile.rest_form==='pretty-index'?'/index.php':'')+'/wp-json'+route;
  const oauth='/unlimited-wp-mcp-next/v1/oauth';
  return {origin,issuer:plain?canonical:rest(oauth),mcp:rest('/unlimited-wp-mcp-next/v1/mcp'),register:rest(oauth+'/register-path'),token:rest(oauth+'/token'),refresh:plain?rest(oauth+'/token'):base+'/?rest_route='+oauth+'/token',revoke:rest(oauth+'/revoke'),authorize:canonical+'/wp-admin/admin-post.php?action=uwnext_authorize',index:rest('/'),preflight:rest('/unlimited-wp-mcp-next/v1/connection/preflight'),routing:{rest_base:base,rest_form:profile.rest_form}};
}
export function routingFromMetadata(site,metadata) {
  const canonical=canonicalURL(site.canonical_url),url=new URL(canonical),parts=url.pathname.split('/').filter(Boolean);
  // The home address may be above the WordPress directory, but never another host
  // or a sibling installation. Compare complete generated contracts, not prefixes.
  for(let n=parts.length;n>=0;n--)for(const rest_form of REST_FORMS) {
    const rest_base=url.origin+(n?'/'+parts.slice(0,n).join('/'):'');
    const ep=endpoints(site,{rest_base,rest_form});
    if(metadata?.issuer===ep.issuer&&metadata.token_endpoint===ep.token&&(!metadata.registration_endpoint||metadata.registration_endpoint===ep.register)&&(!metadata.revocation_endpoint||metadata.revocation_endpoint===ep.revoke)&&(!metadata.authorization_endpoint||metadata.authorization_endpoint===ep.authorize))return ep.routing;
  }
  throw new Denied('WordPress OAuth endpoints do not match this registered installation.');
}
export async function fetchJSON(url, init={}) {
  // Never follow redirects, including redirects carrying a bearer token.
  const r=await fetch(url,{...init,redirect:'manual',headers:{accept:'application/json',...init.headers}});
  if(r.status>=300 && r.status<400) throw new Denied('WordPress redirect refused.',-32015);
  let body; try { body=await r.json(); } catch { throw new Denied('WordPress returned an invalid response.',-32016); }
  return {body,http:r.status};
}
