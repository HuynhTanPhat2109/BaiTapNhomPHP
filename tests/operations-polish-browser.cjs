// Chrome + real PHP views/services. Every fixture uses SQLite RAM or array-backed PDO.
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright-core');
const { spawn } = require('node:child_process');
const fs = require('node:fs'), path = require('node:path'), net = require('node:net');
const root = path.resolve(__dirname, '..'), out = path.join(root, 'storage/temp/operations-polish');
const results = [], servers = [];
const check = (ok, label) => { results.push({ pass: Boolean(ok), label }); if (!ok) throw new Error(label); };
const widths = [1440, 1280, 1024, 768, 375];
fs.mkdirSync(out, { recursive: true });
async function start(fixture, env) {
  const socket = net.createServer(); await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`, server = spawn(process.env.PHP_BINARY || 'D:/PHP/php/php.exe', ['-S', `127.0.0.1:${port}`, '-t', 'public', `tests/fixtures/${fixture}.php`], {
    cwd: root, windowsHide: true, env: { ...process.env, ...env }, stdio: ['ignore', 'pipe', 'pipe'],
  });
  const record = { server, log: '', fixture }; servers.push(record);
  server.stdout.on('data', data => record.log += data); server.stderr.on('data', data => record.log += data);
  for (let attempt = 0; ; attempt++) {
    const ready = await new Promise(resolve => { const connection = net.connect(port, '127.0.0.1'); connection.once('connect', () => { connection.destroy(); resolve(true); }); connection.once('error', () => resolve(false)); });
    if (ready) break; if (attempt > 100) throw new Error('Fixture unavailable'); await new Promise(resolve => setTimeout(resolve, 50));
  }
  return (route, query = {}) => `${base}/index.php?${new URLSearchParams({ route, ...query })}`;
}
async function pageFor(browser, role) {
  const context = await browser.newContext({ extraHTTPHeaders: { 'X-Stryke-Test-Role': role }, reducedMotion: 'reduce' });
  const page = await context.newPage(); page.on('pageerror', error => check(false, 'Browser JS: ' + error.message));
  // The production response forbids inline styling/handlers. Apply the same CSP to fixtures.
  await page.route('**/index.php*', async route => {
    const response = await route.fetch();
    await route.fulfill({ response, headers: { ...response.headers(), 'content-security-policy': "default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:" } });
  });
  return { context, page };
}
async function visit(page, url) { const response = await page.goto(url); check(response.ok(), `HTTP ${response.status()}: ${new URL(url).searchParams.get('route')}`); }
async function layout(page, label) { check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), label + ': no horizontal document overflow'); }
let browser;
(async () => {
  browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  const bookingUrl = await start('booking-pr6', { STRYKE_PR6_UI: '1', STRYKE_PR6_SESSIONS: out, STRYKE_OPERATIONS_TEST: '1' });
  for (const role of ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN']) {
    const { context, page } = await pageFor(browser, role);
    await visit(page, bookingUrl('/admin/bookings', { fixture_role: role }));
    check(!(await page.locator('.admin-workspace').innerText()).includes('Đang trong quá trình phát triển'), role + ': list development banner removed');
    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      await visit(page, bookingUrl('/admin/bookings', { fixture_role: role }));
      await layout(page, `${role}/booking-menu/${width}`);
      for (const [id, expected] of [[1, ['confirm', 'cancel', 'reconcile']], [2, ['reconcile']], [3, ['reconcile']], [4, []], [5, []]]) {
        const menu = page.locator(`[data-row-actions]:has(summary[aria-label="Thao tác cho booking #${id}"])`);
        await menu.locator('summary').click();
        check(await menu.locator('[data-booking-action]').evaluateAll(buttons => buttons.map(button => button.dataset.bookingAction).sort().join(',')) === expected.sort().join(','), `${role}/${width}/#${id}: eligible actions only`);
        const panel = menu.locator('.list-action-menu__panel');
        check(await panel.evaluate(element => { const r = element.getBoundingClientRect(); return element.matches(':popover-open') && r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight; }), `${role}/${width}/#${id}: menu escapes table clipping and fits viewport`);
        check(await panel.locator('a').count() === (id === 4 ? 2 : 1), `${role}/${width}/#${id}: detail and successful-payment reconciliation links`);
        await page.keyboard.press('Escape');
        check(!(await menu.evaluate(element => element.open)), `${role}/${width}/#${id}: Escape closes menu`);
      }
      const menu = page.locator('[data-row-actions]:has(summary[aria-label="Thao tác cho booking #1"])');
      await menu.locator('summary').click();
      await menu.locator('[data-booking-action="confirm"]').click();
      const dialog = page.locator('#booking-dialog-1');
      check(await dialog.evaluate(element => element.open) && !(await menu.evaluate(element => element.open)), `${role}/${width}: action closes menu and opens modal`);
      check(await dialog.locator('dt').count() === 6 && (await dialog.innerText()).includes('60.000'), `${role}/${width}: modal displays booking/customer/facility/time/payment summary`);
      const form = dialog.locator('[data-booking-form="confirm"]');
      check((await form.getAttribute('action')).includes('confirm') && await form.locator('[name="_token"]').count() === 1 && await form.locator('[name="id"]').inputValue() === '1', `${role}/${width}: original POST endpoint, CSRF and booking id`);
      check(await form.locator('[name="list[page]"]').inputValue() === '1', `${role}/${width}: modal keeps server list context`);
      await form.locator('[name="so_tien_thuc_nhan"]').fill('60000');
      await form.locator('[name="ma_giao_dich"]').fill('UI-ONLY-NEVER-SUBMITTED');
      await form.locator('button[type="submit"]').click();
      check(await page.locator('#workspace-confirm').evaluate(element => element.open), `${role}/${width}: important write requires second confirmation`);
      await page.locator('[data-confirm-cancel]').click();
      check(await dialog.evaluate(element => element.open), `${role}/${width}: cancelling confirmation keeps entered form`);
      const size = await dialog.evaluate(element => { const r = element.getBoundingClientRect(); return { left: r.left, right: r.right, scroll: element.scrollWidth, client: element.clientWidth }; });
      check(size.left >= 0 && size.right <= width && size.scroll <= size.client + 1, `${role}/${width}: modal fits without horizontal overflow`);
      if (role === 'QUAN_TRI_TAP_DOAN') {
        await dialog.evaluate(element => { element.scrollTop = 0; });
        await page.screenshot({ path: path.join(out, `booking-confirm-modal-${width}.png`) });
      }
      await page.keyboard.press('Escape');
      await menu.locator('summary').click();
      await menu.locator('[data-booking-action="cancel"]').click();
      check(await dialog.locator('[data-booking-form="cancel"] [name="ly_do_huy"]').getAttribute('required') !== null, `${role}/${width}: cancellation requires reason`);
      await page.keyboard.press('Escape');
    }
    for (const width of widths) {
      await page.setViewportSize({ width, height: 1000 });
      for (const [state, query, forms] of [['pending', { id: '1' }, 2], ['expired', { id: '3' }, 1], ['cancelled', { id: '2' }, 1], ['reconcile', { id: '1', operations_reconcile: '1' }, 2]]) {
        await visit(page, bookingUrl('/admin/bookings/show', { fixture_role: role, ...query }));
        const operations = page.locator('.booking-operations');
        check(!(await page.locator('.admin-workspace').innerText()).includes('Đang trong quá trình phát triển'), `${role}/${state}/${width}: no development banner`);
        check(await operations.locator('form[method="post"]').count() === forms, `${role}/${state}/${width}: existing actions retained`);
        check(await operations.locator('[style], [onclick]').count() === 0, `${state}/${width}: action markup needs no inline style/script`);
        const metrics = await operations.evaluate(section => ({
          cards: [...section.querySelectorAll('.booking-operation-card')].every(card => getComputedStyle(card).display === 'flex' && getComputedStyle(card).backgroundColor === 'rgb(255, 255, 255)' && parseFloat(getComputedStyle(card).paddingTop) >= 20),
          fields: [...section.querySelectorAll('.form-group')].every(group => group.querySelector('label') && getComputedStyle(group).rowGap === '8px'),
          labels: [...section.querySelectorAll('.form-control')].every(field => field.labels.length === 1),
          footer: [...section.querySelectorAll('.booking-operation-footer')].every(footer => parseFloat(getComputedStyle(footer).marginTop) >= 24),
        }));
        check(metrics.cards && metrics.fields && metrics.labels && metrics.footer, `${role}/${state}/${width}: cards, labels, spacing and footer render under CSP`);
        check(await operations.locator('form input[name="_token"]').count() === forms && await operations.locator('form input[name="id"][value="' + query.id + '"]').count() === forms, `${state}/${width}: CSRF and booking ID retained`);
        if (['expired', 'cancelled'].includes(state)) check(await operations.locator('form[action*="confirm"]').count() === 0, `${state}: no confirm action`);
        await layout(page, `${role}/${state}/${width}`);
        if (role === 'QUAN_TRI_TAP_DOAN') await operations.screenshot({ path: path.join(out, `after-booking-${state}-${width}.png`) });
        if (state === 'reconcile' && width === 1440) {
          await operations.locator('[name="ly_do_huy"]').fill('Chỉ kiểm tra hộp xác nhận, không gửi yêu cầu hủy.');
          await operations.locator('form[action*="cancel"] button').click();
          check(await page.locator('#workspace-confirm').isVisible(), role + ': cancellation confirmation works under CSP');
          await page.locator('[data-confirm-cancel]').click();
        }
      }
    }
    await context.close();
  }
  const employeeUrl = await start('admin-navigation', { STRYKE_NAVIGATION_TEST: '1', STRYKE_NAVIGATION_SESSIONS: out });
  for (const [role, area] of [['QUAN_TRI_TAP_DOAN', 'admin'], ['QUAN_LY_CONG_TY', 'company']]) {
    const { context, page } = await pageFor(browser, role);
    await context.request.get(employeeUrl('/' + area + '/employees', { fixture: 'reset', count: '120' }));
    for (const width of widths) {
      await page.setViewportSize({ width, height: 1000 });
      await visit(page, employeeUrl('/' + area + '/employees'));
      await layout(page, `${area} employees/${width}`);
      check(await page.locator('.catalog-table td[data-label="' + (area === 'admin' ? 'Tên công ty' : 'Cơ sở') + '"]').count() === 10, `${area}: semantic column/name retained`);
      const names = await page.locator('td[data-label="' + (area === 'admin' ? 'Tên công ty' : 'Cơ sở') + '"]').allTextContents();
      check(names.every(name => !/^\d+$/.test(name.trim())), `${area}: actual parent names instead of raw IDs`);
      check(await page.locator('.admin-menu__item[aria-current="page"]').count() === 1, `${area}/${width}: sidebar active retained`);
      if (area === 'company') {
        const sizes = await page.evaluate(() => ({ toolbar: document.querySelector('.list-toolbar').getBoundingClientRect().width, content: document.querySelector('.admin-workspace > section').getBoundingClientRect().width }));
        check(Math.abs(sizes.toolbar - sizes.content) <= 2, `company employee/${width}: toolbar uses full content width ${JSON.stringify(sizes)}`);
        check(await page.locator('.list-toolbar').locator('[name="q"], [name="ma_co_so"], [name="vai_tro"], [name="trang_thai"], [name="sort"], [name="per_page"]').count() === 6, 'all existing filters retained');
      }
      await page.screenshot({ path: path.join(out, `after-employees-${area}-${width}.png`), fullPage: true });
    }
    if (area === 'company') {
      const filters = { q: 'UX', ma_co_so: '2', vai_tro: 'QUAN_LY_CO_SO', trang_thai: 'DANG_LAM', sort: 'name_asc', per_page: '10', page: '2' };
      await page.setViewportSize({ width: 1440, height: 1000 });
      await visit(page, employeeUrl('/company/employees', filters));
      check(await page.locator('tbody tr').count() === 10, 'combined company filters have a real second page');
      await page.locator('tbody tr th a').first().click();
      await page.locator('.detail-back').click();
      for (const [name, value] of Object.entries(filters)) if (name !== 'per_page') check(new URL(page.url()).searchParams.get(name) === value, 'employee details/back preserves ' + name);
      await page.selectOption('[name="per_page"]', '20');
      await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Tìm kiếm', exact: true }).click()]);
      check(await page.locator('tbody tr').count() === 20 && new URL(page.url()).searchParams.get('ma_co_so') === '2', 'page size retains combined filter');
      await page.locator('[rel="next"]').click();
      check(new URL(page.url()).searchParams.get('page') === '2' && new URL(page.url()).searchParams.get('per_page') === '20', 'backend pagination keeps page size');
      await page.getByRole('link', { name: 'Đặt lại', exact: true }).click();
      check(new URL(page.url()).searchParams.get('q') === null && await page.locator('[name="per_page"]').inputValue() === '10', 'reset clears filters and resets size');
      await page.setViewportSize({ width: 1440, height: 650 });
      await page.locator('.admin-sidebar__nav').evaluate(nav => nav.scrollTop = nav.scrollHeight);
      await page.waitForTimeout(80);
      await page.locator('.admin-sidebar a[aria-label="Bảng giá"]').click();
      check(await page.locator('.admin-sidebar__nav').evaluate(nav => nav.scrollTop) > 0, 'company sidebar position restored across navigation');
    }
    await context.close();
  }
  const stockUrl = await start('operations-stock', { STRYKE_OPERATIONS_TEST: '1' });
  const { context, page } = await pageFor(browser, 'KHACH_HANG');
  for (const width of widths) {
    await page.setViewportSize({ width, height: 1000 });
    for (const [stock, held, available, label] of [['6', '0', '6', 'Còn hàng'], ['6', '2', '4', 'Còn hàng'], ['6', '6', '0', 'Hết hàng'], ['0', '0', '0', 'Hết hàng'], ['null', '0', '', 'Không theo dõi tồn']]) {
      await visit(page, stockUrl('/booking/details', { stock, held }));
      await page.locator('[data-open-services]').click();
      const row = page.locator('[data-service-row="5"]'), input = row.locator('input[type="number"]');
      check(await row.locator('[data-available-stock]').getAttribute('data-available-stock') === available && (await row.innerText()).includes(label), `rental ${stock}/${held}/${width}: server stock and label agree`);
      check(await input.isDisabled() === (available === '0'), `rental ${stock}/${held}: stock controls match availability`);
      if (available !== '') check(await input.getAttribute('max') === available, 'customer quantity max uses server availability');
      await layout(page, `customer stock/${width}`);
      if (held === '0' && stock === '6') await page.screenshot({ path: path.join(out, `after-rental-${width}.png`), fullPage: true });
      if (width === 1440 && held === '0' && stock === '6') {
        for (let click = 0; click < 7; click++) await row.locator('[data-quantity="1"]').click();
        check(await input.inputValue() === '6', 'rental plus control clamps at the six server-provided units');
        await page.locator('[data-apply-services]').click();
        await page.waitForFunction(() => document.querySelector('[data-selected-services]')?.textContent.includes('Vợt thuê'));
        check(await page.locator('[name="services[5]"]').inputValue() === '6', 'actual AJAX quote accepts rental selection and updates applied quantities');
      }
    }
  }
  await visit(page, stockUrl('/booking/details', { stock: '6', held: '2' }));
  const token = await page.locator('[name="_token"]').inputValue();
  for (const services of [{ 'services[5]': '5' }, { 'services[4]': '1' }]) {
    const response = await context.request.post(stockUrl('/booking/review', { stock: '6', held: '2' }), { form: { _token: token, ...services } });
    check(response.status() === 409, 'real quote rejects forged excessive quantity/foreign facility service');
  }
  await context.close();
})().catch(error => { results.push({ pass: false, label: error.stack }); process.exitCode = 1; }).finally(async () => {
  await browser?.close();
  for (const { server, log, fixture } of servers) { server.kill(); fs.writeFileSync(path.join(out, fixture + '.log'), log); }
  fs.writeFileSync(path.join(out, 'browser-results.json'), JSON.stringify(results, null, 2));
  console.log(`${results.filter(row => row.pass).length} PASS / ${results.filter(row => !row.pass).length} FAIL; ${out}`);
  for (const result of results.filter(row => !row.pass)) console.error(result.label);
});
