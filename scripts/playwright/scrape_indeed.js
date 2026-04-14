#!/usr/bin/env node
/**
 * Indeed scraper. Reads JSON config from argv[2]:
 *   { keywords, locations, salary_floor, remote_ok }
 *
 * Emits log lines to stderr/stdout, then a final stdout line containing
 * a JSON array of normalized listings. The PHP wrapper reads the LAST
 * line of stdout and parses it.
 *
 * NOTE: Indeed actively blocks bots. This is a best-effort scraper that
 * uses Playwright with stealth-style settings. Expect breakage; design
 * downstream code to tolerate empty results.
 *
 * Usage (manual):
 *   node scrape_indeed.js '{"keywords":"php developer","locations":"Buffalo, NY","remote_ok":true}'
 */
const { chromium } = require('playwright');

(async () => {
  const cfg = JSON.parse(process.argv[2] || '{}');
  const keywords  = (cfg.keywords  || '').split(',')[0].trim() || 'developer';
  const location  = (cfg.locations || '').split(',')[0].trim() || 'Remote';
  const remoteOk  = !!cfg.remote_ok;

  const url = `https://www.indeed.com/jobs?q=${encodeURIComponent(keywords)}&l=${encodeURIComponent(location)}` +
              (remoteOk ? '&sc=0kf%3Aattr%28DSQF7%29%3B' : '');

  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    viewport: { width: 1366, height: 800 },
  });
  const page = await ctx.newPage();
  const results = [];

  try {
    console.error(`[indeed] GET ${url}`);
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(2000 + Math.random() * 2000);

    const cards = await page.$$('div.job_seen_beacon, a.tapItem, [data-jk]');
    console.error(`[indeed] ${cards.length} card candidates`);

    for (const card of cards.slice(0, 25)) {
      try {
        const title    = (await card.$eval('h2 a span, h2 span', el => el.textContent).catch(() => '')).trim();
        const company  = (await card.$eval('[data-testid="company-name"], .companyName', el => el.textContent).catch(() => '')).trim();
        const loc      = (await card.$eval('[data-testid="text-location"], .companyLocation', el => el.textContent).catch(() => '')).trim();
        const salary   = (await card.$eval('.salary-snippet-container, .estimated-salary, [data-testid="attribute_snippet_testid"]', el => el.textContent).catch(() => '')).trim();
        const desc     = (await card.$eval('.job-snippet, [data-testid="job-snippet"]', el => el.textContent).catch(() => '')).trim();
        const jk       = await card.getAttribute('data-jk').catch(() => null);
        const href     = jk ? `https://www.indeed.com/viewjob?jk=${jk}` : null;

        if (!title || !company) continue;
        results.push({
          source_id: jk,
          source_url: href,
          title, company,
          location: loc,
          is_remote: /remote/i.test(loc) ? 1 : 0,
          salary_text: salary || null,
          description: desc,
        });
      } catch (e) {
        console.error('[indeed] card parse failed:', e.message);
      }
    }
  } catch (e) {
    console.error('[indeed] FATAL:', e.message);
  } finally {
    await browser.close();
  }

  // Last line of stdout = JSON results
  process.stdout.write('\n' + JSON.stringify(results) + '\n');
})();
