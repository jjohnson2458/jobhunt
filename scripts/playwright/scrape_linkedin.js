#!/usr/bin/env node
/**
 * LinkedIn scraper — uses the public guest jobs search.
 * LinkedIn aggressively shows a login wall after a few clicks.
 * Expect partial results. If blocked, returns []. Do not log in.
 */
const { chromium } = require('playwright');
(async () => {
  const cfg = JSON.parse(process.argv[2] || '{}');
  const q = (cfg.keywords || '').split(',')[0].trim() || 'developer';
  const where = (cfg.locations || '').split(',')[0].trim() || 'United States';
  const remote = cfg.remote_ok ? '&f_WT=2' : '';
  const url = `https://www.linkedin.com/jobs/search?keywords=${encodeURIComponent(q)}&location=${encodeURIComponent(where)}${remote}`;
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ userAgent: 'Mozilla/5.0 Chrome/120 Safari/537.36' })).newPage();
  const results = [];
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(3000);
    if (page.url().includes('/authwall') || page.url().includes('/login')) {
      console.error('[linkedin] blocked by authwall');
      await browser.close();
      process.stdout.write('\n[]\n');
      return;
    }
    const cards = await page.$$('div.base-card, li.jobs-search__results-list-item');
    for (const c of cards.slice(0, 25)) {
      try {
        const title   = (await c.$eval('h3, .base-search-card__title', el => el.textContent).catch(() => '')).trim();
        const company = (await c.$eval('h4, .base-search-card__subtitle', el => el.textContent).catch(() => '')).trim();
        const loc     = (await c.$eval('.job-search-card__location', el => el.textContent).catch(() => '')).trim();
        const href    = await c.$eval('a.base-card__full-link, a', el => el.href).catch(() => null);
        if (!title || !company) continue;
        results.push({ source_url: href, title, company, location: loc, is_remote: /remote/i.test(loc)?1:0, salary_text: null, description: '' });
      } catch (e) { console.error('[linkedin] card:', e.message); }
    }
  } catch (e) { console.error('[linkedin] FATAL:', e.message); }
  finally { await browser.close(); }
  process.stdout.write('\n' + JSON.stringify(results) + '\n');
})();
