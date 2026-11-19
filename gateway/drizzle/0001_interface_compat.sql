-- Pending native approval IDs are encrypted, bound to the existing grant and exact translated arguments.
CREATE TABLE IF NOT EXISTS compat_pending (
 request_hash TEXT PRIMARY KEY NOT NULL,
 approval_ciphertext TEXT NOT NULL,
 approval_iv TEXT NOT NULL,
 expires_at INTEGER NOT NULL
);
