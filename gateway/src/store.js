import { alias, canonicalURL, Denied, endpoints, hash, open, seal, stable } from './security.js';
export const DEFAULT_SITES = [
  {site_id:'00000000-0000-4000-8000-000000000001',display_name:'Consciously Awesome',alias:'consciously-awesome',canonical_url:'https://original.example.com'},
  {site_id:'00000000-0000-4000-8000-000000000002',display_name:'Booking',alias:'booking',canonical_url:'https://booking.example.com'},
];
export const stmt=(env,sql,...args)=>env.DB.prepare(sql).bind(...args);
export const first=(env,sql,...args)=>stmt(env,sql,...args).first();
export const run=(env,sql,...args)=>stmt(env,sql,...args).run();
export async function all(env,sql,...args) { return (await stmt(env,sql,...args).all()).results; }
export async function seed(env,user) {
  const now=Date.now();
  // Idempotent inserts never replace an existing site, alias, credential or disabled state.
  await env.DB.batch(DEFAULT_SITES.flatMap(s=>[
    stmt(env,'INSERT OR IGNORE INTO sites(user_id,site_id,display_name,canonical_url,enabled,created_at,updated_at) VALUES(?,?,?,?,1,?,?)',user,s.site_id,s.display_name,s.canonical_url,now,now),
    stmt(env,'INSERT OR IGNORE INTO site_aliases(user_id,alias,site_id) VALUES(?,?,?)',user,s.alias,s.site_id),
  ]));
}
export async function resolve(env,user,selector) {
  alias(selector);
  const rows=await all(env,'SELECT DISTINCT s.* FROM sites s LEFT JOIN site_aliases a ON a.user_id=s.user_id AND a.site_id=s.site_id WHERE s.user_id=? AND (s.site_id=? OR a.alias=?)',user,selector,selector);
  if(rows.length!==1) throw new Denied('Unknown or ambiguous site. Use list_sites and supply site explicitly.');
  const s=rows[0]; if(s.enabled!==1) throw new Denied('This site is disabled.');
  endpoints(s); return s;
}
export function credentialScope(user,site,credential_id) { return {user,site_id:site.site_id,canonical_url:site.canonical_url,credential_id}; }
export async function connection(env,user,site) {
  const c=await first(env,'SELECT * FROM oauth_connections WHERE user_id=? AND site_id=?',user,site.site_id);
  if(!c || c.canonical_url!==site.canonical_url) throw new Denied('Connect this WordPress site from the gateway.',-32001);
  return c;
}
export async function saveTokens(env,user,site,client_id,t,credential_id=crypto.randomUUID()) {
  if(typeof t.access_token!=='string'||typeof t.refresh_token!=='string'||!t.access_token||!t.refresh_token) throw new Denied('OAuth token response is incomplete.',-32013);
  const z=await seal(env,{a:t.access_token,r:t.refresh_token},credentialScope(user,site,credential_id)),now=Date.now();
  await run(env,`INSERT INTO oauth_connections(user_id,site_id,credential_id,canonical_url,client_id,token_ciphertext,token_iv,expires_at,refresh_lock_until,connected_at,updated_at) VALUES(?,?,?,?,?,?,?,?,0,?,?)
    ON CONFLICT(user_id,site_id) DO UPDATE SET credential_id=excluded.credential_id,canonical_url=excluded.canonical_url,client_id=excluded.client_id,token_ciphertext=excluded.token_ciphertext,token_iv=excluded.token_iv,expires_at=excluded.expires_at,refresh_lock_until=0,updated_at=excluded.updated_at`,user,site.site_id,credential_id,site.canonical_url,client_id,z.ciphertext,z.iv,now+Math.min(86400,Math.max(1,Number(t.expires_in)||3600))*1000,now,now);
  return credential_id;
}
export async function bindApproval(env,user,site,name,args,c,data) {
  const now=Date.now(),expires=Number(data.expires)*1000;
  if(!/^[a-f0-9]{48}$/.test(data.approval_id||'') || !Number.isFinite(expires)||expires<=now||expires>now+601000) throw new Denied('Invalid upstream approval scope.',-32009);
  const url=new URL(data.approval_url);
  if(url.origin!==site.canonical_url || url.pathname!=='/wp-admin/admin.php' || url.searchParams.get('page')!=='unlimited-wp-mcp' || url.searchParams.get('approval')!==data.approval_id) throw new Denied('Approval hostname or identifier mismatch.',-32009);
  await run(env,'INSERT INTO approval_scopes(approval_hash,user_id,site_id,canonical_url,tool_name,arguments_hash,credential_id,created_at,expires_at,used) VALUES(?,?,?,?,?,?,?,?,?,0)',await hash(data.approval_id),user,site.site_id,site.canonical_url,name,await hash(stable(args)),c.credential_id,now,expires);
  return {site_id:site.site_id,canonical_url:site.canonical_url,hostname:url.hostname,tool:name,arguments_hash:await hash(stable(args)),created_at:now,expires_at:expires};
}
export async function consumeApproval(env,user,site,name,args,c,id) {
  if(typeof id!=='string'||!/^[a-f0-9]{48}$/.test(id)) throw new Denied('Invalid approval.',-32009);
  // Exact scope checks and one-use consumption occur atomically before any network request.
  const result=await run(env,`UPDATE approval_scopes SET used=1 WHERE approval_hash=? AND user_id=? AND site_id=? AND canonical_url=? AND tool_name=? AND arguments_hash=? AND credential_id=? AND expires_at>? AND used=0`,await hash(id),user,site.site_id,site.canonical_url,name,await hash(stable(args)),c.credential_id,Date.now());
  if(result.meta?.changes!==1) throw new Denied('Approval is expired, used, unknown, or belongs to another site, user, connection, tool or argument set.',-32009);
}
