// Real Chrome + PHP HTTP redirects/controllers/forms; all test rows stay in RAM.
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright-core');
const root = path.resolve(__dirname, '..');
const output = path.join(root, 'storage/temp/admin-navigation');
fs.mkdirSync(path.join(output, 'sessions'), { recursive: true });
const report = { mode: 'Real Chrome/PHP; SQLite :memory: only; no MySQL or database files', checks: [], failures: [], screenshots: [] };
const check = (ok, label) => { report.checks.push(label); if (!ok) throw new Error(label); };
const modules = {
  'bo-mon': 'ten_bo_mon', 'loai-san': 'ten_loai_san', 'co-so': 'ten_co_so',
  san: 'ten_san', 'bang-gia': 'ten_bang_gia', 'dich-vu': 'ten_dich_vu',
};
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
async function freePort() {
  const server = net.createServer();
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const port = server.address().port;
  await new Promise(resolve => server.close(resolve));
  return port;
}
(async () => {
  const port = await freePort();
  const base = `http://127.0.0.1:${port}/index.php`;
  const address = (slug, query = {}) => base + '?' + new URLSearchParams({ route: '/admin/' + slug, ...query });
  const returnTo = (slug, query = {}) => { const target = new URL(address(slug, query)); return target.pathname + target.search; };
  const server = spawn(process.env.PHP_BINARY || 'php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/fixtures/admin-navigation.php'], {
    cwd: root, windowsHide: true, env: { ...process.env, STRYKE_NAVIGATION_TEST: '1', STRYKE_NAVIGATION_SESSIONS: path.join(output, 'sessions') },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  let serverLog = '';
  server.stdout.on('data', data => { serverLog += data; });
  server.stderr.on('data', data => { serverLog += data; });
  let browser;
  try {
    for (let attempt = 0; ; attempt++) {
      try { await fetch(base + '?fixture=reset'); break; }
      catch (error) { if (attempt >= 30) throw error; await sleep(100); }
    }
    browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_BINARY || undefined });
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.on('pageerror', error => report.failures.push('Browser JS: ' + error.message));
    const reset = async count => {
      const response = await page.request.get(base + '?fixture=reset&count=' + count);
      check(response.ok(), 'reset disposable RAM fixtures');
    };
    const navigate = async url => {
      const response = await page.goto(url);
      if (!response.ok()) throw new Error(`HTTP ${response.status()}: ${(await page.locator('body').innerText()).slice(0, 600)}`);
      await page.waitForFunction(() => document.body.classList.contains('sidebar-enhanced'));
    };
    const listContext = (slug, pageNumber = '2') => {
      const query = new URL(page.url()).searchParams;
      check(query.get('route') === '/admin/' + slug && query.get('page') === pageNumber && query.get('q') === 'UX', `${slug}: server redirect retains page/search`);
    };
    const clickNavigation = async locator => {
      await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), locator.click()]);
    };
    const action = async kind => {
      const row = page.locator('.catalog-table tbody tr').first();
      const menu = row.locator('[data-row-actions] summary');
      if (await menu.count()) await menu.click();
      await clickNavigation(row.locator('a.list-action--' + kind));
    };
    const submit = async () => {
      await clickNavigation(page.locator('.admin-workspace form[method="post"] button[type="submit"]').first());
    };
    const checkBack = async (slug, width) => {
      const back = page.locator('.admin-back-link').first();
      const target = new URL(await back.getAttribute('href'), base).searchParams;
      const style = await back.evaluate(element => { const css = getComputedStyle(element); return { border: parseFloat(css.borderTopWidth), radius: parseFloat(css.borderTopLeftRadius), font: parseFloat(css.fontSize), background: css.backgroundColor }; });
      check(target.get('route') === '/admin/' + slug && target.get('page') === '2', `${slug} ${width}: back returns to correct module/page`);
      check(style.border >= 1 && style.radius >= 8 && style.font >= 14 && style.background === 'rgb(255, 255, 255)', `${slug} ${width}: shared outlined back button style`);
    };
    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 780 });
      for (const [slug, name] of Object.entries(modules)) {
        await reset(20);
        const filters = { page: '2', per_page: '10', q: 'UX' };
        if (slug === 'dich-vu' || slug === 'bang-gia') Object.assign(filters, {
          ma_co_so: '2', trang_thai: slug === 'dich-vu' ? 'DANG_CUNG_CAP' : 'NGUNG',
          ...(slug === 'dich-vu' ? { loai_dich_vu: 'DO_UONG', don_vi_tinh: 'chai' } : { ma_loai_san: '2', loai_ngay: 'NGAY_THUONG' }),
        });
        await navigate(address(slug, filters));
        await clickNavigation(page.locator('.module-heading a.btn--primary').first());
        await checkBack(slug, width);
        await clickNavigation(page.locator('.admin-back-link').first()); listContext(slug);
        if (slug === 'bo-mon') {
          const row = page.locator('.catalog-table tbody tr').first();
          check(await row.locator('a.list-action').count() === 2 && await row.locator('a.list-action--view').count() === 0, `bo-mon ${width}: list exposes only edit/delete`);
          const edit = new URL(await row.locator('a.list-action--edit').getAttribute('href'), base);
          edit.searchParams.set('route', '/admin/bo-mon/show');
          await navigate(edit.href); // Existing detail route and return context remain supported.
        } else await action('view');
        await checkBack(slug, width);
        await clickNavigation(page.locator('a.detail-back'));
        listContext(slug);
        await action('edit');
        const cancelHref = await page.locator('.catalog-actions a').last().getAttribute('href');
        check(new URL(cancelHref, base).searchParams.get('page') === '2', `${slug} ${width}: cancel retains list page`);
        await page.locator(`[name="${name}"]`).fill('');
        await submit();
        check((await page.locator('.field-error').count()) > 0, `${slug} ${width}: real validation errors rendered`);
        check(new URL(await page.locator('.admin-workspace form[method="post"]').first().getAttribute('action'), base).searchParams.has('return_to'), `${slug} ${width}: validation keeps return context`);
        await page.locator(`[name="${name}"]`).fill(`UX ${slug} edited ${width}`);
        await submit();
        listContext(slug);
        check(await page.locator('[role="status"]').count() > 0, `${slug} ${width}: success notice survives redirect`);
        for (const [field, value] of Object.entries(filters)) {
          if (field === 'per_page') continue; // 10 is the default and may be omitted canonically.
          check(new URL(page.url()).searchParams.get(field) === value, `${slug} ${width}: retained ${field}`);
        }
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${slug} ${width}: no document overflow`);
        if (slug === 'dich-vu' || slug === 'bang-gia') {
          await action(slug === 'dich-vu' ? 'stop' : 'activate');
          if (await page.locator('input[name="confirmed"]').count()) await page.locator('input[name="confirmed"]').check();
          await submit();
        } else {
          await action('delete');
          await submit();
        }
        listContext(slug);
        check(await page.locator('[role="status"]').count() > 0, `${slug} ${width}: delete/status success notice`);
        if (slug === 'bang-gia') {
          await navigate(address(slug, { page: '2', q: 'UX' }));
          await action('stop');
          await page.locator('input[name="confirmed"]').check();
          await submit(); listContext(slug);
          check(await page.locator('[role="status"]').count() > 0, `pricing ${width}: stop confirms and returns to page 2`);
        }
      }

      // Chi's existing CRUD uses inline POST confirmations, never newly invented deletes.
      for (const [slug, name] of Object.entries({ companies: 'ten_cong_ty', contracts: 'so_hop_dong', employees: 'ho_ten' })) {
        await reset(120);
        const filters = { page: '2', q: 'UX', sort: slug === 'contracts' ? 'start_asc' : 'name_asc',
          trang_thai: slug === 'companies' ? 'HOAT_DONG' : slug === 'contracts' ? 'HIEU_LUC' : 'DANG_LAM' };
        if (slug !== 'companies') filters.ma_cong_ty = '1';
        if (slug === 'contracts') Object.assign(filters, { tu_ngay: '2020-01-01', den_ngay: '2030-12-31' });
        await navigate(address(slug, filters));
        check(await page.locator('.catalog-table tbody tr').count() === 10, `${slug} ${width}: backend page 2 has ten records`);
        if (slug === 'contracts') {
          const geometry = await page.locator('tbody tr').first().evaluate(row => {
            const form = row.querySelector('.contract-actions');
            const edit = form.querySelector('a').getBoundingClientRect(), select = form.querySelector('select').getBoundingClientRect(), update = form.querySelector('button').getBoundingClientRect();
            return { edit, select, update, order: [...form.querySelectorAll('select, a, button')].map(control => control.tagName).join(','), width: form.clientWidth, overflow: form.scrollWidth > form.clientWidth + 1, rowHeight: row.clientHeight };
          });
          check(!geometry.overflow && [geometry.edit, geometry.select, geometry.update].every(rect => Math.abs(rect.height - 38) < 1), `contracts ${width}: actions fit and share 38px height`);
          check(geometry.order === 'SELECT,A,BUTTON', `contracts ${width}: DOM/tab order is status, edit, update`);
          check(Math.abs(geometry.edit.y - geometry.update.y) < 1 && geometry.update.x > geometry.edit.x, `contracts ${width}: edit and update stay adjacent on one row`);
          if (geometry.width >= 330) check(Math.abs(geometry.edit.y - geometry.select.y) < 1 && geometry.select.x < geometry.edit.x, `contracts ${width}: dropdown precedes adjacent buttons on desktop`);
          else check(geometry.select.bottom <= geometry.edit.top + 1, `contracts ${width}: narrow layout keeps the button pair together below status`);
          if (width === 1440) check(geometry.rowHeight < 100, 'contracts desktop: no oversized row from stacked status form');
        }
        if (slug === 'employees') {
          await clickNavigation(page.locator('tbody tr th a.admin-table-link').first());
          const detailText = await page.locator('.detail-page').innerText();
          check(['Thông tin nhân viên', 'Tài khoản đăng nhập', 'Phân quyền', 'memory.'].every(text => detailText.includes(text)), `employees ${width}: read-only cards show login and scope`);
          check(!(await page.content()).includes('FIXTURE_SECRET_NEVER_RENDER'), `employees ${width}: account secret sentinel never reaches HTML`);
          check(await page.locator('.detail-page form, .detail-page input, .detail-edit').count() === 0, `employees ${width}: details have no write/reset action`);
          check(new URL(await page.locator('.detail-back').getAttribute('href'), base).searchParams.get('page') === '2', `employees ${width}: detail back preserves page`);
          check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `employees ${width}: detail fits viewport`);
          await clickNavigation(page.locator('.detail-back')); listContext(slug);
        }
        await clickNavigation(page.locator('.module-heading a.btn--primary').first());
        await checkBack(slug, width);
        if (slug === 'companies') {
          await page.locator('[name="ten_cong_ty"]').fill(`UX New company ${width}`);
          await page.locator('[name="dia_chi"]').fill('Address only in RAM'); await submit(); listContext(slug);
          check(await page.locator('[role="status"]').count() > 0, `company ${width}: create POST retains context and flash`);
        } else {
          await clickNavigation(page.locator('.admin-back-link').first()); listContext(slug);
        }
        await action('edit');
        await checkBack(slug, width);
        const back = page.locator('.admin-back-link').first();
        check(new URL(await back.getAttribute('href'), base).searchParams.get('sort') === filters.sort, `${slug} ${width}: back retains sorting`);
        const input = page.locator(`[name="${name}"]`);
        await input.fill(`UX ${slug} updated ${width}`);
        // Trigger real server validation, preserving old data and return context.
        const form = page.locator('.admin-workspace form[method="post"]').first();
        await form.evaluate(element => element.noValidate = true);
        await input.fill(''); await submit();
        check(await page.locator('.field-error').count() > 0, `${slug} ${width}: validation rendered without losing context`);
        await input.fill(`UX ${slug} updated ${width}`); await submit();
        listContext(slug);
        for (const [key, value] of Object.entries(filters)) check(new URL(page.url()).searchParams.get(key) === value, `${slug} ${width}: edit retains ${key}`);
        check(await page.locator('[role="status"]').count() > 0, `${slug} ${width}: edit flash visible`);
        const statusForm = page.locator('.catalog-table tbody tr').first().locator('form');
        if (slug === 'contracts') await statusForm.locator('select[name="trang_thai"]').selectOption('HET_HAN');
        const navigation = page.waitForNavigation({ waitUntil: 'load' });
        await statusForm.locator('button[type="submit"]').click();
        await page.locator('#workspace-confirm[open] [data-confirm-accept]').click();
        await navigation; listContext(slug);
        check(await page.locator('[role="status"]').count() > 0, `${slug} ${width}: inline status confirmation preserves flash/page`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${slug} ${width}: compact filters fit viewport`);
        if (width === 1440 || width === 375) await page.screenshot({ path: path.join(output, `${slug}-${width}.png`), fullPage: false });
      }

      // Money is displayed locally but the named hidden field keeps the server decimal format.
      for (const slug of ['bang-gia', 'dich-vu', 'contracts']) {
        await reset(20);
        const fieldName = slug === 'contracts' ? 'phi_nhuong_quyen' : 'don_gia';
        await navigate(address(slug + '/edit', { id: '10', return_to: returnTo(slug, { page: '2', q: 'UX' }) }));
        const field = page.locator('#' + fieldName);
        const raw = page.locator(`input[name="${fieldName}"]`);
        check(await field.inputValue() === (slug === 'contracts' ? '60.000' : '10.000'), `${slug} ${width}: initial amount is formatted`);
        for (const [typed, formatted, decimal] of [['60000', '60.000', '60000'], ['60.000', '60.000', '60000'],
          ['60000.50', '60.000,50', '60000.50'], ['0', '0', '0'], ['9999999999.99', '9.999.999.999,99', '9999999999.99'],
          ['', '', ''], ['-1', '-1', '-1'], ['invalid', 'invalid', 'invalid']]) {
          await field.fill(typed); await field.blur();
          check(await field.inputValue() === formatted && await raw.inputValue() === decimal, `${slug} ${width}: money display/raw preserve ${typed || 'empty'}`);
        }
        await field.fill('123456');
        await field.evaluate(element => { element.focus(); element.setSelectionRange(1, 1); });
        await page.keyboard.type('2');
        check(await field.inputValue() === '1.223.456' && await field.evaluate(element => element.selectionStart) === 3, `${slug} ${width}: middle insertion preserves digits/caret`);
        await field.fill('123456');
        await field.evaluate(element => { element.focus(); element.setSelectionRange(4, 4); }); await page.keyboard.press('Backspace');
        check(await raw.inputValue() === '12456', `${slug} ${width}: deleting at thousands separator removes intended digit`);
        await field.fill('60.000');
        const requestPromise = page.waitForRequest(request => request.method() === 'POST' && new URL(request.url()).searchParams.get('route') === '/admin/' + slug + '/update');
        // Enter submits through the original form, including canonical money/CSRF/return context.
        await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), field.press('Enter')]);
        const request = await requestPromise;
        check(new URLSearchParams(request.postData()).get(fieldName) === '60000', `${slug} ${width}: Enter POST sends 60000, not formatted text`);
        listContext(slug);
        await navigate(address(slug + '/edit', { id: '10' }));
        check(await field.inputValue() === '60.000' && /^60000(?:\.00)?$/.test(await raw.inputValue()), `${slug} ${width}: saved value remains sixty thousand`);
        if ((width === 1440 || width === 375) && slug !== 'contracts') {
          await field.scrollIntoViewIfNeeded();
          await page.screenshot({ path: path.join(output, `${slug}-money-${width}.png`), fullPage: false });
        }
        if (slug === 'contracts') {
          await field.fill('9999999999999.99');
          check(await field.inputValue() === '9.999.999.999.999,99' && await raw.inputValue() === '9999999999999.99', `contract ${width}: full schema precision preserved before backend normalization`);
        }
      }
      await navigate(address('dich-vu/edit', { id: '10', return_to: returnTo('dich-vu', { page: '2', q: 'UX' }) }));
      for (const [typed, expected] of [['', ''], ['-1', '-1'], ['10000000000', '10000000000'], ['1,234', '1.234']]) {
        await page.locator('#don_gia').fill(typed); await submit();
        check(await page.locator('#don_gia-error').count() === 1 && await page.locator('[name="don_gia"]').inputValue() === expected, `service ${width}: invalid amount retained by backend validation: ${typed || 'empty'}`);
      }
      await page.locator('#don_gia').evaluate(element => { element.value = '60.000,50'; element.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertFromPaste', data: '60.000,50' })); });
      check(await page.locator('[name="don_gia"]').inputValue() === '60000.50', `service ${width}: localized paste preserves cents`);
      await submit(); listContext('dich-vu');

      // Every paginated module: hard delete, or leaving the last filtered status page.
      for (const slug of [...Object.keys(modules), 'companies', 'contracts', 'employees']) {
        await reset(11);
        const business = ['companies', 'contracts', 'employees'].includes(slug);
        const status = slug === 'dich-vu' ? 'DANG_CUNG_CAP' : slug === 'bang-gia' ? 'NGUNG'
          : slug === 'companies' ? 'HOAT_DONG' : slug === 'contracts' ? 'HIEU_LUC' : slug === 'employees' ? 'DANG_LAM' : null;
        // Admin now sees all personnel. Keep this fixture's original 11-manager last-page case.
        await navigate(address(slug, { page: '2', q: 'UX', ...(status ? { trang_thai: status } : {}), ...(slug === 'employees' ? { vai_tro: 'QUAN_LY_CONG_TY' } : {}) }));
        if (business) {
          const form = page.locator('.catalog-table tbody tr').first().locator('form');
          if (slug === 'contracts') await form.locator('select').selectOption('HET_HAN');
          const navigation = page.waitForNavigation({ waitUntil: 'load' });
          await form.locator('button[type="submit"]').click(); await page.locator('#workspace-confirm[open] [data-confirm-accept]').click(); await navigation;
        } else {
          await action(status ? (slug === 'dich-vu' ? 'stop' : 'activate') : 'delete');
          if (await page.locator('input[name="confirmed"]').count()) await page.locator('input[name="confirmed"]').check();
          await submit();
        }
        check(new URL(page.url()).searchParams.get('page') === null, `${slug} ${width}: last-page deletion redirects to page 1`);
        check((await page.locator('.catalog-table tbody tr').count()) === 10 && await page.locator('[role="status"]').count() > 0, `${slug} ${width}: clamped list retains rows and notice`);
        if (status) check(new URL(page.url()).searchParams.get('trang_thai') === status, `${slug} ${width}: empty last page retains status filter`);
        if (slug === 'employees') check(new URL(page.url()).searchParams.get('vai_tro') === 'QUAN_LY_CONG_TY', `employees ${width}: clamped list retains role filter`);
      }

      await reset(20);
      await navigate(address('dich-vu'));
      if (width < 1024) await page.locator('#admin-menu-toggle').click();
      await page.evaluate(() => { const nav = document.querySelector('.admin-sidebar__nav'); nav.scrollTop = nav.scrollHeight; });
      await page.waitForTimeout(60);
      const scrollBefore = await page.locator('.admin-sidebar__nav').evaluate(nav => nav.scrollTop);
      await clickNavigation(page.locator('.admin-sidebar a', { hasText: 'Bảng giá' }));
      if (width < 1024) await page.locator('#admin-menu-toggle').click();
      check(await page.locator('.admin-sidebar__nav').evaluate(nav => nav.scrollTop) > 0 && scrollBefore > 0, `sidebar ${width}: scroll restored across real navigation`);
      check(await page.locator('.admin-menu__item[aria-current="page"]').innerText() === 'Bảng giá', `sidebar ${width}: active menu matches route`);
      const activeVisible = await page.evaluate(() => {
        const nav = document.querySelector('.admin-sidebar__nav').getBoundingClientRect();
        const active = document.querySelector('.admin-menu__item[aria-current="page"]').getBoundingClientRect();
        return active.top >= nav.top - 1 && active.bottom <= nav.bottom + 1;
      });
      check(activeVisible, `sidebar ${width}: active link visible with minimal scrolling`);
      check(await page.evaluate(() => scrollY === 0), `sidebar ${width}: main scroll unaffected`);
      await clickNavigation(page.locator('.admin-sidebar a', { hasText: 'Dịch vụ' }));
      if (width < 1024) await page.locator('#admin-menu-toggle').click();
      check(await page.locator('.admin-sidebar__nav').evaluate(nav => nav.scrollTop) > 0, `sidebar ${width}: second navigation retains scroll`);
      for (const label of ['Công ty nhượng quyền', 'Hợp đồng nhượng quyền', 'Nhân viên', 'Dịch vụ']) {
        await clickNavigation(page.locator('.admin-sidebar a', { hasText: label }));
        if (width < 1024) await page.locator('#admin-menu-toggle').click();
        check((await page.locator('.admin-menu__item[aria-current="page"]').innerText()) === label, `sidebar ${width}: cross-module active ${label}`);
      }
      if (width >= 1024) {
        const expandedScroll = await page.locator('.admin-sidebar__nav').evaluate(nav => nav.scrollTop);
        await page.locator('[data-sidebar-collapse]').click();
        check(await page.locator('body').evaluate(body => body.classList.contains('sidebar-compact')), `sidebar ${width}: compact works`);
        await page.locator('[data-sidebar-collapse]').click();
        check(await page.locator('.admin-sidebar__nav').evaluate(nav => {
          const box = nav.getBoundingClientRect(), active = nav.querySelector('[aria-current="page"]').getBoundingClientRect();
          return active.top >= box.top - 1 && active.bottom <= box.bottom + 1;
        }), `sidebar ${width}: expanded active link remains visible`);
        check(Math.abs(await page.locator('.admin-sidebar__nav').evaluate(nav => nav.scrollTop) - expandedScroll) <= 1, `sidebar ${width}: compact mode cannot overwrite expanded scroll position`);
      }
      const screenshot = path.join(output, `navigation-${width}.png`);
      await page.screenshot({ path: screenshot, fullPage: width >= 1024 }); report.screenshots.push(screenshot);
      if (width < 1024) await page.locator('#admin-sidebar-close').click();
      console.log(`Checked Admin CRUD/context/sidebar at ${width}px.`);
    }

    for (const size of ['20', '50']) {
      await reset(120);
      await navigate(address('dich-vu', { page: '2', per_page: size, q: 'UX' }));
      await action('edit'); await submit();
      listContext('dich-vu');
      check(new URL(page.url()).searchParams.get('per_page') === size, `service retains page size ${size}`);
    }
    const token = await page.locator('input[name="_token"]').first().inputValue();
    for (const role of ['KHACH_HANG', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN']) {
      const detail = await page.request.get(address('employees/show', { id: '1', fixture_role: role }));
      check(detail.status() === 403, `${role}: direct Admin employee detail URL denied`);
      for (const slug of [...Object.keys(modules), 'companies', 'contracts', 'employees']) {
        const response = await page.request.get(address(slug, { fixture_role: role, return_to: address(slug, { page: '2' }) }));
        check(response.status() === 403, `${role} cannot use return context to access ${slug}`);
        const post = await page.request.post(address(slug + '/update', { fixture_role: role, return_to: address(slug, { page: '2' }) }), { form: { _token: token, id: '1' } });
        check(post.status() === 403 && (await post.text()).includes('quyền'), `${role} POST with valid CSRF still cannot access ${slug}`);
      }
    }
    const csrf = await page.request.post(address('dich-vu/update'), { form: { id: '1', _token: 'invalid', return_to: address('dich-vu', { page: '2' }) } });
    check(csrf.status() === 403 && (await csrf.text()).includes('Phiên biểu mẫu'), 'invalid CSRF still rejected before CRUD');
    for (const slug of ['companies', 'contracts', 'employees']) {
      await navigate(address(slug));
      check(await page.locator('#admin-sidebar').count() === 1, `${slug} retains shared sidebar without adding pagination`);
    }

    // Real company-employee routes retain their original scope and do not expose other companies.
    const companyContext = await browser.newContext({ extraHTTPHeaders: { 'X-STRYKE-TEST-ROLE': 'QUAN_LY_CONG_TY' }, viewport: { width: 768, height: 780 } });
    const companyPage = await companyContext.newPage();
    const companyAddress = query => base + '?' + new URLSearchParams({ route: '/company/employees', ...query });
    await companyPage.request.get(base + '?fixture=reset&count=120');
    const scoped = { page: '2', q: 'UX', sort: 'name_asc', ma_co_so: '2', vai_tro: 'QUAN_LY_CO_SO', trang_thai: 'DANG_LAM' };
    await companyPage.goto(companyAddress(scoped));
    check(await companyPage.locator('tbody tr').count() === 10 && await companyPage.locator('#filter-ma_co_so option[value="3"]').count() === 0, 'company employee list/options exclude foreign facility');
    check(!(await companyPage.locator('tbody').innerText()).includes('ux200'), 'company cannot see foreign employee email');
    await Promise.all([companyPage.waitForNavigation(), companyPage.locator('tbody tr th a.admin-table-link').first().click()]);
    check((await companyPage.locator('.detail-page').innerText()).includes('memory.10'), 'company employee detail shows scoped username');
    check(await companyPage.locator('.admin-menu__item[aria-current="page"]').innerText() === 'Nhân viên', 'company detail retains correct sidebar active item');
    const scopedBack = new URL(await companyPage.locator('.detail-back').getAttribute('href'), base).searchParams;
    for (const [key, value] of Object.entries(scoped)) check(scopedBack.get(key) === value, 'company detail back preserves ' + key);
    await Promise.all([companyPage.waitForNavigation(), companyPage.locator('.detail-back').click()]);
    const detailUrl = id => base + '?' + new URLSearchParams({ route: '/company/employees/show', id });
    for (const id of ['2001', '1', '999999']) {
      const denied = await companyPage.request.get(detailUrl(id));
      check(denied.status() === 404 && !(await denied.text()).includes('memory.'), 'company direct detail excludes foreign/manager/missing employee ' + id);
    }
    const noAccount = await companyPage.request.get(detailUrl('9000'));
    check(noAccount.ok() && (await noAccount.text()).includes('Chưa có tài khoản đăng nhập'), 'own employee without account has useful read-only empty state');
    for (const role of ['KHACH_HANG', 'QUAN_LY_CO_SO', 'NHAN_VIEN', 'QUAN_TRI_TAP_DOAN']) {
      const denied = await page.request.get(detailUrl('1001') + '&fixture_role=' + role);
      check(denied.status() === 403, role + ': direct company employee detail URL denied');
    }
    await Promise.all([companyPage.waitForNavigation(), companyPage.locator('tbody tr').first().locator('a.list-action--edit').click()]);
    await companyPage.locator('[name="ho_ten"]').fill('UX Scoped edit');
    await Promise.all([companyPage.waitForNavigation(), companyPage.locator('.admin-workspace form[method="post"] button[type="submit"]').click()]);
    for (const [key, value] of Object.entries(scoped)) check(new URL(companyPage.url()).searchParams.get(key) === value, 'company employee edit retains ' + key);
    const foreignFacility = await companyPage.request.get(companyAddress({ ma_co_so: '3' }));
    check(foreignFacility.status() === 403, 'company foreign facility filter rejected');
    const companyToken = await companyPage.locator('[name="_token"]').first().inputValue();
    const foreignEmployee = await companyPage.request.post(base + '?' + new URLSearchParams({ route: '/company/employees/update' }), { form: { id: '2001', _token: companyToken } });
    check(foreignEmployee.status() === 404, 'company cannot edit foreign employee with valid CSRF');
    await companyPage.goto(companyAddress({ ma_cong_ty: '2' }));
    check(!(await companyPage.locator('tbody').innerText()).includes('ux200'), 'client company filter cannot override authenticated scope');
    await companyContext.close();

    const animated = await browser.newContext({ reducedMotion: 'no-preference', viewport: { width: 1440, height: 780 } });
    const animatedPage = await animated.newPage();
    await animatedPage.goto(address('contracts'));
    const button = animatedPage.locator('.contract-actions .list-action--edit').first();
    const row = animatedPage.locator('tbody tr').first();
    const rowBefore = await row.boundingBox();
    const duration = await button.evaluate(element => getComputedStyle(element).transitionDuration.split(',').map(parseFloat));
    check(duration.every(value => value >= .15 && value <= .22), 'action transitions use requested 150–220ms duration');
    await button.hover(); await animatedPage.waitForTimeout(230);
    const hovered = await button.evaluate(element => new DOMMatrix(getComputedStyle(element).transform).m42);
    check(Math.abs(hovered + 1) < .1, 'action hover lifts by one pixel');
    const rowAfter = await row.boundingBox();
    check(['x', 'y', 'width', 'height'].every(key => Math.abs(rowBefore[key] - rowAfter[key]) < .1), 'hover transform causes no table layout shift');
    await animatedPage.mouse.down(); await animatedPage.waitForTimeout(230);
    check(Math.abs(await button.evaluate(element => new DOMMatrix(getComputedStyle(element).transform).m42) - 1) < .1, 'pressed action gives one-pixel feedback');
    await animatedPage.mouse.move(0, 0); await animatedPage.mouse.up();
    const active = animatedPage.locator('.admin-menu__item[aria-current="page"]');
    const activeBefore = await active.boundingBox();
    await active.hover(); await animatedPage.waitForTimeout(230);
    const activeAfter = await active.boundingBox();
    check(['x', 'y', 'width', 'height'].every(key => Math.abs(activeBefore[key] - activeAfter[key]) < .1), 'active sidebar hover preserves position and size');
    check(await active.evaluate(element => getComputedStyle(element).backgroundColor) === 'rgb(183, 239, 124)', 'active sidebar keeps STRYKE green');
    check(!(await animatedPage.locator('.admin-sidebar').innerText()).includes('Sắp phát triển'), 'Admin sidebar no longer renders the disabled payment item');
    for (const label of ['Quản lý booking', 'Tài khoản ngân hàng', 'Hóa đơn']) check(await animatedPage.locator('.admin-sidebar a', { hasText: label }).count() === 1, 'sidebar retains ' + label);
    await animatedPage.emulateMedia({ reducedMotion: 'reduce' });
    await button.hover();
    check(await button.evaluate(element => getComputedStyle(element).transitionDuration === '0s' && getComputedStyle(element).transform === 'none'), 'reduced motion disables action transition and transform');
    await animated.close();

    const noJs = await browser.newContext({ javaScriptEnabled: false });
    const noJsPage = await noJs.newPage();
    await noJsPage.goto(address('dich-vu/edit', { id: '10', return_to: returnTo('dich-vu', { page: '2', q: 'UX' }) }));
    await noJsPage.locator('[name="don_gia"]').fill('60000');
    await Promise.all([noJsPage.waitForNavigation(), noJsPage.locator('.admin-workspace form[method="post"] button[type="submit"]').click()]);
    check(new URL(noJsPage.url()).searchParams.get('page') === '2', 'without JS, original canonical money form and return context remain usable');
    await noJs.close();
  } catch (error) {
    report.failures.push(error.stack || String(error));
    const failedPage = browser?.contexts()[0]?.pages()[0];
    if (failedPage) {
      report.failedUrl = failedPage.url();
      await failedPage.screenshot({ path: path.join(output, 'failure.png'), fullPage: true }).catch(() => {});
      fs.writeFileSync(path.join(output, 'failure.html'), await failedPage.content().catch(() => ''));
    }
  } finally {
    if (browser) await browser.close();
    server.kill();
    fs.writeFileSync(path.join(output, 'server.log'), serverLog);
    fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
  }
  if (report.failures.length) { console.error(report.failures.join('\n')); process.exitCode = 1; }
  else console.log(`PASS: ${report.checks.length} Chrome Admin navigation checks at 1440/1024/768/375; no application database writes.`);
})();
