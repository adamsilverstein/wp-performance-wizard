# Bundled expert skills

The Markdown files under this directory are vendored "agent skills" — short,
authoritative playbooks used as reference context for the AI agents that back
the Performance Wizard. They were copied verbatim from upstream sources and
are redistributed under their original licenses.

## Sources

### `web-quality/`

Copied from [addyosmani/web-quality-skills](https://github.com/addyosmani/web-quality-skills)
at commit `fed9617111260e19f4f54b72a2874a3f3de8ff94`.

- `web-quality/performance.md` — from `skills/performance/SKILL.md`
- `web-quality/core-web-vitals.md` — from `skills/core-web-vitals/SKILL.md`
- `web-quality/best-practices.md` — from `skills/best-practices/SKILL.md`

License: **MIT** — Copyright (c) 2026 Addy Osmani. See
<https://github.com/addyosmani/web-quality-skills/blob/fed9617111260e19f4f54b72a2874a3f3de8ff94/LICENSE>.

### `wordpress/`

Copied from [WordPress/agent-skills](https://github.com/WordPress/agent-skills)
at commit `c5c0697b120ec00e8fcf6a265f161c61dbc2581c`.

- `wordpress/wp-performance.md` — from `skills/wp-performance/SKILL.md`
- `wordpress/wp-plugin-development.md` — from `skills/wp-plugin-development/SKILL.md`

License: **GPL-2.0-or-later** — Copyright (C) 2026 WordPress Contributors. See
<https://github.com/WordPress/agent-skills/blob/c5c0697b120ec00e8fcf6a265f161c61dbc2581c/LICENSE>.

## Refreshing

To refresh these files to a newer upstream version, re-copy from the source
repositories and update the commit SHAs above. Prefer verbatim copies so
attribution stays clean.
