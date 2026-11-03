# Theme workflow

Read site_info, theme files and classic_theme_safety. Create a draft through its
approval gate. Edit draft files with syntax checks, path containment, byte limits
and SVG sanitization. Preview the draft. For Tailwind, read compile sources,
compile the supplied local inputs with an authorized local compiler, and provide
the source hash with generated output. Publishing needs a fresh snapshot-bound
browser approval and a successful render health check. Failed or unavailable
health checks roll back. Production publishing needs explicit human authorization.
