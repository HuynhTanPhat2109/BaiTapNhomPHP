// Invoked only by employee-account-integration.php; the normal app runs against its disposable DB.
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
if (!/^stryke_employee_test_[a-f0-9]{10}$/.test(process.env.STRYKE_EMPLOYEE_TEST_DB || '')) throw Error('Use integration runner');
const directory = path.resolve(process.argv[2]);
const fixture = JSON.parse(fs.readFileSync(path.join(directory, 'browser-fixture.json'), 'utf8'));
const url = (route, query = {}) => fixture.url + '/index.php?' + new URLSearchParams({ route, ...query });
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
    const profile = path.join(directory, 'chrome-profile');
    const chrome = spawn(process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
        ['--headless', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile],
        { windowsHide: true, stdio: 'ignore' });
    let socket, sequence = 0;
    const pending = new Map(), checks = [];
    const check = (ok, label) => { assert.ok(ok, label); checks.push(label); };
    try {
        for (let attempt = 0; !fs.existsSync(path.join(profile, 'DevToolsActivePort')); attempt++) {
            if (attempt > 100) throw Error('Chrome startup timeout');
            await delay(100);
        }
        const [port, endpoint] = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').trim().split('\n');
        socket = new WebSocket('ws://127.0.0.1:' + port + endpoint);
        await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
        socket.onmessage = event => {
            const message = JSON.parse(event.data);
            if (!message.id) return;
            const job = pending.get(message.id); pending.delete(message.id); clearTimeout(job.timer);
            message.error ? job.reject(Error(message.error.message)) : job.resolve(message.result);
        };
        const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
            const id = ++sequence;
            pending.set(id, { resolve, reject, timer: setTimeout(() => reject(Error('CDP timeout: ' + method)), 10000) });
            socket.send(JSON.stringify({ id, method, params, sessionId }));
        });
        const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        const evaluate = async expression => {
            const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }, sessionId);
            if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
            return result.result.value;
        };
        const wait = async expression => {
            for (let attempt = 0; ; attempt++) {
                if (await evaluate(expression)) return;
                if (attempt > 100) throw Error('Page condition timed out');
                await delay(50);
            }
        };
        await send('Page.enable', {}, sessionId);
        await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] }, sessionId);
        await send('Page.addScriptToEvaluateOnNewDocument', { source: "window.testErrors=[];addEventListener('error',e=>testErrors.push(e.message));addEventListener('securitypolicyviolation',e=>testErrors.push(e.violatedDirective));" }, sessionId);
        const navigate = async address => {
            await send('Page.navigate', { url: address }, sessionId);
            await wait(`location.href===${JSON.stringify(address)} && document.readyState==='complete'`);
            check((await evaluate('testErrors')).length === 0, 'no JS/CSP errors');
        };
        const login = async username => {
            await navigate(url('/login'));
            await evaluate(`document.querySelector('[name=username]').value=${JSON.stringify(username)};document.querySelector('[name=password]').value=${JSON.stringify(process.env.STRYKE_EMPLOYEE_TEST_PASSWORD)};document.querySelector('form').requestSubmit()`);
            await wait("document.readyState==='complete' && new URL(location.href).searchParams.get('route')!=='/login'");
            check(true, 'actual browser login: ' + username);
        };
        const screenshot = async name => {
            await delay(250);
            const { data } = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, sessionId);
            fs.writeFileSync(path.join(directory, name + '.png'), Buffer.from(data, 'base64'));
        };
        await login('grant.company');
        for (const width of [1440, 390]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 950, deviceScaleFactor: 1, mobile: width < 600 }, sessionId);
            await navigate(url('/company/employees', { q: 'BROWSER TARGET' }));
            check(await evaluate("!![...document.querySelectorAll('.row-actions a')].find(a=>a.textContent.trim()==='Xem chi tiết')"), 'detail action in employee row');
            await screenshot('employee-list-' + width);
            await evaluate("[...document.querySelectorAll('.row-actions a')].find(a=>a.textContent.trim()==='Xem chi tiết').click()");
            await wait("document.readyState==='complete' && new URL(location.href).searchParams.get('route')==='/company/employees/show'");
            check(await evaluate("document.querySelector('.detail-page').textContent.includes('Chưa cấp') && !document.querySelector('.detail-page form')"), 'unprovisioned detail remains read-only');
            await screenshot('employee-detail-' + width);
            await navigate(url('/company/employees/edit', { id: String(fixture.target) }));
            check(await evaluate("!document.querySelector('details:has(#provision-username)').open"), 'provision form initially collapsed');
            await evaluate("document.querySelector('details:has(#provision-username) summary').click()");
            check(await evaluate("document.querySelector('details:has(#provision-username)').open && !document.querySelector('form form')"), 'account form opens without nested forms');
            check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'), 'no page overflow at ' + width);
            await screenshot('employee-provision-' + width);
        }
        // Unsaved personnel edits must not be submitted by the separate account form.
        await evaluate("document.querySelector('[name=ho_ten]').value='UNSAVED PROFILE CHANGE'");
        const fill = async confirmation => evaluate(`document.querySelector('#provision-username').value='grant.browser';document.querySelector('#provision-role').value='NHAN_VIEN';document.querySelector('#provision-password').value=${JSON.stringify(process.env.STRYKE_EMPLOYEE_TEST_PASSWORD)};document.querySelector('#provision-confirmation').value=${JSON.stringify(confirmation)};document.querySelector('#provision-username').form.requestSubmit()`);
        await fill('Wrongpass1!');
        await wait("document.readyState==='complete' && !!document.querySelector('#password_confirmation-error')");
        check(await evaluate("document.querySelector('#provision-password').value==='' && document.querySelector('details:has(#provision-username)').open"), 'invalid confirmation keeps form open and clears secret fields');
        await fill(process.env.STRYKE_EMPLOYEE_TEST_PASSWORD);
        await wait("document.readyState==='complete' && new URL(location.href).searchParams.get('route')==='/company/employees/edit' && !document.querySelector('#provision-username')");
        check(await evaluate("document.body.textContent.includes('Đã cấp tài khoản') && document.querySelector('[name=ho_ten]').value==='BROWSER TARGET'"), 'grant succeeds without saving personnel changes');
        await navigate(url('/company/employees/show', { id: String(fixture.target) }));
        check(await evaluate("document.querySelector('.detail-page').textContent.includes('grant.browser') && document.querySelector('.detail-page').textContent.includes('Đã cấp')"), 'detail reflects new account');
        check((await evaluate('testErrors')).length === 0, 'no JavaScript/CSP errors after grant');
        await screenshot('employee-provisioned-mobile');
        fs.writeFileSync(path.join(directory, 'browser-report.json'), JSON.stringify({ checks }, null, 2));
        console.log(`PASS: ${checks.length} actual Chrome desktop/mobile form checks.`);
    } finally {
        for (const job of pending.values()) clearTimeout(job.timer);
        if (socket) socket.close();
        chrome.kill();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
