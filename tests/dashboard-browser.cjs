// Real Chrome + real MVC routes. All rows are disposable SQLite RAM fixtures.
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright-core');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const root = path.resolve(__dirname, '..');
const out = path.join(root, 'storage/temp/dashboard-review');
const results = [];
const tableMeasurements = [];
let browser, server, serverLog = '';
const check = (ok, label) => { results.push({ pass: Boolean(ok), label }); if (!ok) throw new Error(label); };
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
async function freePort() {
  const socket = net.createServer(); await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve)); return port;
}
async function layout(page, label) {
  await page.evaluate(() => document.fonts.ready);
  const sizes = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth,
    clipped: [...document.querySelectorAll('.dashboard-kpi-value, .dashboard-filters .btn')].filter(el => el.scrollWidth > el.clientWidth + 1).map(el => el.textContent),
    overlaps: [...document.querySelectorAll('.dashboard-kpi')].some(card => {
      const title = card.querySelector('h3').getBoundingClientRect(), icon = card.querySelector('.dashboard-kpi-icon').getBoundingClientRect();
      return title.right > icon.left && title.bottom > icon.top;
    }),
    narrowColumns: [...document.querySelectorAll('.dashboard-table tr > :first-child')].some(el => el.getBoundingClientRect().width < 140),
  }));
  check(sizes.scroll <= sizes.width + 1 && !sizes.clipped.length && !sizes.overlaps && !sizes.narrowColumns, `${label}: no overflow, clipped values, overlapping icons or crushed table columns ${JSON.stringify(sizes)}`);
  const alignment = await page.locator('.dashboard-kpi').evaluateAll(cards => cards.map(card => {
    const rect = card.getBoundingClientRect();
    return { top: rect.top, height: rect.height, title: card.querySelector('h3').getBoundingClientRect().top,
      value: card.querySelector('.dashboard-kpi-value').getBoundingClientRect().top, hint: card.querySelector('small').getBoundingClientRect().top };
  }));
  const aligned = alignment.every(card => alignment.filter(other => Math.abs(other.top - card.top) < 1)
    .every(other => ['height', 'title', 'value', 'hint'].every(key => Math.abs(other[key] - card[key]) < 1)));
  if (!aligned) fs.writeFileSync(path.join(out, 'misaligned-kpis.json'), JSON.stringify({ label, alignment }, null, 2));
  check(aligned, label + ': KPI heights, headings, values and descriptions align within each row');
  const assetsAligned = await page.locator('.dashboard-asset-grid').evaluateAll(grids => grids.every(grid => {
    const kpis = grid.closest('.dashboard-page').querySelector('.dashboard-kpis');
    const cards = [...grid.children].map(card => ({ top: card.getBoundingClientRect().top, height: card.getBoundingClientRect().height,
      value: card.querySelector('strong').getBoundingClientRect().top, label: card.querySelector('span').getBoundingClientRect().top }));
    return getComputedStyle(grid).gridTemplateColumns.split(' ').length === getComputedStyle(kpis).gridTemplateColumns.split(' ').length
      && cards.every(card => cards.filter(other => Math.abs(other.top - card.top) < 1)
        .every(other => ['height', 'value', 'label'].every(key => Math.abs(other[key] - card[key]) < 1)));
  }));
  check(assetsAligned, label + ': asset totals use the same column count with aligned rows');
  const stressAligned = await page.locator('.dashboard-kpi').evaluateAll(cards => {
    if (!cards.length) return true;
    const first = cards[0], value = first.querySelector('.dashboard-kpi-value'), hint = first.querySelector('small');
    const originalValue = value.textContent, originalHint = hint.textContent;
    try {
      value.textContent = '9.999.999.999,99 đ';
      hint.textContent = 'Mô tả dài để kiểm tra căn chỉnh khi số liệu và nội dung của các thẻ có nhiều dòng khác nhau. Số liệu của từng thẻ vẫn cần nằm cùng hàng và mô tả không đẩy nội dung các thẻ bên cạnh lên xuống.';
      const boxes = cards.map(card => ({ top: card.getBoundingClientRect().top, height: card.getBoundingClientRect().height,
        value: card.querySelector('.dashboard-kpi-value').getBoundingClientRect().top, hint: card.querySelector('small').getBoundingClientRect().top }));
      return boxes.every(card => boxes.filter(other => Math.abs(other.top - card.top) < 1)
        .every(other => ['height', 'value', 'hint'].every(key => Math.abs(other[key] - card[key]) < 1)));
    } finally { value.textContent = originalValue; hint.textContent = originalHint; }
  });
  check(stressAligned, label + ': long money values/descriptions share row tracks without shifting other KPIs');
  const recentAligned = await page.locator('[aria-label="Booking gần đây"] table').evaluateAll(tables => tables.every(table => {
    const headings = [...table.querySelectorAll('thead th')];
    return [...table.querySelectorAll('tbody tr')].every(row => row.children.length === headings.length
      && [...row.children].every((cell, index) => Math.abs(cell.getBoundingClientRect().left - headings[index].getBoundingClientRect().left) < 1
        && Math.abs(cell.getBoundingClientRect().width - headings[index].getBoundingClientRect().width) < 1));
  }));
  check(recentAligned, label + ': recent-booking header/body cell boundaries align');
  const contentMeasurements = await page.locator('.dashboard-table').evaluateAll(tables => {
    const textBox = element => {
      const node = [...element.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
      if (node) { const range = document.createRange(); range.selectNodeContents(node); return range.getBoundingClientRect(); }
      const child = element.querySelector('strong, a');
      if (child) { const range = document.createRange(); range.selectNodeContents(child); return range.getBoundingClientRect(); }
      return element.getBoundingClientRect();
    };
    return tables.flatMap(table => {
      const headings = [...table.querySelectorAll('thead th')];
      return [...table.querySelectorAll('tbody tr')].filter(row => row.children.length === headings.length)
        .flatMap((row, rowIndex) => [...row.children].map((cell, index) => {
          const header = headings[index], right = getComputedStyle(header).textAlign === 'right';
          const headBox = textBox(header), badge = cell.querySelector('.dashboard-badge');
          const bodyBox = badge ? badge.getBoundingClientRect() : textBox(cell);
          const edge = right ? 'right' : 'left', headStyle = getComputedStyle(header), bodyStyle = getComputedStyle(cell);
          return { table: table.closest('[aria-label]')?.getAttribute('aria-label'), row: rowIndex, column: header.textContent.trim(), edge,
            header: headBox[edge], body: bodyBox[edge], delta: Math.abs(headBox[edge] - bodyBox[edge]),
            paddingMatches: headStyle.paddingLeft === bodyStyle.paddingLeft && headStyle.paddingRight === bodyStyle.paddingRight,
            badgeFits: !badge || badge.scrollWidth <= badge.clientWidth + 1 };
        }));
    });
  });
  tableMeasurements.push({ label, measurements: contentMeasurements });
  check(contentMeasurements.every(item => item.delta < 1 && item.paddingMatches && item.badgeFits), label + ': actual text/badge edges align with headers, money right-aligned');
}
(async () => {
  fs.mkdirSync(out, { recursive: true });
  const port = await freePort(), base = `http://127.0.0.1:${port}`;
  const url = (route, query = {}) => `${base}/index.php?${new URLSearchParams({ route, ...query })}`;
  server = spawn(process.env.PHP_BINARY || 'D:/PHP/php/php.exe', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/fixtures/dashboard.php'], {
    cwd: root, windowsHide: true, env: { ...process.env, STRYKE_DASHBOARD_TEST: '1' }, stdio: ['ignore', 'pipe', 'pipe'],
  });
  server.stdout.on('data', value => { serverLog += value; }); server.stderr.on('data', value => { serverLog += value; });
  for (let i = 0; ; i++) {
    const ready = await new Promise(resolve => { const socket = net.createConnection({ port, host: '127.0.0.1' });
      socket.once('connect', () => { socket.destroy(); resolve(true); }); socket.once('error', () => resolve(false)); });
    if (ready) break; if (i > 100 || server.exitCode !== null) throw new Error('PHP fixture did not start'); await wait(50);
  }
  browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  const roles = [
    ['QUAN_TRI_TAP_DOAN', '/admin/revenue', 'group', '830.100,75 đ'], ['QUAN_LY_CONG_TY', '/company/revenue', 'company', '520.100,75 đ'],
    ['QUAN_LY_CO_SO', '/facility/revenue', 'facility', '270.100,75 đ'], ['NHAN_VIEN', '/staff/revenue', 'staff', '270.100,75 đ'],
  ];
  let foreignInvoiceId;
  for (const [role, home, name, revenue] of roles) {
    const context = await browser.newContext({ locale: 'vi-VN', extraHTTPHeaders: { 'X-Stryke-Test-Role': role }, reducedMotion: 'reduce' });
    const page = await context.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error' && /Content Security Policy|Refused to/.test(message.text())) errors.push(message.text()); });
    await page.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
    const dashboardHome = home.replace('/revenue', '');
    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      check((await page.goto(url(dashboardHome))).ok(), name + ': default Dashboard loads');
      check(await page.locator('[data-dashboard-filters]').count() === 0 && (await page.locator('.dashboard-period-summary').innerText()).includes('Tháng này'), name + ': Dashboard has month summary without filters');
      await layout(page, name + '/Dashboard/' + width);
      await page.screenshot({ path: path.join(out, name + '-dashboard-' + width + '.png'), fullPage: true });
    }
    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      const response = await page.goto(url(home, { period: 'today' }));
      check(response.status() === 200, `${role}/${width}: Dashboard loads`);
      check(await page.locator('[data-kpi="revenue"]').innerText() === revenue, `${role}/${width}: exact scoped revenue`);
      check(await page.locator('.admin-sidebar [aria-current="page"]').count() === 1, `${role}/${width}: one active menu`);
      check(await page.locator('.dashboard-chart svg[role="img"]').count() === 3, `${role}/${width}: timeline and donut charts rendered`);
      check(await page.locator('.dashboard-page form[method="post"]').count() === 0, `${role}/${width}: read-only Dashboard`);
      await layout(page, `${name}/${width}`);
      const controlHeights = await page.locator('.dashboard-filters .form-control, .dashboard-filter-actions .btn').evaluateAll(elements => elements.map(element => element.getBoundingClientRect().height));
      check(controlHeights.every(height => Math.abs(height - 44) < 1), `${name}/${width}: filter inputs and buttons share 44px height`);
      const point = page.locator('[data-dashboard-tooltip]').first();
      await point.focus();
      check((await page.locator('#dashboard-tooltip').innerText()).includes('đ'), `${name}/${width}: keyboard tooltip formats VND`);
      check(await page.locator('#dashboard-tooltip').evaluate(element => { const rect = element.getBoundingClientRect(); return rect.left >= 0 && rect.right <= innerWidth && rect.top >= 0; }), `${name}/${width}: tooltip stays in viewport`);
      await page.keyboard.press('Escape');
      check(await page.locator('#dashboard-tooltip').isHidden(), `${name}/${width}: Escape dismisses tooltip`);
      await point.evaluate(element => element.blur());
      await page.evaluate(() => scrollTo(0, 0));
      await page.screenshot({ path: path.join(out, `${name}-${width}.png`), fullPage: true });
      await page.screenshot({ path: path.join(out, `${name}-${width}-top.png`) });
      if (width === 375) await page.locator('.dashboard-report').last().screenshot({ path: path.join(out, `${name}-booking-table-375.png`) });
      await page.locator('[aria-label="Booking gần đây"]').screenshot({ path: path.join(out, `${name}-recent-bookings-${width}.png`) });
      await page.locator('.dashboard-chart-data summary').first().click();
      check(await page.locator('.dashboard-chart-data[open] tbody tr').count() >= 1, `${role}/${width}: accessible chart data table opens`);
      if (width >= 1024) {
        await page.locator('[data-sidebar-collapse]').click(); await layout(page, `${name}/${width}/compact`);
        await page.screenshot({ path: path.join(out, `${name}-${width}-compact.png`), fullPage: true });
        await page.locator('[data-sidebar-collapse]').click();
      } else {
        await page.locator('#admin-menu-toggle').click();
        check(await page.locator('#admin-menu-toggle').getAttribute('aria-expanded') === 'true', `${name}/${width}: mobile sidebar opens`);
        await page.locator('#admin-sidebar-close').click();
        check(await page.locator('#admin-menu-toggle').getAttribute('aria-expanded') === 'false', `${name}/${width}: mobile sidebar closes`);
      }
    }
    if (name === 'group') {
      await page.selectOption('#dashboard-company', '2');
      check(await page.locator('#dashboard-facility option:enabled').count() === 2, 'company selection limits facility options');
      await page.selectOption('#dashboard-facility', '21');
      await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Áp dụng', exact: true }).click()]);
      check(await page.locator('[data-kpi="revenue"]').innerText() === '310.000 đ', 'combined company/facility filter updates KPI');
      check((await page.locator('.dashboard-report').first().innerText()).includes('310.000 đ') && !(await page.locator('.dashboard-report').first().innerText()).includes('An Phú'), 'comparison table follows filters');
      await page.selectOption('#dashboard-period', 'last_month');
      await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Áp dụng', exact: true }).click()]);
      check(await page.locator('[data-kpi="revenue"]').innerText() === '500.000 đ', 'last-month filter exact revenue');
      await page.locator('.dashboard-filter-actions a').click();
      check(new URL(page.url()).searchParams.get('company_id') === null, 'reset clears organization filters');
    } else if (name === 'company') {
      check(await page.locator('[name="company_id"]').count() === 0 && await page.locator('#dashboard-facility option[value="21"]').count() === 0, 'company manager cannot select foreign organization');
      await page.selectOption('#dashboard-facility', '12');
      await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Áp dụng', exact: true }).click()]);
      check(await page.locator('[data-kpi="revenue"]').innerText() === '250.000 đ', 'company manager filters owned facility');
    } else {
      check(await page.locator('[name="company_id"], [name="facility_id"]').count() === 0, `${name}: no organization selectors`);
      check(!(await page.locator('.dashboard-page').innerText()).includes('Bình Minh') && !(await page.locator('.dashboard-page').innerText()).includes('Phía Đông'), `${name}: no foreign or sibling names`);
    }
    for (const [, route] of roles) {
      if (route === home) continue;
      check((await context.request.get(url(route))).status() === 403, `${role}: direct ${route} forbidden`);
    }
    if (role !== 'QUAN_TRI_TAP_DOAN') {
      check((await context.request.get(url(home, { company_id: '2' }))).status() === 403, `${role}: forged company denied`);
      check((await context.request.get(url(home, { facility_id: name === 'company' ? '21' : '12' }))).status() === 403, `${role}: forged facility denied`);
    }
    const emptyResponse = await page.goto(url(home, { fixture: 'empty', period: 'today' }));
    check(emptyResponse.status() === 200 && await page.locator('[data-kpi="revenue"]').innerText() === '0 đ', `${name}: true empty state renders zero`);
    check(await page.locator('.dashboard-empty').count() >= 3, `${name}: empty charts have explanations`);
    await layout(page, `${name}/empty`);
    await page.screenshot({ path: path.join(out, `${name}-empty-375.png`), fullPage: true });
    check(errors.length === 0, `${name}: no JS/CSP errors ${errors.join(', ')}`);
    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.goto(url(dashboardHome, { fixture: 'long' }));
      check((await page.locator('[aria-label="Booking gần đây"]').innerText()).includes('Tên cơ sở dài'), `${name}/${width}: long facility name fixture is present`);
      await layout(page, `${name}/long-recent/${width}`);
      await page.locator('[aria-label="Booking gần đây"]').screenshot({ path: path.join(out, `${name}-recent-long-${width}.png`) });
    }
    // Invoice routes and native print stay unchanged; test real reads with the same RAM PDO.
    const invoicePage = await context.newPage(), invoiceErrors = [];
    invoicePage.on('pageerror', error => invoiceErrors.push(error.message));
    await context.addInitScript(() => { window.print = () => { window.__printRequested = true; }; });
    for (const width of [1440, 1024, 768, 375]) {
      await invoicePage.setViewportSize({ width, height: 1000 });
      const response = await invoicePage.goto(url('/admin/invoices', { per_page: '20' }));
      check(response.ok(), `${name}/${width}: invoice list loads with existing permission`);
      const row = invoicePage.locator('tbody tr').first();
      const geometry = await row.locator('.invoice-actions').evaluate(group => {
        const [details, print] = [...group.querySelectorAll('a')].map(element => element.getBoundingClientRect());
        const cell = group.parentElement.getBoundingClientRect();
        return { details, print, cell, icons: group.querySelectorAll('a svg').length, labels: [...group.querySelectorAll('a')].map(link => link.textContent.trim()) };
      });
      check(geometry.labels.join(',') === 'Chi tiết,In' && geometry.icons === 2, `${name}/${width}: details then print, with SVG icons`);
      check(Math.abs(geometry.details.y - geometry.print.y) < 1 && Math.abs(geometry.details.height - 38) < 1 && Math.abs(geometry.print.height - 38) < 1, `${name}/${width}: invoice buttons stay aligned at 38px`);
      check(geometry.print.x - geometry.details.right >= 7 && geometry.print.right <= geometry.cell.right + 1, `${name}/${width}: actions fit their cell with an 8px gap`);
      check(await invoicePage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name}/${width}: invoices do not overflow viewport`);
      check(await invoicePage.locator('.invoice-number').evaluateAll(codes => codes.every(code => code.scrollWidth <= code.clientWidth + 1)), `${name}/${width}: invoice numbers including long codes wrap within their column`);
      check(await invoicePage.locator('.invoice-list .catalog-table [style]').count() === 0, `${name}/${width}: invoice table no longer relies on CSP-blocked inline styles`);
      if (name === 'group') {
        await row.scrollIntoViewIfNeeded();
        await invoicePage.screenshot({ path: path.join(out, `invoices-${width}.png`) });
      }
      const detailsLink = row.locator('.invoice-actions a').first();
      const invoiceId = new URL(await detailsLink.getAttribute('href'), base).searchParams.get('id');
      const shown = await context.request.get(url('/admin/invoices/show', { id: invoiceId }));
      check(shown.ok() && (await shown.text()).includes('Hóa đơn #'), `${name}/${width}: real detail route returns invoice`);
    }
    if (name === 'group') {
      await invoicePage.goto(url('/admin/invoices', { facility_id: '21' }));
      foreignInvoiceId = new URL(await invoicePage.locator('.invoice-actions a').first().getAttribute('href'), base).searchParams.get('id');
    } else {
      for (const route of ['/admin/invoices/show', '/admin/invoices/print']) check((await context.request.get(url(route, { id: foreignInvoiceId }))).status() === 403, `${name}: foreign invoice ${route} denied`);
    }
    await Promise.all([invoicePage.waitForNavigation(), invoicePage.locator('.invoice-actions a').first().click()]);
    check(await invoicePage.locator('h1').innerText().then(title => title.includes('Hóa đơn #')), `${name}: details button navigates to invoice`);
    await invoicePage.goto(url('/admin/invoices'));
    const popupPromise = context.waitForEvent('page');
    await invoicePage.locator('.invoice-actions a').nth(1).click();
    const popup = await popupPromise;
    await popup.waitForLoadState();
    await popup.waitForFunction(() => window.__printRequested === true);
    check((await popup.title()).includes('In hóa đơn #'), `${name}: print opens a new tab and invokes window.print`);
    await popup.close();
    check(invoiceErrors.length === 0, `${name}: no invoice list/detail JavaScript errors`);
    await invoicePage.close();
    await context.close();
  }
  const customer = await browser.newContext({ extraHTTPHeaders: { 'X-Stryke-Test-Role': 'KHACH_HANG' } });
  for (const [, home] of roles) check((await customer.request.get(url(home))).status() === 403, `customer cannot access ${home}`);
  for (const route of ['/admin/invoices', '/admin/invoices/show', '/admin/invoices/print']) check((await customer.request.get(url(route, { id: '1' }))).status() === 403, `customer cannot access internal invoice route ${route}`);
  await customer.close();
  const context = await browser.newContext({ extraHTTPHeaders: { 'X-Stryke-Test-Role': 'QUAN_TRI_TAP_DOAN' } });
  const page = await context.newPage();
  await page.setViewportSize({ width: 1440, height: 780 });
  await page.goto(url('/admin/revenue', { period: 'today' }));
  const navigation = page.locator('.admin-sidebar__nav');
  // Revenue is an operation link near the bottom; keep the active link visible before testing restoration.
  await navigation.evaluate(element => { element.scrollTop = element.scrollHeight; });
  const savedScroll = await navigation.evaluate(element => element.scrollTop);
  check(savedScroll > 0, 'Dashboard test sidebar has a real independent scroll position');
  await page.selectOption('#dashboard-group', 'month');
  await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Áp dụng', exact: true }).click()]);
  check(Math.abs(await navigation.evaluate(element => element.scrollTop) - savedScroll) < 1, 'Dashboard GET filter preserves sidebar scroll position');
  check(await page.locator('#dashboard-group').inputValue() === 'month' && await page.locator('[data-kpi="revenue"]').innerText() === '830.100,75 đ', 'chart grouping selection survives reload without changing revenue');
  check(await page.locator('.dashboard-cell-name').evaluateAll(cells => cells.every(cell => getComputedStyle(cell).textAlign === 'left')), 'facility names align left in recent bookings');
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  const kpi = page.locator('.dashboard-kpi').first();
  await kpi.scrollIntoViewIfNeeded();
  const nextBox = () => page.locator('.dashboard-kpi').nth(1).evaluate(element => {
    const box = element.getBoundingClientRect();
    return { x: box.x + scrollX, y: box.y + scrollY, width: box.width, height: box.height };
  });
  const nextBefore = await nextBox();
  await kpi.hover(); await wait(230);
  check(Math.abs(await kpi.evaluate(element => new DOMMatrix(getComputedStyle(element).transform).m42) + 2) < .1, 'KPI hover lifts two pixels');
  const nextAfter = await nextBox();
  check(['x', 'y', 'width', 'height'].every(key => Math.abs(nextBefore[key] - nextAfter[key]) < .1), 'KPI hover does not shift surrounding layout');
  await page.emulateMedia({ reducedMotion: 'reduce' });
  check(await kpi.evaluate(element => getComputedStyle(element).transitionDuration === '0s' && getComputedStyle(element).transform === 'none'), 'reduced motion disables KPI animation');
  const failed = await page.goto(url('/admin', { fixture: 'error' }));
  check(failed.status() === 503 && await page.locator('.dashboard-error').count() === 1 && await page.locator('[data-kpi]').count() === 0, 'query failure shows error, never fabricated zero KPI');
  check(!(await page.locator('body').innerText()).includes('SQLSTATE'), 'error page does not expose SQL details');
  await page.screenshot({ path: path.join(out, 'error.png'), fullPage: true });
  await context.close();
})().catch(error => { results.push({ pass: false, label: error.stack }); process.exitCode = 1; }).finally(async () => {
  if (browser) await browser.close(); if (server) server.kill();
  results.push({ pass: !/PHP (Warning|Fatal error|Parse error)/.test(serverLog), label: 'no PHP warnings/fatal errors' });
  fs.writeFileSync(path.join(out, 'browser-results.json'), JSON.stringify(results, null, 2)); fs.writeFileSync(path.join(out, 'server.log'), serverLog);
  fs.writeFileSync(path.join(out, 'table-alignment.json'), JSON.stringify(tableMeasurements, null, 2));
  console.log(`${results.filter(r => r.pass).length} PASS / ${results.filter(r => !r.pass).length} FAIL; ${out}`);
  for (const result of results.filter(r => !r.pass)) console.error(result.label);
});
