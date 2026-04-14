const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' })).newPage();
  await page.goto('https://www.ziprecruiter.com/jobs-search?search=php+developer&location=Buffalo%2C+NY', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(3000);
  console.log('FINAL URL:', page.url());
  console.log('TITLE:', await page.title());
  console.log('BODY (first 600):', (await page.evaluate(() => document.body.innerText)).slice(0, 600));
  console.log('article.job_result:', (await page.$$('article.job_result')).length);
  console.log('[data-testid=job-card]:', (await page.$$('[data-testid="job-card"]')).length);
  console.log('any article:', (await page.$$('article')).length);
  console.log('div[class*="job"]:', (await page.$$('div[class*="job"]')).length);
  await browser.close();
})();
