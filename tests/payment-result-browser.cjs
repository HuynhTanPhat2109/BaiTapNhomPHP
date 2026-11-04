// Run after booking-integration.php --run. Uses its isolated-DB HTTP-rendered fixture.
const fs=require('node:fs');
const path=require('node:path');
const {spawn}=require('node:child_process');
const assert=require('node:assert/strict');
const chrome=process.env.CHROME_BINARY||'C:/Program Files/Google/Chrome/Application/chrome.exe';
const root=path.resolve('.');
const dir=fs.mkdtempSync(path.join(root,'storage/temp/payment-result-'));
let html=fs.readFileSync('storage/temp/receipt-result-test.html','utf8');
html=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/g,'');
html=html.replace(/<link[^>]+href="[^\"]*\/assets\/([^\"?]+)(?:\?[^\"]*)?"[^>]*>/g,(_,asset)=>'<style>'+fs.readFileSync(path.join(root,'public/assets',asset),'utf8')+'</style>');
// Suppress irrelevant hidden receipt thumbnails from the disposable database.
html=html.replace(/<img[^>]+>/g,'');
const setup=()=>{
    window.testTimers=new Map();window.testTimerId=0;window.testCalls=[];window.testErrors=[];
    window.addEventListener('error',e=>testErrors.push(e.message));
    window.setInterval=(fn,ms)=>{testTimers.set(++testTimerId,{fn,ms});return testTimerId;};
    window.clearInterval=id=>testTimers.delete(id);
    window.testState={state:'CHO_XAC_NHAN',payment_status:'CHO_THANH_TOAN',receipt_submitted:true};
    window.fetch=async(url,options)=>{testCalls.push({url,options});return {ok:true,json:async()=>testState};};
};
const test=async()=>{
    const checks=[];const check=(ok,label)=>{if(!ok)throw Error(label);checks.push(label);};
    const result=document.querySelector('[data-payment-result]');
    const title=document.querySelector('[data-result-title]');
    const poll=[...testTimers.values()].find(t=>t.ms===5000).fn;
    const pending=()=>[...document.querySelectorAll('[data-payment-pending]')].some(n=>!n.hidden);
    try {
        check(!result.hidden&&title.textContent==='Đã gửi biên lai thành công!','committed receipt opens result directly');
        check(!pending()&&!document.querySelector('[data-receipt-waiting]'),'no intermediate receipt screen or pending QR');
        check(document.querySelector('[data-result-description]').textContent.includes('chờ nhân viên xác nhận'),'receipt is not called paid');
        check(result.querySelectorAll('a').length===2&&result.querySelectorAll('a')[0].href.includes('route=%2F')&&result.querySelectorAll('a')[1].href.includes('booking%2Fhistory'),'two correct destination links');
        check(document.querySelector('.court-flow-title').textContent==='THANH TOÁN','existing header preserved');
        check(document.documentElement.scrollWidth<=innerWidth,'no horizontal overflow');
        const actions=[...result.querySelectorAll('a')];
        check(actions.every(a=>a.getBoundingClientRect().width<=innerWidth&&a.getBoundingClientRect().height>=44),'responsive accessible action targets');
        actions[0].focus();check(document.activeElement===actions[0],'keyboard focus works');
        await poll();check(!testCalls.some(c=>c.url.includes('expire')),'old receipt review never sends expiry');
        testState={state:'CHO_XAC_NHAN',payment_status:'CAN_DOI_SOAT',receipt_submitted:true};await poll();
        check(result.hidden&&!document.querySelector('[data-payment-reconcile]').hidden,'reconciliation does not show success');
        testState={state:'CHO_THANH_TOAN',payment_status:'THANH_CONG',receipt_submitted:false};await poll();
        check(result.hidden&&!pending(),'recorded payment alone neither claims success nor offers QR');
        testState={state:'CHO_XAC_NHAN',payment_status:'CHO_THANH_TOAN',receipt_submitted:true};await poll();
        check(!result.hidden,'review returns to honest receipt result');
        testState={state:'DA_XAC_NHAN',payment_status:'THANH_CONG',receipt_submitted:true};await poll();
        check(!result.hidden&&title.textContent==='Thanh toán đã thành công!'&&!pending(),'verified booking and payment show success');
        check(testTimers.size===0,'verified result stops polling');
        check(testErrors.length===0,'no JavaScript errors');
        // Screenshot receipt state for visual comparison, without altering production code.
        title.textContent='Đã gửi biên lai thành công!';
        document.querySelector('[data-result-description]').textContent='Chúng tôi đã tiếp nhận biên lai của bạn. Đơn đặt sân đang chờ nhân viên xác nhận.';
        const report=document.createElement('pre');report.id='test-result';report.hidden=true;report.textContent=JSON.stringify({checks});document.body.append(report);
    }catch(e){const report=document.createElement('pre');report.id='test-result';report.textContent=JSON.stringify({error:e.message,checks});document.body.append(report);}
};
html=html.replace('</body>','<script>('+setup.toString()+')()</script><script>'+fs.readFileSync('public/assets/js/booking.js','utf8')+'</script><script>('+test.toString()+')()</script></body>');
const file=path.join(dir,'result.html');fs.writeFileSync(file,html);
const delay=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
    const profile=path.join(dir,'cdp-profile');
    const proc=spawn(chrome,['--headless','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile],{stdio:'ignore',windowsHide:true});
    let socket;let sequence=0;const jobs=new Map();
    try {
        for(let i=0;!fs.existsSync(path.join(profile,'DevToolsActivePort'));i++){if(i>100)throw Error('Chrome startup timeout');await delay(100);}
        const [port,endpoint]=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').trim().split('\n');
        socket=new WebSocket('ws://127.0.0.1:'+port+endpoint);
        await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j;});
        socket.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const job=jobs.get(m.id);jobs.delete(m.id);m.error?job.reject(Error(m.error.message)):job.resolve(m.result);}};
        const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const id=++sequence;jobs.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params,sessionId}));});
        for(const width of [1440,768,390,320]) {
            const {targetId}=await send('Target.createTarget',{url:'about:blank'});
            const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<600},sessionId);
            await send('Page.navigate',{url:'file:///'+file.replaceAll('\\','/')},sessionId);
            let value;
            for(let i=0;i<100;i++){
                const response=await send('Runtime.evaluate',{expression:'document.querySelector("#test-result")?.textContent',returnByValue:true},sessionId);
                value=response.result.value;if(value)break;await delay(100);
            }
            assert(value,'Browser test did not finish');const result=JSON.parse(value);assert(!result.error,JSON.stringify(result));
            const metrics=await send('Runtime.evaluate',{expression:'innerWidth',returnByValue:true},sessionId);assert.equal(metrics.result.value,width,'actual viewport width');
            await send('Runtime.evaluate',{expression:'document.activeElement.blur()'},sessionId);
            const shot=path.join(dir,'receipt-'+width+'.png');
            fs.writeFileSync(shot,Buffer.from((await send('Page.captureScreenshot',{format:'png'},sessionId)).data,'base64'));
            console.log('PASS '+width+'px: '+result.checks.length+' UI checks; screenshot '+shot);
            await send('Target.closeTarget',{targetId});
        }
    }finally{socket?.close();proc.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
