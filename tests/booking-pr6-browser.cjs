// Real Chrome and production MVC GET routes on SQLite RAM only. Not MySQL integration.
const fs = require('node:fs'), path = require('node:path'), net = require('node:net');
const { spawn } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright-core');
const root = path.resolve(__dirname, '..'), output = path.join(root, 'storage/temp/pr6-review/browser');
fs.mkdirSync(path.join(output, 'sessions'), { recursive: true });
const report = { checks: [], failures: [], screenshots: [], mode: 'Production GET MVC on SQLite :memory: with query_only=ON; no MySQL/provider' };
const check = (ok, name) => { report.checks.push({ name, ok }); if (!ok) throw new Error(name); };
(async () => {
  const portServer = net.createServer(); await new Promise(resolve => portServer.listen(0, '127.0.0.1', resolve));
  const port = portServer.address().port; await new Promise(resolve => portServer.close(resolve));
  const base = `http://127.0.0.1:${port}/index.php`;
  const address = (route, params = {}) => base + '?' + new URLSearchParams({ route, ...params });
  const server = spawn(process.env.PHP_BINARY || 'php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/fixtures/booking-pr6.php'], {
    cwd: root, windowsHide: true, env: { ...process.env, STRYKE_PR6_UI: '1', STRYKE_PR6_SESSIONS: path.join(output, 'sessions') }, stdio: ['ignore', 'pipe', 'pipe'],
  });
  let log = '', browser;
  server.stdout.on('data', chunk => log += chunk); server.stderr.on('data', chunk => log += chunk);
  try {
    for (let i = 0; ; i++) {
      try {
        await new Promise((resolve, reject) => {
          const socket = net.createConnection({ host: '127.0.0.1', port });
          socket.once('connect', () => { socket.destroy(); resolve(); }); socket.once('error', reject);
        });
        break;
      }
      catch (error) { if (i >= 30) throw error; await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_BINARY || undefined });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    page.on('pageerror', error => report.failures.push('Browser JS: ' + error.message));
    let response = await page.goto(address('/admin/bookings'));
    check(response.status() === 200, 'booking list opens with compatible RAM schema');
    const menu = page.locator('[data-row-actions]').first();
    await menu.locator('summary').click();
    const link = menu.getByRole('link', { name: 'Xem chi tiết', exact: true });
    check(await link.count() === 1 && (await link.getAttribute('href')).includes('bookings%2Fshow'), 'real detail route is accessible through the redesigned actions menu');
    await Promise.all([page.waitForNavigation(), link.click()]);
    check(page.url().includes('id='), 'list link uses actual booking ID and route');
    for (const width of [1440, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      for (const [id, label] of [[1, 'Chờ thanh toán'], [2, 'Đã hủy'], [3, 'Hết hạn'], [4, 'Đã xác nhận'], [5, 'Chờ thanh toán'], [8, 'Chờ xác nhận']]) {
        response = await page.goto(address('/admin/bookings/show', { id }));
        check(response.status() === 200, `detail ${id} ${width}: HTTP 200`);
        check((await page.locator('main').innerText()).includes(label), `detail ${id} ${width}: booking status ${label}`);
        check(!(await page.locator('body').innerText()).includes('SQLSTATE'), `detail ${id} ${width}: no leaked exception`);
        if ([1, 4].includes(id)) {
          const html = await page.locator('main').innerText();
          check(html.includes('Nước snapshot PR6') && !html.includes('Tên catalog hiện tại'), `detail ${id} ${width}: saved snapshot name, not catalog`);
          check(html.includes('10.000') && html.includes('20.000') && html.includes('60.000'), `detail ${id} ${width}: saved service prices and totals`);
        } else check((await page.locator('main').innerText()).includes('Khách hàng không chọn thêm dịch vụ nào'), `detail ${id} ${width}: services empty state`);
        if ([1, 2, 5].includes(id)) {
          const image = path.join(output, `booking-${id}-${width}.png`); await page.screenshot({ path: image, fullPage: true }); report.screenshots.push(image);
        }
      }
    }
    for (const role of ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN', 'KHACH_HANG']) {
      const own = await page.request.get(address('/admin/bookings/show', { id: 1, fixture_role: role }));
      check(own.status() === (role === 'KHACH_HANG' ? 403 : 200), role + ': expected read permission');
      if (role !== 'KHACH_HANG') {
        const otherCompany = await page.request.get(address('/admin/bookings/show', { id: 7, fixture_role: role, ma_cong_ty: 2, ma_co_so: 3 }));
        check(otherCompany.status() === (role === 'QUAN_TRI_TAP_DOAN' ? 200 : 404), role + ': server company scope cannot be overridden');
        const otherFacility = await page.request.get(address('/admin/bookings/show', { id: 6, fixture_role: role }));
        check(otherFacility.status() === (['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY'].includes(role) ? 200 : 404), role + ': facility scope enforced');
      }
    }
    for (const id of ['0', 'abc', '999', '9']) {
      response = await page.request.get(address('/admin/bookings/show', { id }));
      check(response.status() === 404, 'invalid/missing/orphan booking ' + id + ': 404');
    }
    const csrf = await page.request.post(address('/admin/bookings/confirm'), { form: { id: '1', _token: 'invalid-pr6-token' } });
    check(csrf.status() === 403, 'actual router rejects invalid CSRF before any payment action');
    response = await page.goto(address('/admin/bookings/show', { id: 2, missing_bank: '1' }));
    check(response.status() === 500, 'reproduce missing bank schema: HTTP 500 (expected diagnostic, not fixed)');
    check((await page.locator('body').innerText()) === 'Một chút gián đoạn', 'missing schema error stays generic, not hidden as success');
    response = await page.goto(address('/admin/bookings/show', { id: 2 }));
    check(response.status() === 200, 'cancelled/pending-payment works when existing bank migration schema is present');
    check(log.includes('AdminBooking.php(282)') && log.includes('tai_khoan_ngan_hang_co_so'), 'local fixture log records exact missing-table failure');
  } catch (error) { report.failures.push(error.stack); }
  finally {
    if (browser) await browser.close(); server.kill();
    fs.writeFileSync(path.join(output, 'server.log'), log);
    fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ pass: report.checks.filter(check => check.ok).length, failures: report.failures, screenshots: report.screenshots.length, report: path.join(output, 'report.json') }, null, 2));
    if (report.failures.length) process.exitCode = 1;
  }
})();
