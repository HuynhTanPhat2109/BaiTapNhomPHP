// Real Chrome, PHP routes, scoped SQL and existing admin assets. SQLite RAM fixture only.
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const output = fs.mkdtempSync(path.join(root, 'storage/temp/customer-browser-'));
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    const listener = net.createServer();
    await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
    const port = listener.address().port;
    await new Promise(resolve => listener.close(resolve));
    const base = `http://127.0.0.1:${port}/index.php`;
    const address = (route = '/admin/customers', query = {}) => base + '?' + new URLSearchParams({ route, ...query });
    const server = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe',
        ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/fixtures/customer-management.php'],
        { cwd: root, windowsHide: true, env: { ...process.env, STRYKE_CUSTOMER_TEST: '1' }, stdio: ['ignore', 'pipe', 'pipe'] });
    let log = '';
    server.stdout.on('data', chunk => log += chunk);
    server.stderr.on('data', chunk => log += chunk);
    let chrome, socket;
    const checks = [];
    const check = (ok, label) => { assert.ok(ok, label); checks.push(label); };
    try {
        for (let attempt = 0; ; attempt++) {
            try { if ((await fetch(address())).ok) break; } catch {}
            if (attempt > 60) throw Error('PHP test server did not start');
            await delay(100);
        }
        const profile = path.join(output, 'profile');
        chrome = spawn(process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
            ['--headless', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile],
            { windowsHide: true, stdio: 'ignore' });
        for (let attempt = 0; !fs.existsSync(path.join(profile, 'DevToolsActivePort')); attempt++) {
            if (attempt > 100) throw Error('Chrome did not start');
            await delay(100);
        }
        const [debugPort, endpoint] = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').trim().split('\n');
        socket = new WebSocket('ws://127.0.0.1:' + debugPort + endpoint);
        await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
        let sequence = 0;
        const pending = new Map();
        socket.onmessage = event => {
            const message = JSON.parse(event.data);
            if (!message.id) return;
            const job = pending.get(message.id);
            pending.delete(message.id);
            message.error ? job.reject(Error(message.error.message)) : job.resolve(message.result);
        };
        const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
            const id = ++sequence;
            pending.set(id, { resolve, reject });
            socket.send(JSON.stringify({ id, method, params, sessionId }));
        });
        const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        const evaluate = async expression => {
            const response = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }, sessionId);
            if (response.exceptionDetails) throw Error(JSON.stringify(response.exceptionDetails));
            return response.result.value;
        };
        await send('Page.enable', {}, sessionId);
        await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] }, sessionId);
        await send('Page.addScriptToEvaluateOnNewDocument', { source: "window.testErrors=[];addEventListener('error',e=>testErrors.push(e.message));addEventListener('securitypolicyviolation',e=>testErrors.push('CSP '+e.violatedDirective));" }, sessionId);
        const navigate = async (url, selector = 'h1') => {
            await send('Page.navigate', { url }, sessionId);
            for (let attempt = 0; ; attempt++) {
                if (await evaluate(`location.href === ${JSON.stringify(url)} && document.readyState==='complete' && !!document.querySelector(${JSON.stringify(selector)})`)) break;
                if (attempt > 100) throw Error('Navigation did not finish: ' + url);
                await delay(50);
            }
            check((await evaluate('testErrors')).length === 0, 'no JS/CSP error: ' + url);
        };
        const screenshot = async name => {
            await delay(300);
            const { data } = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, sessionId);
            fs.writeFileSync(path.join(output, name + '.png'), Buffer.from(data, 'base64'));
        };
        for (const width of [1440, 820, 390]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 950, deviceScaleFactor: 1, mobile: width < 600 }, sessionId);
            await navigate(address());
            check(await evaluate("document.querySelectorAll('.workspace-table tbody tr').length===10"), 'ten customers per page at ' + width);
            check(await evaluate("document.querySelectorAll('.admin-menu__item.is-active[aria-label=\"Quản lý khách hàng\"]').length===1"), 'sidebar active at ' + width);
            check(await evaluate('document.documentElement.scrollWidth <= innerWidth+1'), 'no page overflow at ' + width);
            await screenshot('list-' + width);
            await navigate(address('/admin/customers/show', { id: '1' }));
            check(await evaluate("document.querySelectorAll('.workspace-table tbody tr').length===3 && document.querySelectorAll('[data-label=\"Khung giờ\"] div').length===9 && !document.querySelector('.detail-page').textContent.includes('Facility B1')"), 'three scoped bookings with nine exact time lines at ' + width);
            check(await evaluate('document.documentElement.scrollWidth <= innerWidth+1'), 'detail no page overflow at ' + width);
            await screenshot('detail-' + width);
        }
        await navigate(address());
        await evaluate("document.querySelector('[name=q]').value='Shared Customer';document.querySelector('.list-toolbar').requestSubmit()");
        for (let attempt = 0; ; attempt++) {
            if (await evaluate("document.readyState==='complete' && new URL(location.href).searchParams.get('q')==='Shared Customer'")) break;
            if (attempt > 100) throw Error('Search did not finish');
            await delay(50);
        }
        check(await evaluate("document.querySelectorAll('.workspace-table tbody tr').length===1"), 'search form submits and filters results');
        for (const [actor, id, status] of [['company-b', '2', 404], ['staff', '5', 404], ['facility', '5', 404], ['customer', '1', 403], ['admin', '1', 403]]) {
            check((await fetch(address('/admin/customers/show', { actor, id }))).status === status, actor + ' direct URL rejected');
        }
        check(!/PHP (Warning|Fatal error|Parse error)/.test(log), 'no PHP runtime errors');
        fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify({ checks, screenshots: [1440, 820, 390], database: 'SQLite memory only' }, null, 2));
        console.log(`PASS: ${checks.length} Chrome/HTTP checks. Screenshots: ${output}`);
    } finally {
        fs.writeFileSync(path.join(output, 'server.log'), log);
        if (socket) socket.close();
        if (chrome) chrome.kill();
        server.kill();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
