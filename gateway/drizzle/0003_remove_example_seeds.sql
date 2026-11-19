-- Retire only untouched, unauthorized legacy demonstration records.
-- D1 runs each migration transactionally. Do not add BEGIN/COMMIT here.
-- Anything with a client, flow, grant, approval or renamed identity is preserved
-- for operator review; this migration never attempts external revocation.
DELETE FROM site_aliases WHERE EXISTS (
 SELECT 1 FROM sites s WHERE s.user_id=site_aliases.user_id AND s.site_id=site_aliases.site_id
 AND ((s.site_id='00000000-0000-4000-8000-000000000001' AND s.canonical_url='https://original.example.com' AND s.display_name='Consciously Awesome')
   OR (s.site_id='00000000-0000-4000-8000-000000000002' AND s.canonical_url='https://booking.example.com' AND s.display_name='Booking'))
 AND NOT EXISTS (SELECT 1 FROM oauth_connections c WHERE c.user_id=s.user_id AND c.site_id=s.site_id)
 AND NOT EXISTS (SELECT 1 FROM oauth_clients c WHERE c.user_id=s.user_id AND c.site_id=s.site_id)
 AND NOT EXISTS (SELECT 1 FROM oauth_flows f WHERE f.user_id=s.user_id AND f.site_id=s.site_id)
 AND NOT EXISTS (SELECT 1 FROM approval_scopes a WHERE a.user_id=s.user_id AND a.site_id=s.site_id)
);
DELETE FROM sites WHERE
 ((site_id='00000000-0000-4000-8000-000000000001' AND canonical_url='https://original.example.com' AND display_name='Consciously Awesome')
   OR (site_id='00000000-0000-4000-8000-000000000002' AND canonical_url='https://booking.example.com' AND display_name='Booking'))
 AND NOT EXISTS (SELECT 1 FROM site_aliases a WHERE a.user_id=sites.user_id AND a.site_id=sites.site_id)
 AND NOT EXISTS (SELECT 1 FROM oauth_connections c WHERE c.user_id=sites.user_id AND c.site_id=sites.site_id)
 AND NOT EXISTS (SELECT 1 FROM oauth_clients c WHERE c.user_id=sites.user_id AND c.site_id=sites.site_id)
 AND NOT EXISTS (SELECT 1 FROM oauth_flows f WHERE f.user_id=sites.user_id AND f.site_id=sites.site_id)
 AND NOT EXISTS (SELECT 1 FROM approval_scopes a WHERE a.user_id=sites.user_id AND a.site_id=sites.site_id);
