CREATE TABLE IF NOT EXISTS connection_observations (
 user_id TEXT NOT NULL, site_id TEXT NOT NULL, canonical_url TEXT NOT NULL,
 credential_id TEXT NOT NULL, observed_at INTEGER NOT NULL, state TEXT NOT NULL, reason TEXT NOT NULL,
 PRIMARY KEY(user_id,site_id)
);
