# claude_jobhunt nightly config

enabled: true

## Tasks

- nightly_cleanup: true
- nightly_unit_tests: true
- nightly_playwright: true
- nightly_auto_push: true
- nightly_cost_estimate: true

## Project-specific nightly prompts

See `.claude-nightly/prompts/` for prompt files.

- `scrape_jobs.md` — runs `php scripts/scrape.php` for all active tracks
  and emails any listings with score >= 70 from the last 24h.
