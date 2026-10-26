# Native command workflow

Use cli_run with `help` to discover native commands; no shell is invoked. Every
command, including SQL reads, needs local browser approval. Protected options,
identity/audit tables and file safeguards remain enforced afterward. Prefer
native WordPress commands to raw SQL. Review destructive snapshots. Set detach
before requesting approval for long commands; changing it invalidates approval.
Poll operation_receipt for queued/started/completed/failed state. Never retry an
uncertain mutation or consumed approval automatically.
