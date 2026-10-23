# Builder workflow

Identify installed builders, then read their schemas and current page data.
Preserve unrelated layout and styles. Use owning builder APIs through the local
approval gate. SeedProd compile-login URLs are short-lived credentials: request
only for the intended page, keep them out of logs/chat and use them only in an
authorized builder session. They grant no other admin action. Read back the page
and rendered output; stored layout data alone does not prove compilation.
