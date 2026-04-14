#!/usr/bin/env node
// Monster scraper
const { chromium } = require('playwright');
(async () => {
  const cfg = JSON.parse(process.argv[2] || '{}');
  const q = (cfg.keywords || '').split(',')[0].trim() || 'developer';
  const where = (cfg.locations || '').split(',')[0].trim() || 'Remote';
  const url = `https://www.monster.com/jobs/search?q=${encodeURIComponent(q)}&where=${encodeURIComponent(where)}`;
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ userAgent: 'Mozilla/5.0 Chrome/120 Safari/537.36' })).newPage();
  const results = [];
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(2500);
    const cards = await page.$$('article[data-testid="JobCard"], .job-cardstyle__JobCardComponent-sc-1mbmxes-0');
    for (const c of cards.slice(0, 25)) {
      try {
        const title   = (await c.$eval('h3, [data-testid="jobTitle"]', el => el.textContent).catch(() => '')).trim();
        const company = (await c.$eval('[data-testid="company"]', el => el.textContent).catch(() => '')).trim();
        const loc     = (await c.$eval('[data-testid="jobDetailLocation"]', el => el.textContent).catch(() => '')).trim();
        const href    = await c.$eval('a', el => el.href).catch(() => null);
        if (!title || !company) continue;
        results.push({ source_url: href, title, company, location: loc, is_remote: /remote/i.test(loc)?1:0, salary_text: null, description: '' });
      } catch (e) { console.error('[monster] card:', e.message); }
    }
  } catch (e) { console.error('[monster] FATAL:', e.message); }
  finally { await browser.close(); }
  process.stdout.write('\n' + JSON.stringify(results) + '\n');
})();
