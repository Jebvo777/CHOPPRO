const { chromium } = require(process.env.PORTAL_PLAYWRIGHT_PATH || 'playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  const origin = process.env.PORTAL_TEST_URL || 'http://127.0.0.1:8090';
  const out = process.env.PORTAL_SCREENSHOTS || 'dist/screenshots';
  fs.mkdirSync(out, {recursive: true});
  const browser = await chromium.launch({headless: true});
  const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  for (const section of ['overview','docs','diagrams','requirements','stories','acceptance','prototypes','github','openapi','delivery']) {
    const response = await page.goto(origin + '/?page=' + section);
    if (response.status() !== 200) throw new Error('HTTP ' + response.status() + ' for ' + section);
    await page.locator('h1').first().waitFor();
    await page.screenshot({path: path.join(out, section + '.png'), fullPage: section !== 'requirements' && section !== 'github'});
  }
  await page.goto(origin + '/?page=docs');
  const before = await page.locator('[data-doc]:visible').count();
  await page.locator('[data-doc-search]').fill('завершении');
  const after = await page.locator('[data-doc]:visible').count();
  if (!(after > 0 && after < before)) throw new Error('Document search did not filter documents');
  await page.goto(origin + '/?page=requirements');
  await page.locator('[data-req-search]').fill('FR-RBAC-001');
  if (await page.locator('[data-req-row]:visible').count() !== 1) throw new Error('Requirement search failed');
  await page.goto(origin + '/?page=acceptance');
  const checkbox = page.locator('[data-accept]').first();
  await checkbox.check();
  await page.reload();
  if (!(await checkbox.isChecked())) throw new Error('Acceptance state was not preserved');
  await page.goto(origin + '/?page=prototypes&app=admin&screen=dashboard');
  const frame = page.frameLocator('iframe');
  await frame.locator('#app').waitFor();
  await frame.locator('[data-nav="admin:employees"]').first().click();
  await page.waitForTimeout(250);
  if (!(await frame.locator('#app').innerText()).includes('Сотрудники')) throw new Error('Prototype navigation failed');
  await page.goto(origin + '/?page=prototypes&story=UC-02&step=2');
  if (!(await page.locator('.story-card').first().innerText()).includes('Шаг 2')) throw new Error('Guided scenario did not load');
  await page.setViewportSize({width: 390, height: 844});
  await page.goto(origin + '/?page=overview');
  await page.screenshot({path: path.join(out, 'mobile-overview.png'), fullPage: true});
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2);
  if (overflow) throw new Error('Mobile layout overflows the viewport');
  for (const url of ['/config.php','/bootstrap/index.json','/storage/state.json','/app/Repository.php']) {
    const response = await context.request.get(origin + url);
    if (response.status() !== 403) throw new Error('Protected path is accessible: ' + url);
  }
  const traversal = await context.request.get(origin + '/file.php?path=../config.php&ref=' + 'a'.repeat(40));
  if (traversal.status() !== 404) throw new Error('Traversal request was not blocked');
  if (errors.length) throw new Error('Browser errors: ' + errors.join('; '));
  await browser.close();
  console.log('PASS browser pages, search, acceptance persistence, prototype navigation, scenario, mobile layout, access controls');
})().catch(error => {console.error(error); process.exit(1);});
