const E = new TextEncoder(), D = new TextDecoder();
export class Denied extends Error {
  constructor(message, code = -32602) { super(message); this.code = code; }
}
export function canonicalURL(value) {
  if (typeof value !== 'string' || !/^https:\/\/[a-zA-Z0-9.-]+\/?$/.test(value)) throw new Denied('Use a public HTTPS hostname without a path or port.');
  const u = new URL(value), host = u.hostname;
  if (!host.includes('.') || host.endsWith('.') || host.split('.').some(x => !/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/.test(x)) || /(?:^|\.)(?:localhost|local|internal|test|invalid|example)$/.test(host) || /^[\d.]+$/.test(host)) throw new Denied('Private or malformed hostnames are forbidden.');
  return u.origin;
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
// Every endpoint is derived from the registry. Discovery responses cannot replace endpoints.
export function endpoints(site) {
  const origin=canonicalURL(site.canonical_url);
  if(origin!==site.canonical_url) throw new Denied('Registry URL is not canonical.');
  const issuer=origin+'/wp-json/unlimited-wp-mcp-next/v1/oauth';
  return {origin,issuer,mcp:origin+'/wp-json/unlimited-wp-mcp-next/v1/mcp',register:issuer+'/register-path',token:issuer+'/token',refresh:origin+'/?rest_route=/unlimited-wp-mcp-next/v1/oauth/token',revoke:issuer+'/revoke',authorize:origin+'/wp-admin/admin-post.php?action=uwnext_authorize'};
}
export async function fetchJSON(url, init={}) {
  // Never follow redirects, including redirects carrying a bearer token.
  const r=await fetch(url,{...init,redirect:'manual',headers:{accept:'application/json',...init.headers}});
  if(r.status>=300 && r.status<400) throw new Denied('WordPress redirect refused.',-32015);
  let body; try { body=await r.json(); } catch { throw new Denied('WordPress returned an invalid response.',-32016); }
  return {body,http:r.status};
}
