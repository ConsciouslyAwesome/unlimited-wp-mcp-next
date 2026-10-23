CREATE TABLE IF NOT EXISTS sites (
 user_id TEXT NOT NULL, site_id TEXT NOT NULL, display_name TEXT NOT NULL,
 canonical_url TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 1,
 created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL,
 PRIMARY KEY(user_id,site_id), UNIQUE(user_id,canonical_url)
);
CREATE TABLE IF NOT EXISTS site_aliases (
 user_id TEXT NOT NULL, alias TEXT NOT NULL, site_id TEXT NOT NULL,
 PRIMARY KEY(user_id,alias), FOREIGN KEY(user_id,site_id) REFERENCES sites(user_id,site_id)
);
CREATE INDEX IF NOT EXISTS aliases_site ON site_aliases(user_id,site_id);
CREATE TABLE IF NOT EXISTS oauth_clients (
 user_id TEXT NOT NULL, site_id TEXT NOT NULL, client_id TEXT NOT NULL,
 redirect_uri TEXT NOT NULL, created_at INTEGER NOT NULL,
 PRIMARY KEY(user_id,site_id)
);
CREATE TABLE IF NOT EXISTS oauth_flows (
 state_hash TEXT PRIMARY KEY NOT NULL, user_id TEXT NOT NULL, site_id TEXT NOT NULL,
 canonical_url TEXT NOT NULL, client_id TEXT NOT NULL, redirect_uri TEXT NOT NULL,
 verifier_ciphertext TEXT NOT NULL, verifier_iv TEXT NOT NULL, created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS flows_user ON oauth_flows(user_id,site_id);
CREATE TABLE IF NOT EXISTS oauth_connections (
 user_id TEXT NOT NULL, site_id TEXT NOT NULL, credential_id TEXT NOT NULL UNIQUE,
 canonical_url TEXT NOT NULL, client_id TEXT NOT NULL, token_ciphertext TEXT NOT NULL,
 token_iv TEXT NOT NULL, expires_at INTEGER NOT NULL, refresh_lock_until INTEGER NOT NULL DEFAULT 0,
 connected_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, PRIMARY KEY(user_id,site_id)
);
CREATE TABLE IF NOT EXISTS approval_scopes (
 approval_hash TEXT PRIMARY KEY NOT NULL, user_id TEXT NOT NULL, site_id TEXT NOT NULL,
 canonical_url TEXT NOT NULL, tool_name TEXT NOT NULL, arguments_hash TEXT NOT NULL,
 credential_id TEXT NOT NULL, created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL,
 used INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS approvals_expiry ON approval_scopes(expires_at);
CREATE TABLE IF NOT EXISTS bridge_diagnostics (
 id INTEGER PRIMARY KEY AUTOINCREMENT, timestamp INTEGER NOT NULL,
 user_id TEXT NOT NULL, site_id TEXT NOT NULL, tool_name TEXT NOT NULL,
 status TEXT NOT NULL, upstream_http_status INTEGER
);
CREATE INDEX IF NOT EXISTS diagnostics_site ON bridge_diagnostics(user_id,site_id,timestamp);
