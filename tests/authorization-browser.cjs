// Chrome and real MVC; disposable SQLite RAM fixture only, no application database.
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright-core');
const { spawn } = require('node:child_process');
const fs = require('node:fs'), path = require('node:path'), net = require('node:net');
const root = path.resolve(__dirname, '..'), out = path.join(root, 'storage/temp/authorization-review/browser');
fs.mkdirSync(path.join(out, 'sessions'), { recursive: true });
const report = { checks: [], failures: [], mode: 'Real Chrome/MVC; SQLite :memory: only' };
const check = (ok, label) => { report.checks.push({ ok, label }); if (!ok) throw new Error(label); };
(async () => {
  const socket = net.createServer(); await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}/index.php`;
  const url = (route, query = {}) => base + '?' + new URLSearchParams({ route, ...query });
  const server = spawn(process.env.PHP_BINARY || 'D:/PHP/php/php.exe', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/fixtures/admin-navigation.php'], {
    cwd: root, windowsHide: true, env: { ...process.env, STRYKE_NAVIGATION_TEST: '1', STRYKE_AUTHORIZATION_TEST: '1', STRYKE_NAVIGATION_SESSIONS: path.join(out, 'sessions') }, stdio: ['ignore', 'pipe', 'pipe'],
  });
  let log = '', browser;
  server.stdout.on('data', chunk => log += chunk); server.stderr.on('data', chunk => log += chunk);
  try {
    for (let attempt = 0; ; attempt++) {
      if (await new Promise(resolve => { const connection = net.connect(port, '127.0.0.1'); connection.once('connect', () => { connection.destroy(); resolve(true); }); connection.once('error', () => resolve(false)); })) break;
      if (attempt > 100) throw new Error('PHP fixture unavailable'); await new Promise(resolve => setTimeout(resolve, 50));
    }
    browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    const context = await browser.newContext({ reducedMotion: 'reduce' }), page = await context.newPage();
    page.on('pageerror', error => report.failures.push(error.message));
    // Share the identity with both page navigation and APIRequestContext POSTs.
    const as = role => context.setExtraHTTPHeaders({ 'X-Stryke-Test-Role': role });
    const visit = async (route, params) => {
      const response = await page.goto(url(route, params));
      check(response.ok(), `GET ${route}: HTTP ${response.status()}`);
    };
    const submit = async () => {
      await Promise.all([page.waitForNavigation(), page.locator('.admin-workspace form[method="post"] button[type="submit"]').first().click()]);
      check(await page.locator('[role="status"]').count() > 0, 'authorized form POST redirects with success message');
    };
    await page.request.get(base + '?fixture=reset&count=120');
    await as('QUAN_LY_CONG_TY');
    await visit('/company/co-so/create');
    await page.locator('[name="ten_co_so"]').fill('Authorized company facility');
    await page.locator('[name="dia_chi"]').fill('Memory fixture address');
    await page.selectOption('#ma_tinh', await page.locator('#ma_tinh option').nth(1).getAttribute('value'));
    await page.waitForFunction(() => [...document.querySelectorAll('#ma_phuong_xa option')].some(option => option.value && !option.disabled && !option.parentElement.hidden));
    const ward = await page.locator('#ma_phuong_xa').evaluate(select => [...select.options].find(option => option.value && !option.disabled && !option.parentElement.hidden).value);
    await page.selectOption('#ma_phuong_xa', ward);
    await page.locator('[name="gio_mo_cua"]').fill('06:00'); await page.locator('[name="gio_dong_cua"]').fill('23:00');
    await submit();
    check(new URL(page.url()).searchParams.get('route') === '/company/co-so', 'company facility create returns to own area');
    await visit('/company/san/create');
    await page.selectOption('[name="ma_co_so"]', '1'); await page.selectOption('[name="ma_loai_san"]', '2');
    await page.locator('[name="ten_san"]').fill('Authorized owned court');
    await submit();
    const courtRow = page.locator('tbody tr').filter({ hasText: 'Authorized owned court' });
    const courtId = new URL(await courtRow.locator('.list-action--view').getAttribute('href'), base).searchParams.get('id');
    await visit('/company/bang-gia/create');
    await page.selectOption('[name="ma_co_so"]', '1'); await page.selectOption('[name="ma_loai_san"]', '2');
    await page.locator('[name="ten_bang_gia"]').fill('Authorized owned price');
    await page.selectOption('[name="loai_ngay"]', 'NGAY_THUONG');
    await page.locator('[name="gio_bat_dau"]').fill('10:00'); await page.locator('[name="gio_ket_thuc"]').fill('11:00');
    await page.locator('#don_gia').fill('50000');
    await page.locator('[name="ngay_bat_dau"]').fill('2025-01-01');
    await submit();
    const priceRow = page.locator('tbody tr').filter({ hasText: 'Authorized owned price' });
    await priceRow.locator('[data-row-actions] summary').click();
    const priceId = new URL(await priceRow.locator('.list-action--view').getAttribute('href'), base).searchParams.get('id');
    const identities = [['QUAN_TRI_TAP_DOAN', '/admin'], ['QUAN_LY_CONG_TY', '/company'], ['QUAN_LY_CO_SO', '/facility'], ['NHAN_VIEN', '/staff']];
    for (const [role, home] of identities) {
      await as(role);
      for (const width of [1440, 1024, 768, 375]) {
        await page.setViewportSize({ width, height: 900 });
        for (const [module, id] of [['co-so', '1'], ['san', courtId], ['bang-gia', priceId]]) {
          await visit(home + '/' + module);
          check(await page.locator('.admin-sidebar [aria-current="page"]').count() === 1, `${role}/${module}/${width}: sidebar active is unique`);
          check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${role}/${module}/${width}: no document overflow`);
          if (role === 'NHAN_VIEN') {
            check(await page.locator('.admin-workspace .list-action--edit, .admin-workspace .list-action--delete, .module-heading a[href*="create"]').count() === 0, `${role}/${module}/${width}: read-only controls`);
          }
          if (role !== 'QUAN_TRI_TAP_DOAN') check(await page.locator('.list-action--delete').count() === 0, `${role}/${module}: no hard delete`);
          if (width === 1440 || width === 375) await page.screenshot({ path: path.join(out, `${role}-${module}-${width}.png`) });
          await visit(home + '/' + module + '/show', { id });
          check(await page.locator('.detail-edit').count() === (role === 'NHAN_VIEN' ? 0 : 1), `${role}/${module}: detail respects write permission`);
          if (role === 'NHAN_VIEN') check(await page.locator('.admin-workspace a[href*="create"], .admin-workspace a[href*="/edit"], .admin-workspace a[href*="/delete"], .admin-workspace form[method="post"]').count() === 0, `staff ${module}: details expose no mutation controls`);
          if (role !== 'QUAN_TRI_TAP_DOAN') {
            const foreign = await page.request.get(url(home + '/' + module + '/show', { id: '3' }));
            check([403, 404].includes(foreign.status()), `${role}/${module}: foreign direct URL blocked`);
          }
        }
      }
    }
    await as('QUAN_LY_CO_SO');
    await visit('/facility/san/edit', { id: courtId });
    await page.locator('[name="ten_san"]').fill('Facility manager edited own court'); await submit();
    check(new URL(page.url()).searchParams.get('route') === '/facility/san', 'facility manager valid court update returns to own area');
    await visit('/facility/bang-gia/edit', { id: priceId });
    await page.locator('#don_gia').fill('60000'); await submit();
    check(new URL(page.url()).searchParams.get('route') === '/facility/bang-gia', 'facility manager valid price update returns to own area');
    await as('NHAN_VIEN');
    await visit('/staff/bang-gia/show', { id: priceId });
    const token = await page.locator('input[name="_token"]').first().inputValue();
    for (const route of ['/staff/bang-gia/update', '/facility/bang-gia/update', '/admin/bang-gia/update']) {
      const response = await page.request.post(url(route), { form: { _token: token, id: priceId, ma_co_so: '1' } });
      check([403, 404].includes(response.status()), `staff valid-CSRF forged write blocked: ${route} (HTTP ${response.status()})`);
    }
    check(report.failures.length === 0, 'no browser JavaScript errors');
  } catch (error) { report.failures.push(error.stack); process.exitCode = 1; }
  finally {
    await browser?.close(); server.kill();
    fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 2)); fs.writeFileSync(path.join(out, 'server.log'), log);
  }
  console.log(`${report.checks.filter(result => result.ok).length} PASS / ${report.failures.length} FAIL; RAM only.`);
  for (const failure of report.failures) console.error(failure);
})().catch(error => { console.error(error); process.exitCode = 1; });
