// Test-only example registry. Never imported by production source.
import {stmt} from "../src/store.js";
export const DEFAULT_SITES = [
  {site_id:'00000000-0000-4000-8000-000000000001',display_name:'Consciously Awesome',alias:'consciously-awesome',canonical_url:'https://original.example.com'},
  {site_id:'00000000-0000-4000-8000-000000000002',display_name:'Booking',alias:'booking',canonical_url:'https://booking.example.com'},
];
export async function seed(env,user) {
  const now=Date.now();
  // Idempotent inserts never replace an existing site, alias, credential or disabled state.
  await env.DB.batch(DEFAULT_SITES.flatMap(s=>[
    stmt(env,'INSERT OR IGNORE INTO sites(user_id,site_id,display_name,canonical_url,enabled,created_at,updated_at) VALUES(?,?,?,?,1,?,?)',user,s.site_id,s.display_name,s.canonical_url,now,now),
    stmt(env,'INSERT OR IGNORE INTO site_aliases(user_id,alias,site_id) VALUES(?,?,?)',user,s.alias,s.site_id),
  ]));
}
