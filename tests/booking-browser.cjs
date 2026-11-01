// Called by booking-integration.php --run --browser against its disposable database/server.
// Uses Chrome DevTools and Node built-ins only; never connects to the application database.
const fs = require('node:fs');
const path = require('node:path');
const {spawn, execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');
const config = JSON.parse(process.argv[2]);
assert.equal(config.port, 18989);
assert.match(config.db, /^stryke_booking_test_[a-f0-9]{10}$/);
const base = `http://127.0.0.1:${config.port}/index.php`;
const route = (name, params={}) => base+'?'+new URLSearchParams({route:name,...params});
const delay = ms => new Promise(resolve=>setTimeout(resolve,ms));
const profile = path.resolve('storage/temp/booking-chrome-'+Date.now());
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',[
    '--headless','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile
],{stdio:'ignore',windowsHide:true});
let socket, sequence=0, checks=0;
const pending=new Map(), errors=[];
function pass(label) { checks++; console.log('PASS browser: '+label); }
async function until(fn,label,timeout=15000) {
    const start=Date.now();
    while(Date.now()-start<timeout) { if(await fn())return; await delay(100); }
    throw new Error('Timeout: '+label);
}
function send(method,params={},sessionId) {
    return new Promise((resolve,reject)=>{
        const id=++sequence;
        const timer=setTimeout(()=>{pending.delete(id);reject(new Error('CDP timeout '+method));},15000);
        pending.set(id,{resolve:value=>{clearTimeout(timer);resolve(value);},reject:error=>{clearTimeout(timer);reject(error);}});
        socket.send(JSON.stringify({id,method,params,sessionId}));
    });
}
async function tab() {
    const {browserContextId}=await send('Target.createBrowserContext');
    const {targetId}=await send('Target.createTarget',{url:'about:blank',browserContextId});
    const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
    await send('Page.enable',{},sessionId);await send('Runtime.enable',{},sessionId);
    // Model two foreground browser windows, rather than a background tab where polling pauses.
    await send('Emulation.setFocusEmulationEnabled',{enabled:true},sessionId);
    const evaluate=async expression=>{
        const result=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true},sessionId);
        if(result.exceptionDetails)throw new Error(result.exceptionDetails.exception?.description||'JS evaluation failed');
        return result.result.value;
    };
    return {sessionId,evaluate,
        async go(url,selector){await send('Page.navigate',{url},sessionId);await until(()=>evaluate(`location.href===${JSON.stringify(url)} && document.readyState==='complete' && !!document.querySelector(${JSON.stringify(selector)})`),'navigate '+url);},
        async click(selector){await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);},
        async wait(selector){await until(()=>evaluate(`!!document.querySelector(${JSON.stringify(selector)})`),selector);},
        async back(){const h=await send('Page.getNavigationHistory',{},sessionId);await send('Page.navigateToHistoryEntry',{entryId:h.entries[h.currentIndex-1].id},sessionId);}
    };
}
(async()=>{
    try {
        await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome startup');
        const [port,endpoint]=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').trim().split('\n');
        socket=new WebSocket('ws://127.0.0.1:'+port+endpoint);
        await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject;});
        socket.onmessage=event=>{
            const data=JSON.parse(event.data);
            if(data.id){const job=pending.get(data.id);pending.delete(data.id);if(job)data.error?job.reject(new Error(data.error.message)):job.resolve(data.result);}
            else if(data.method==='Runtime.exceptionThrown') errors.push(data.params.exceptionDetails.exception?.description||data.params.exceptionDetails.text);
        };
        const a=await tab(), b=await tab();
        await a.go(route('/login'),'[name="username"]');
        await a.evaluate(`(async()=>{const data=new FormData(document.querySelector('form'));data.set('username','booktest1');data.set('password','Testpass1!');await fetch(${JSON.stringify(route('/login'))},{method:'POST',body:data})})()`);
        const schedule=route('/courts/schedule',{id:config.facility,date:config.date});
        const cell=time=>`.time-cell[data-court="${config.court}"][data-start="${time}"]`;
        await a.go(schedule,'#court-schedule');await b.go(schedule,'#court-schedule');
        await b.click(cell('21:00'));await b.click(cell('22:00'));
        await until(()=>b.evaluate(`document.querySelectorAll('.time-cell[aria-pressed="true"]').length===2`),'B selects two slots');
        const bNavigation=await b.evaluate('performance.timeOrigin');
        async function makeHold(time) {
            await a.go(schedule,'#court-schedule');await a.click(cell(time));
            await until(()=>a.evaluate(`!document.querySelector('#booking-next button').disabled`),'quote ready');
            await a.click('#booking-next button');await a.wait('[data-booking-form]');
            assert(await a.evaluate(`document.querySelector('[data-booking-back]').href.includes('date='+${JSON.stringify(config.date)})`));
            await a.click('[data-confirm-booking]');await a.wait('[data-booking-wait]');
            return await a.evaluate('location.href');
        }
        const firstWait=await makeHold('21:00');
        await until(()=>b.evaluate(`document.querySelector(${JSON.stringify(cell('21:00'))}).classList.contains('is-held')`),'B receives A hold');
        assert(await b.evaluate(`document.querySelectorAll('.time-cell[aria-pressed="true"]').length===1 && document.querySelector(${JSON.stringify(cell('22:00'))}).getAttribute('aria-pressed')==='true'`));
        pass('two isolated sessions: A hold updates B; only conflicting selection removed');
        const deadline=await a.evaluate(`document.querySelector('[data-booking-wait]').dataset.expires`);
        await a.go(firstWait,'[data-booking-wait]');
        assert.equal(await a.evaluate(`document.querySelector('[data-booking-wait]').dataset.expires`),deadline);
        assert.equal(await a.evaluate(`document.querySelector('[data-booking-wait]').dataset.state`),'CHO_THANH_TOAN');
        pass('refresh keeps hold and original six-minute deadline');
        // User activation before browser Back (browsers may skip unactivated history entries).
        await a.evaluate('document.body.click()');await a.back();await a.wait('#court-schedule');
        assert(await a.evaluate(`location.href.includes('date='+${JSON.stringify(config.date)}) && location.href.includes('id='+${JSON.stringify(String(config.facility))})`));
        await until(()=>b.evaluate(`!document.querySelector(${JSON.stringify(cell('21:00'))}).disabled`),'B receives A cancellation');
        assert.equal(await b.evaluate('performance.timeOrigin'),bNavigation);
        pass('browser Back cancels at server, goes directly to same schedule; B updates without reload');
        const forwardHistory=await send('Page.getNavigationHistory',{},a.sessionId);
        const forwardEntry=forwardHistory.entries[forwardHistory.currentIndex+1];
        assert(forwardEntry,'guard entry is available for Forward test');
        await send('Page.navigateToHistoryEntry',{entryId:forwardEntry.id},a.sessionId);
        await until(()=>a.evaluate(`document.querySelector('[data-booking-wait]')?.dataset.state==='DA_HUY'`),'Forward sees cancelled state');
        pass('browser Forward reloads cancelled state without restoring hold');
        await a.go(firstWait,'[data-booking-wait]');assert.equal(await a.evaluate(`document.querySelector('[data-booking-wait]').dataset.state`),'DA_HUY');
        pass('revisiting cancelled URL does not recreate hold');
        // Header path, then one failure/retry via genuine network offline mode.
        const secondWait=await makeHold('21:30');
        await send('Network.enable',{},a.sessionId);
        await send('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:0,uploadThroughput:0},a.sessionId);
        await a.click('[data-booking-cancel] button');
        await until(()=>a.evaluate(`!document.querySelector('[data-cancel-error]').hidden`),'cancellation error');
        assert.equal(await a.evaluate('location.href'),secondWait);pass('failed cancellation stays on waiting page and offers retry');
        await send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1},a.sessionId);
        await a.click('[data-retry-cancel]');await a.wait('#court-schedule');
        pass('retry cancellation succeeds via POST and navigates to schedule');
        const expiredWait=await makeHold('20:30');
        await until(()=>b.evaluate(`document.querySelector(${JSON.stringify(cell('20:30'))}).classList.contains('is-held')`),'B receives expiring hold');
        const id=new URL(expiredWait).searchParams.get('id');
        execFileSync('C:/xampp/php/php.exe',['tests/booking-integration.php','--expire-worker',config.db,id],{windowsHide:true});
        await until(()=>b.evaluate(`!document.querySelector(${JSON.stringify(cell('20:30'))}).disabled`),'expired hold released');
        await until(()=>a.evaluate(`document.querySelector('[data-wait-message]').textContent.includes('hết')`),'waiting expiry status');
        pass('server-expired fixture releases slot in B and updates waiting state without reload');
        await a.go(schedule,'#court-schedule');await a.click(cell('20:00'));
        await until(()=>a.evaluate(`!document.querySelector('#booking-next button').disabled`),'detail preview quote');
        await a.click('#booking-next button');await a.wait('[data-booking-form]');
        for (const width of [390,768,1440]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width===390},a.sessionId);
            assert(await a.evaluate('document.documentElement.scrollWidth<=innerWidth'), 'booking overflow at '+width);
        }
        pass('existing details layout has no horizontal overflow at 390/768/1440px');
        await a.back();await a.wait('#court-schedule');pass('details browser Back returns to schedule without creating a hold');
        await a.click('.court-flow-back');await a.wait('#court-filters');pass('schedule header goes to directory');
        assert.deepEqual(errors,[]);pass('no uncaught JavaScript exceptions');
        console.log('PASS: '+checks+' real Chrome booking/realtime checks.');
    } catch (error) { console.error('Browser JS errors:',errors); throw error; }
    finally {if(socket){try{await send('Browser.close');}catch{}socket.close();}chrome.kill();}
})().catch(error=>{console.error(error);process.exitCode=1;});
