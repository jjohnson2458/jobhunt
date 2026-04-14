# Nightly job scrape

1. Run `php scripts/scrape.php` from the project root.
2. Query `listings` for rows where `score >= 70` AND `fetched_at >= NOW() - INTERVAL 1 DAY` AND `status = 'new'`.
3. If any rows exist, build a short HTML digest (title, company, location, score, link) and email it via:

```
php C:\xampp\htdocs\claude_messenger\notify.php \
  --subject "Jobhunt: N new high-score listings" \
  --body "<HTML>" \
  --project claude_jobhunt
```

4. If `scraper_runs.status='failed'` for any run in the last 24h, include the error in the email and tag the subject `[FAILED]`.
