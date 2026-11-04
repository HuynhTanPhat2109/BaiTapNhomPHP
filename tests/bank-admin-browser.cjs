// Real Chrome + production routes/views/controllers; SQLite RAM fixture only.
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright-core');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const root = path.resolve(__dirname, '..');
const out = path.join(root, 'storage/temp/bank-admin-ui');
const before = process.argv.includes('--before');
const port = Number(process.env.BANK_TEST_PORT || 8138);
const base = `http://127.0.0.1:${port}`;
const url = (route, query = {}) => `${base}/index.php?${new URLSearchParams({ route, ...query })}`;
const checks = [];
let browser;
let serverLog = '';
fs.mkdirSync(out, { recursive: true });
const server = spawn(process.env.PHP_BINARY || 'D:/PHP/php/php.exe', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/fixtures/bank-admin.php'], {
    cwd: root, windowsHide: true, env: { ...process.env, STRYKE_BANK_ADMIN_TEST: '1' }, stdio: ['ignore', 'pipe', 'pipe'],
});
server.stdout.on('data', data => { serverLog += data; });
server.stderr.on('data', data => { serverLog += data; });
function check(ok, label) {
    checks.push({ label, pass: Boolean(ok) });
    if (!ok) throw new Error(label);
}
async function ready() {
    for (let i = 0; i < 100; i++) {
        if (server.exitCode !== null) throw new Error('PHP server stopped');
        if (await new Promise(resolve => {
            const socket = net.createConnection({ port, host: '127.0.0.1' });
            socket.once('connect', () => { socket.destroy(); resolve(true); });
            socket.once('error', () => resolve(false));
        })) return;
        await new Promise(resolve => setTimeout(resolve, 50));
    }
    throw new Error('PHP test server did not start');
}
async function layout(page, label) {
    const metrics = await page.evaluate(() => {
        const visible = element => element.getBoundingClientRect().width > 0 && element.getBoundingClientRect().height > 0;
        const clipped = [...document.querySelectorAll('.bank-page .catalog-status, .bank-page .list-action, .bank-page .form-control')]
            .filter(visible).filter(element => element.scrollWidth > element.clientWidth + 2).map(element => element.textContent.trim());
        const table = document.querySelector('.catalog-table-wrap');
        const outsideCell = [...document.querySelectorAll('.bank-page tbody td:last-child .list-action')].filter(visible).some(button => {
            const bounds = button.closest('td').getBoundingClientRect(), rect = button.getBoundingClientRect();
            return rect.left < bounds.left - 1 || rect.right > bounds.right + 1;
        });
        return { width: innerWidth, scroll: document.documentElement.scrollWidth, clipped, outsideCell,
            tableOverflow: table ? table.scrollWidth - table.clientWidth : 0 };
    });
    check(metrics.scroll <= metrics.width + 1 && metrics.clipped.length === 0, `${label}: no overflow or clipped controls ${JSON.stringify(metrics)}`);
    check(!metrics.outsideCell && metrics.tableOverflow <= 1, `${label}: table/actions fit without hidden overflow`);
}
async function post(context, route, data) {
    return context.request.post(url(route), { form: data, maxRedirects: 0 });
}
async function formSubmit(page) {
    await Promise.all([page.waitForNavigation(), page.locator('.module-form button[type="submit"]').click()]);
}
async function confirmAction(page, form) {
    await form.locator('button[type="submit"]').click();
    check(await page.locator('#workspace-confirm').isVisible(), 'bank action retains confirmation dialog');
    await Promise.all([page.waitForNavigation(), page.locator('[data-confirm-accept]').click()]);
}
async function managerFlow(context, page, role) {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(url('/__bank_fixture/reset'));
    if (role === 'QUAN_LY_CONG_TY') {
        await page.goto(url('/facility/bank'));
        check(await page.locator('#bank-facility option[value="3"]').count() === 0, 'company picker excludes foreign facilities');
        await page.selectOption('#bank-facility', '2');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Xem tài khoản', exact: true }).click()]);
        check((await page.locator('.bank-page').innerText()).includes('TESTACCOUNT003'), 'company picker selects owned facility');
        await page.locator('.bank-page .list-action--edit').click();
        const breadcrumb = page.locator('.workspace-breadcrumb a').last();
        check((await breadcrumb.getAttribute('href')).includes('facility_id=2'), 'breadcrumb preserves chosen facility');
        await page.locator('.catalog-actions a').click();
        check(new URL(page.url()).searchParams.get('facility_id') === '2', 'cancel returns to selected facility');
    }
    await page.goto(url('/facility/bank/create', { facility_id: '1' }));
    await page.locator('#so_tai_khoan').fill('BAD#ACCOUNT');
    await formSubmit(page);
    check((await page.locator('.field-error').count()) >= 3, `${role}: field validation preserved`);
    check(await page.locator('#so_tai_khoan').inputValue() === 'BAD#ACCOUNT', `${role}: invalid input preserved`);
    await page.screenshot({ path: path.join(out, `${role}-validation.png`), fullPage: true });
    await page.selectOption('#bank_code', 'VCB');
    await page.locator('#so_tai_khoan').fill('TESTNEWACCOUNT');
    await page.locator('#ten_chu_tai_khoan').fill('TAI KHOAN KIEM THU MOI');
    await formSubmit(page);
    check((await page.locator('.alert--success').innerText()).includes('Thêm'), `${role}: create succeeds with notice`);
    let row = page.locator('tbody tr').filter({ hasText: 'TESTNEWACCOUNT' });
    check(await row.count() === 1, `${role}: created account appears`);
    for (const width of [1440, 1280, 1024, 768, 375]) {
        await page.setViewportSize({ width, height: 1000 });
        await layout(page, `${role}/${width}/three actions`);
        await page.screenshot({ path: path.join(out, `${role}-three-actions-${width}.png`), fullPage: true });
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await row.locator('.list-action--edit').click();
    const token = await page.locator('.module-form input[name="_token"]').inputValue();
    const id = await page.locator('.module-form input[name="id"]').inputValue();
    await page.locator('#ten_chu_tai_khoan').fill('TAI KHOAN KIEM THU DA SUA');
    await formSubmit(page);
    row = page.locator('tbody tr').filter({ hasText: 'TESTNEWACCOUNT' });
    check((await row.innerText()).includes('TAI KHOAN KIEM THU DA SUA'), `${role}: edit persists`);
    check((await page.locator('.alert--success').innerText()).includes('Cập nhật'), `${role}: edit notice`);
    await confirmAction(page, row.locator('form[action*="toggle-status"]'));
    row = page.locator('tbody tr').filter({ hasText: 'TESTNEWACCOUNT' });
    check((await row.locator('[data-label="Trạng thái"]').innerText()).includes('Ngừng'), `${role}: disable works`);
    await confirmAction(page, row.locator('form[action*="toggle-status"]'));
    row = page.locator('tbody tr').filter({ hasText: 'TESTNEWACCOUNT' });
    check((await row.locator('[data-label="Trạng thái"]').innerText()).includes('Hoạt động'), `${role}: activation works`);
    await confirmAction(page, row.locator('form[action*="set-default"]'));
    row = page.locator('tbody tr').filter({ hasText: 'TESTNEWACCOUNT' });
    check((await row.innerText()).includes('Nhận tiền chính') && await row.locator('form[action*="set-default"]').count() === 0, `${role}: set default works`);
    await confirmAction(page, row.locator('form[action*="toggle-status"]'));
    check((await page.locator('[role="alert"]').innerText()).includes('mặc định trước'), `${role}: stopping current default is blocked with explanation`);
    row = page.locator('tbody tr').filter({ hasText: 'TESTNEWACCOUNT' });
    check((await row.locator('[data-label="Trạng thái"]').innerText()).includes('Hoạt động'), `${role}: blocked stop preserves active state`);
    const inactive = await post(context, '/facility/bank/set-default', { id: '2', _token: token });
    check(inactive.status() === 303, `${role}: forged inactive default redirects safely`);
    await page.goto(url('/facility/bank', { facility_id: '1' }));
    check((await page.locator('[role="alert"]').innerText()).includes('kích hoạt'), `${role}: inactive default rejected, not auto-activated`);
    await page.goto(url('/facility/bank/edit', { id: '2' }));
    check(await page.locator('.module-form [name="trang_thai"], .module-form [name="la_mac_dinh"]').count() === 0, `${role}: edit cannot toggle state/default`);
    await page.locator('#ten_chu_tai_khoan').fill('SUA TAI KHOAN TAM NGUNG'); await formSubmit(page);
    check((await page.locator('tbody tr').filter({ hasText: 'TESTACCOUNT002' }).innerText()).includes('Ngừng'), `${role}: editing inactive account keeps inactive`);
    await page.goto(url('/facility/bank/create', { facility_id: '1' }));
    await page.selectOption('#bank_code', 'MB');
    await page.locator('#so_tai_khoan').fill('TESTINACTIVE');
    await page.locator('#ten_chu_tai_khoan').fill('TAI KHOAN CHUA SU DUNG');
    await page.selectOption('#bank-status', 'NGUNG');
    await page.locator('[name="la_mac_dinh"]').check(); await formSubmit(page);
    check(await page.locator('#la_mac_dinh-error').count() === 1 && await page.locator('#bank-status').inputValue() === 'NGUNG' && await page.locator('#so_tai_khoan').inputValue() === 'TESTINACTIVE', `${role}: inactive-default validation retains state and entered details`);
    await page.locator('[name="la_mac_dinh"]').uncheck(); await formSubmit(page);
    const createdInactive = page.locator('tbody tr').filter({ hasText: 'TESTINACTIVE' });
    check((await createdInactive.locator('[data-label="Trạng thái"]').innerText()).includes('Ngừng') && await createdInactive.locator('form[action*="set-default"]').count() === 0, `${role}: create explicitly inactive remains inactive and cannot be default`);
    for (const action of ['store', 'update', 'set-default', 'toggle-status']) {
        const csrf = await post(context, `/facility/bank/${action}`, { id, facility_id: '1', _token: 'invalid' });
        check(csrf.status() === 403, `${role}: ${action} rejects bad CSRF`);
    }
    for (const foreignId of role === 'QUAN_TRI_TAP_DOAN' ? [] : role === 'QUAN_LY_CONG_TY' ? ['4'] : ['3', '4']) {
        const edit = await context.request.get(url('/facility/bank/edit', { id: foreignId }));
        check(edit.status() === 403, `${role}: foreign edit ${foreignId} rejected`);
        for (const action of ['update', 'set-default', 'toggle-status']) {
            const denied = await post(context, `/facility/bank/${action}`, { id: foreignId, facility_id: '1', _token: token, bank_code: 'VCB', so_tai_khoan: 'FORGEDACCOUNT', ten_chu_tai_khoan: 'FORGED ACCOUNT' });
            check(denied.status() === 403, `${role}: foreign ${action} ${foreignId} rejected`);
        }
    }
    if (role === 'QUAN_LY_CONG_TY') {
        const denied = await post(context, '/facility/bank/store', { facility_id: '3', _token: token, bank_code: 'VCB', so_tai_khoan: 'FORGEDACCOUNT', ten_chu_tai_khoan: 'FORGED ACCOUNT' });
        check(denied.status() === 403, 'company cannot create account in foreign company');
    }
    await page.goto(url('/facility/bank', { facility_id: '1' }));
    check((await page.locator('tbody').innerText()).includes('TESTNEWACCOUNT') && !(await page.locator('tbody').innerText()).includes('FORGEDACCOUNT'), `${role}: rejected requests leave list unchanged`);
}
(async () => {
    await ready();
    browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    for (const [role, name] of [['QUAN_TRI_TAP_DOAN', 'admin'], ['QUAN_LY_CONG_TY', 'company'], ['QUAN_LY_CO_SO', 'facility'], ['NHAN_VIEN', 'employee']]) {
        const context = await browser.newContext({ extraHTTPHeaders: { 'X-Stryke-Test-Role': role } });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        for (const width of before ? [1440] : [1440, 1280, 1024, 768, 375]) {
            await page.setViewportSize({ width, height: 1000 });
            const response = await page.goto(url('/facility/bank', { facility_id: '1' }));
            if (before && role === 'NHAN_VIEN') {
                check(response.status() === 403, 'before: employee denied list');
                continue;
            }
            check(response.status() === 200, `${name}/${width}: list loads`);
            if (!before) {
                await layout(page, `${name}/${width}`);
                check(await page.locator('.admin-menu__item.is-active[aria-label="Tài khoản ngân hàng"]').count() === 1, `${name}/${width}: bank menu active`);
                const editable = await page.locator('.bank-page a[href*="bank%2Fcreate"], .bank-page form[method="post"]').count();
                check(['NHAN_VIEN', 'QUAN_TRI_TAP_DOAN'].includes(role) ? editable === 0 : editable > 0, `${name}/${width}: controls match role`);
                if (role === 'NHAN_VIEN') {
                    check(!(await page.locator('.bank-page thead').innerText()).includes('Thao tác'), `${width}: employee has no actions column`);
                    check((await page.locator('.bank-page').innerText()).includes('Nhân viên chỉ có quyền xem'), `${width}: read-only note`);
                }
            }
            await page.screenshot({ path: path.join(out, `${before ? 'before-' : ''}${name}-${width}.png`), fullPage: true });
            if (!before && width >= 1024) {
                await page.locator('[data-sidebar-collapse]').click();
                await layout(page, `${name}/${width}/compact`);
                await page.screenshot({ path: path.join(out, `${name}-${width}-compact.png`), fullPage: true });
                await page.locator('[data-sidebar-collapse]').click();
            } else if (!before) {
                await page.locator('#admin-menu-toggle').click();
                check(await page.locator('#admin-menu-toggle').getAttribute('aria-expanded') === 'true', `${name}/${width}: mobile menu opens`);
                await page.locator('#admin-sidebar-close').click();
                check(await page.locator('#admin-menu-toggle').getAttribute('aria-expanded') === 'false', `${name}/${width}: mobile menu closes`);
            }
            if (['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO'].includes(role)) {
                for (const [route, suffix] of [['/facility/bank/create', 'create'], ['/facility/bank/edit', 'edit']]) {
                    const response = await page.goto(url(route, { facility_id: '1', ...(suffix === 'edit' ? { id: '2' } : {}) }));
                    check(response.status() === 200, `${name}/${width}: ${suffix} loads`);
                    if (!before) await layout(page, `${name}/${width}/${suffix}`);
                    await page.screenshot({ path: path.join(out, `${before ? 'before-' : ''}${name}-${suffix}-${width}.png`), fullPage: true });
                }
            }
        }
        if (!before && ['QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO'].includes(role)) await managerFlow(context, page, role);
        if (!before && ['NHAN_VIEN', 'QUAN_TRI_TAP_DOAN'].includes(role)) {
            await page.goto(url('/facility/bank'));
            const token = await page.locator('input[name="_token"]').first().inputValue();
            const initial = await page.locator('tbody').innerText();
            for (const action of ['create', 'edit']) {
                const denied = await context.request.get(url(`/facility/bank/${action}`, { id: '1', facility_id: '1' }));
                check(denied.status() === 403, `${role} cannot open ${action} URL`);
            }
            for (const action of ['store', 'update', 'set-default', 'toggle-status']) {
                const denied = await post(context, `/facility/bank/${action}`, { id: '1', facility_id: '1', _token: token, bank_code: 'VCB', so_tai_khoan: 'FORGEDACCOUNT', ten_chu_tai_khoan: 'FORGED ACCOUNT' });
                check(denied.status() === 403, `${role} cannot POST ${action}, even with valid CSRF`);
            }
            const foreign = await context.request.get(url('/facility/bank', { facility_id: '2' }));
            check(foreign.status() === (role === 'QUAN_TRI_TAP_DOAN' ? 200 : 403), role + ': cross-facility read matches role');
            await page.reload();
            check(await page.locator('tbody').innerText() === initial, 'employee requests do not change bank data');
        }
        check(errors.length === 0, `${name}: no JavaScript errors ${errors.join(', ')}`);
        await context.close();
    }
    if (!before) {
        for (const role of ['QUAN_LY_CO_SO', 'NHAN_VIEN']) {
            const context = await browser.newContext({ viewport: { width: 375, height: 1000 }, extraHTTPHeaders: { 'X-Stryke-Test-Role': role, 'X-Stryke-Test-Facility': '4' } });
            const page = await context.newPage();
            await page.goto(url('/facility/bank'));
            check((await page.locator('.workspace-empty').innerText()).includes('Chưa có tài khoản ngân hàng'), `${role}: empty state`);
            if (role === 'NHAN_VIEN') check(!(await page.locator('.bank-page').innerText()).includes('Thêm tài khoản'), 'employee empty state has no add instruction');
            await layout(page, `${role}/empty`);
            await page.screenshot({ path: path.join(out, `${role}-empty-375.png`), fullPage: true });
            await context.close();
        }
        for (const role of ['QUAN_TRI_TAP_DOAN', 'KHACH_HANG']) {
            const context = await browser.newContext({ extraHTTPHeaders: { 'X-Stryke-Test-Role': role } });
            const response = await context.request.get(url('/facility/bank', { facility_id: '3' }));
            check(response.status() === (role === 'KHACH_HANG' ? 403 : 200), `${role}: existing permission retained`);
            await context.close();
        }
    }
})().catch(error => {
    checks.push({ label: error.stack, pass: false });
    process.exitCode = 1;
}).finally(async () => {
    if (browser) await browser.close();
    server.kill();
    if (!before) checks.push({ label: 'PHP fixture has no warnings or fatal errors', pass: !/PHP (?:Warning|Fatal error|Parse error)/.test(serverLog) });
    fs.writeFileSync(path.join(out, `${before ? 'before-' : ''}browser-results.json`), JSON.stringify(checks, null, 2));
    fs.writeFileSync(path.join(out, `${before ? 'before-' : ''}server.log`), serverLog);
    console.log(`${checks.filter(x => x.pass).length} PASS / ${checks.filter(x => !x.pass).length} FAIL; ${out}`);
    for (const failure of checks.filter(x => !x.pass)) console.error(failure.label);
});
