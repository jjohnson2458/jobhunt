#!/usr/bin/env node
// ZipRecruiter scraper — same shape as scrape_indeed.js
const { chromium } = require('playwright');
(async () => {
  const cfg = JSON.parse(process.argv[2] || '{}');
  const keywords = (cfg.keywords || '').split(',')[0].trim() || 'developer';
  const location = (cfg.locations || '').split(',')[0].trim() || 'Remote';
  const url = `https://www.ziprecruiter.com/jobs-search?search=${encodeURIComponent(keywords)}&location=${encodeURIComponent(location)}`;
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ userAgent: 'Mozilla/5.0 Chrome/120 Safari/537.36' })).newPage();
  const results = [];
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(2000);
    const cards = await page.$$('article.job_result, [data-testid="job-card"]');
    for (const c of cards.slice(0, 25)) {
      try {
        const title   = (await c.$eval('h2, [data-testid="job-title"]', el => el.textContent).catch(() => '')).trim();
        const company = (await c.$eval('[data-testid="company-name"], a.company_name', el => el.textContent).catch(() => '')).trim();
        const loc     = (await c.$eval('[data-testid="location"], .location', el => el.textContent).catch(() => '')).trim();
        const salary  = (await c.$eval('.compensation, [data-testid="salary"]', el => el.textContent).catch(() => '')).trim();
        const desc    = (await c.$eval('[data-testid="job-snippet"], .description', el => el.textContent).catch(() => '')).trim();
        const href    = await c.$eval('a[href*="/jobs/"]', el => el.href).catch(() => null);
        if (!title || !company) continue;
        results.push({ source_url: href, title, company, location: loc, is_remote: /remote/i.test(loc)?1:0, salary_text: salary || null, description: desc });
      } catch (e) { console.error('[zip] card:', e.message); }
    }
  } catch (e) { console.error('[zip] FATAL:', e.message); }
  finally { await browser.close(); }
  process.stdout.write('\n' + JSON.stringify(results) + '\n');
})();
