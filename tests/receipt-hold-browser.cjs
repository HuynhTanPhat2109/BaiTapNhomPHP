// Optional Chrome verification used by receipt-hold-http.php --run --browser.
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),{spawn}=require('child_process');const root=process.cwd(),dir=fs.mkdtempSync(path.join(root,'storage/temp/receipt-hold-')),delay=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{const profile=path.join(dir,'profile'),proc=spawn(process.env.CHROME_BINARY||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile],{stdio:'ignore',windowsHide:true});let socket,seq=0;const jobs=new Map();try{for(let i=0;!fs.existsSync(path.join(profile,'DevToolsActivePort'));i++){if(i>100)throw Error('startup');await delay(100)}const [port,endpoint]=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').trim().split('\n');socket=new WebSocket('ws://127.0.0.1:'+port+endpoint);await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j});socket.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const j=jobs.get(m.id);jobs.delete(m.id);m.error?j.reject(Error(m.error.message)):j.resolve(m.result)}};const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const id=++seq;jobs.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params,sessionId}))});const {targetId}=await send('Target.createTarget',{url:'about:blank'}),{sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});const ev=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true},sessionId);if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value};
await send('Page.enable',{},sessionId);await send('Network.enable',{},sessionId);await send('Network.setBlockedURLs',{urls:['*route=%2Fpayment%2Fqr*']},sessionId);const base='http://127.0.0.1:18993/index.php?route=',url=r=>base+encodeURIComponent(r);const id=process.env.RESULT_TEST_ID;
await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.testErrors=[];window.addEventListener('error',e=>testErrors.push(e.message));"},sessionId);
await send('Page.navigate',{url:url('/login')},sessionId);await delay(500);await ev("document.querySelector('[name=username]').value='resulttest';document.querySelector('[name=password]').value='Testpass1!';document.querySelector('form').requestSubmit()");await delay(700);await send('Page.navigate',{url:url('/booking/wait')+'&id='+id},sessionId);await delay(600);
await ev("document.querySelector('[data-submit-payment]').click()");await delay(200);assert.equal(await ev("new URL(location.href).searchParams.get('route')"),'/booking/wait');assert.equal(await ev("document.querySelector('#stryke-proof-input').checkValidity()"),false);console.log('PENDING ERRORS',await ev('JSON.stringify(testErrors)'));console.log('PASS browser no image: submit blocked');
if(process.env.RESULT_TEST_AGED_ID){
    await send('Page.navigate',{url:url('/booking/result')+'&id='+process.env.RESULT_TEST_AGED_ID},sessionId);await delay(5700);
    assert.equal(await ev("document.querySelector('[data-booking-wait]').dataset.state"),'CHO_XAC_NHAN');
    assert.equal(await ev("!!document.querySelector('[data-payment-result]')"),true);
    assert.equal(await ev("!!document.querySelector('[data-countdown],[data-payment-expired]')"),false);
    assert.equal(await ev('testErrors.length'),0);
    console.log('PASS actual browser: one-day receipt review survives status polling without countdown/expiry');
    await send('Page.navigate',{url:url('/booking/wait')+'&id='+id},sessionId);await delay(550);
}
const originalHold=await ev("fetch("+JSON.stringify(url('/booking/wait')+'&id='+id+'&status=1')+").then(r=>r.json())");
await ev("document.querySelector('[data-booking-cancel] button').click()");
for(let i=0;i<30;i++){if(await ev("new URL(location.href).searchParams.get('route')")==='/courts/schedule')break;await delay(100);}
assert.equal(await ev("new URL(location.href).searchParams.get('route')"),'/courts/schedule');
await send('Page.navigate',{url:url('/booking/wait')+'&id='+id},sessionId);await delay(650);
await ev('history.back()');await delay(900);
assert.equal(await ev("new URL(location.href).searchParams.get('route')"),'/courts/schedule');
await send('Page.navigate',{url:url('/booking/wait')+'&id='+id},sessionId);await delay(650);
const resumedHold=await ev("fetch("+JSON.stringify(url('/booking/wait')+'&id='+id+'&status=1')+").then(r=>r.json())");
assert.equal(resumedHold.state,'CHO_THANH_TOAN');assert.equal(resumedHold.expires,originalHold.expires);
console.log('PASS actual Back/header navigation preserves booking and original deadline');
await send('DOM.enable',{},sessionId);const {root:doc}=await send('DOM.getDocument',{},sessionId);const {nodeId}=await send('DOM.querySelector',{nodeId:doc.nodeId,selector:'#stryke-proof-input'},sessionId);await send('DOM.setFileInputFiles',{nodeId,files:[path.join(root,'storage/temp/result-proof.png')]},sessionId);await delay(250);await ev("document.querySelector('[data-submit-payment]').click()");await delay(900);assert.equal(await ev("new URL(location.href).searchParams.get('route')"),'/booking/result');
for(const width of [1440,390]){await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600},sessionId);await send('Page.reload',{},sessionId);await delay(650);assert.equal(await ev("!!document.querySelector('[data-payment-result]')"),true);assert.equal(await ev("document.querySelector('[data-result-title]').textContent.trim()"),'G\u1eedi minh ch\u1ee9ng th\u00e0nh c\u00f4ng!');assert.equal(await ev("document.querySelector('[data-result-description]').textContent.includes('ch\u01b0a \u0111\u01b0\u1ee3c x\u00e1c nh\u1eadn')"),true);assert.equal(await ev("!!document.querySelector('[data-countdown], [data-receipt-waiting], [data-payment-pending], .stryke-countdown-banner, .stryke-bottom-bar')"),false);assert.equal(await ev('document.documentElement.scrollWidth<=innerWidth'),true);assert.equal(await ev('testErrors.length'),0);fs.writeFileSync(path.join(dir,'receipt-result-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'},sessionId)).data,'base64'));console.log('PASS browser actual upload ? result, refresh, clean DOM and no JS errors '+width+'px');}
for(const width of [1440,390]){
 await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600},sessionId);
 await send('Page.navigate',{url:url('/booking/history')},sessionId);await delay(650);
 await ev("(()=>{const b=[...document.querySelectorAll('[data-view-proof]')].find(b=>new URL(b.dataset.proofSrc,location.href).searchParams.get('id')==="+JSON.stringify(id)+");if(!b)throw Error('No receipt_url button');b.closest('details').open=true;b.click()})()");
 await delay(350);
 assert.equal(await ev("document.querySelector('[data-proof-modal]').open"),true);
 assert.equal(await ev("(()=>{const i=document.querySelector('[data-proof-image]');return i.complete&&i.naturalWidth>0&&!i.hidden})()"),true);
 assert.equal(await ev("new URL(document.querySelector('[data-proof-image]').src).searchParams.get('id')"),id);
 await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27},sessionId);
 await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27},sessionId);await delay(150);
 assert.equal(await ev("document.querySelector('[data-proof-modal]').open"),false);
 assert.equal(await ev('testErrors.length'),0);
 console.log('PASS existing history modal loads protected private receipt and closes with Escape '+width+'px');
}
console.log('Screenshots '+dir);
}finally{socket?.close();proc.kill()}})().catch(e=>{console.error(e);process.exitCode=1});
