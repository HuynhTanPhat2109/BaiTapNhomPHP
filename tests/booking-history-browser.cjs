// Chrome + built-in Node modules. Fixture data is never written to the application DB.
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const {spawn, execFileSync} = require('node:child_process');
const output = path.resolve('storage/temp/history-review');
fs.mkdirSync(output, {recursive: true});
const html = execFileSync('C:/xampp/php/php.exe', ['tests/booking-history.php', '--render'], {encoding:'utf8'});
const server = http.createServer((req,res) => {
    const resource = new URL(req.url, 'http://localhost').pathname;
    if (resource === '/history') {res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);return;}
    if (/^\/test-proof\/[12]\.png$/.test(resource)) {
        res.setHeader('Content-Type','image/svg+xml');
        res.end('<svg xmlns="http://www.w3.org/2000/svg" width="1000" height="1600"><rect width="1000" height="1600" fill="#eaf2e8"/><text x="80" y="150" font-size="60">TEST RECEIPT '+resource+'</text></svg>');return;
    }
    if (/^\/BaiTapNhomPHP\/public\/assets\/(css|js)\/[a-z-]+\.(css|js)$/.test(resource)) {
        const file=path.resolve('public/assets', resource.split('/assets/')[1]);
        res.setHeader('Content-Type',file.endsWith('.css')?'text/css':'application/javascript');res.end(fs.readFileSync(file));return;
    }
    res.statusCode=404;res.end();
});
const pause = ms => new Promise(r=>setTimeout(r,ms));
let chrome, socket, sequence=0, checks=0;
const pending=new Map(), errors=[];
async function until(fn) {for(let i=0;i<150;i++){if(await fn()) return;await pause(100);}throw new Error('Timed out');}
function send(method,params={},sessionId) {return new Promise((resolve,reject)=>{const id=++sequence;const timer=setTimeout(()=>reject(new Error('CDP timeout: '+method)),15000);pending.set(id,{resolve:r=>{clearTimeout(timer);resolve(r);},reject:e=>{clearTimeout(timer);reject(e);}});socket.send(JSON.stringify({id,method,params,sessionId}));});}
(async()=>{
    await new Promise(r=>server.listen(0,'127.0.0.1',r));
    const profile=path.join(output,'chrome-'+Date.now());
    chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile],{stdio:'ignore',windowsHide:true});
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')));
    const [port,endpoint]=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').trim().split('\n');
    socket=new WebSocket('ws://127.0.0.1:'+port+endpoint);
    await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject;});
    socket.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const job=pending.get(data.id);pending.delete(data.id);if(job)data.error?job.reject(new Error(data.error.message)):job.resolve(data.result);}else if(data.method==='Runtime.exceptionThrown')errors.push(data.params.exceptionDetails.text);};
    const {targetId}=await send('Target.createTarget',{url:'about:blank'});
    const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
    await send('Page.enable',{},sessionId);await send('Runtime.enable',{},sessionId);
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},sessionId);if(r.exceptionDetails)throw new Error(r.exceptionDetails.text);return r.result.value;};
    const go=async url=>{await send('Page.navigate',{url},sessionId);await until(()=>evaluate(`document.readyState==='complete' && location.href===${JSON.stringify(url)}`));};
    for(const width of [1440,1280,768,390,360]) {
        console.log('Checking viewport '+width);
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false},sessionId);
        await go('http://127.0.0.1:'+server.address().port+'/history');
        assert.equal(await evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'),true,'Page overflow at '+width);
        assert.equal(await evaluate(`document.querySelectorAll('.booking-history-card').length`),6);
        assert.equal(await evaluate(`Array.from(document.querySelectorAll('.booking-history-card, .booking-history-filters, .booking-history-card-aside')).every(e=>e.scrollWidth <= e.clientWidth+1)`),true,'Component overflow at '+width);
        assert.equal(await evaluate(`getComputedStyle(document.querySelector('.booking-history-card')).display`),'grid');
        await evaluate(`document.querySelector('.booking-history-filters a').focus()`);
        assert.equal(await evaluate(`document.activeElement.matches('.booking-history-filters a')`),true);
        await evaluate(`document.querySelectorAll('.booking-history-costs')[0].open=true;document.querySelectorAll('.booking-history-costs')[1].open=true;window.costsBefore=[...document.querySelectorAll('.booking-history-costs-row,.booking-history-costs-services,.booking-history-costs-total')].map(e=>e.outerHTML).join('');`);
        assert.equal(await evaluate(`document.querySelectorAll('.booking-history-note')[1].textContent.trim()`),'Không có');
        assert.equal(await evaluate(`document.querySelectorAll('[data-view-proof]').length`),2);
        await evaluate(`document.querySelector('[data-view-proof]').click()`);
        await until(()=>evaluate(`document.querySelector('[data-proof-image]').complete && document.querySelector('[data-proof-image]').naturalWidth>0`));
        assert.equal(await evaluate(`document.querySelector('[data-proof-modal]').open && document.querySelector('[data-proof-image]').src.endsWith('/test-proof/1.png')`),true);
        assert.equal(await evaluate(`document.querySelector('[data-proof-image]').getBoundingClientRect().width <= innerWidth && document.querySelector('[data-proof-image]').getBoundingClientRect().height <= innerHeight`),true);
        await evaluate(`document.querySelector('[data-close-proof]').click()`);
        await until(()=>evaluate(`!document.querySelector('[data-proof-modal]').open && document.activeElement===document.querySelector('[data-view-proof]')`));
        await evaluate(`document.querySelectorAll('[data-view-proof]')[1].click()`);
        assert.equal(await evaluate(`document.querySelector('[data-proof-image]').src.endsWith('/test-proof/2.png')`),true);
        await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27},sessionId);
        await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27},sessionId);
        await until(()=>evaluate(`!document.querySelector('[data-proof-modal]').open`));
        await evaluate(`document.querySelector('[data-view-proof]').click();document.querySelector('[data-proof-modal]').click()`);
        await until(()=>evaluate(`!document.querySelector('[data-proof-modal]').open`));
        assert.equal(await evaluate(`window.costsBefore===[...document.querySelectorAll('.booking-history-costs-row,.booking-history-costs-services,.booking-history-costs-total')].map(e=>e.outerHTML).join('')`),true);
        assert.equal(await evaluate(`document.documentElement.scrollWidth<=innerWidth`),true);
        checks+=9;
        const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true},sessionId);
        fs.writeFileSync(path.join(output,width+'.png'),Buffer.from(shot.data,'base64'));checks+=5;
    }
    // Public real application checks: GET only, no test endpoint/session injection.
    const base='http://localhost/BaiTapNhomPHP/public/index.php';
    const guest=await fetch(base+'?route=%2Fbooking%2Fhistory',{redirect:'manual'});
    assert.equal(guest.status,303);assert.match(guest.headers.get('location'),/route=%2Flogin/);checks+=2;
    for(const route of ['/', '/courts']) {
        await go(base+'?route='+encodeURIComponent(route));
        assert.equal(await evaluate(`document.documentElement.scrollWidth <= innerWidth`),true);
        if(route==='/')assert.equal(await evaluate(`!!document.querySelector('a[href*="booking%2Fhistory"]')`),true);
        checks++;
    }
    assert.deepEqual(errors,[]);checks++;
    console.log(`PASS: ${checks} Chrome checks; 5 widths, rendered history fixture, live guest redirect/home/directory. Screenshots: storage/temp/history-review`);
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{socket?.close();chrome?.kill();server.close();});
