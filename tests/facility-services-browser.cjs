const fs=require('node:fs'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict'),{spawn}=require('node:child_process');
const root=path.resolve(__dirname,'..'),out=fs.mkdtempSync(path.join(root,'storage/temp/facility-services-')),delay=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const listener=net.createServer();await new Promise(r=>listener.listen(0,'127.0.0.1',r));const port=listener.address().port;await new Promise(r=>listener.close(r));
 const address=(route='/courts',query={})=>'http://127.0.0.1:'+port+'/index.php?'+new URLSearchParams({route,...query});
 const php=spawn('C:/xampp/php/php.exe',['-S','127.0.0.1:'+port,'-t','public','tests/fixtures/facility-services.php'],{cwd:root,windowsHide:true,env:{...process.env,STRYKE_FACILITY_SERVICES_TEST:'1'},stdio:['ignore','pipe','pipe']});
 let log='',chrome,socket,sequence=0,checks=0;const jobs=new Map();php.stdout.on('data',c=>log+=c);php.stderr.on('data',c=>log+=c);
 const check=(ok,label)=>{assert.ok(ok,label);checks++};
 try{
  for(let i=0;;i++){try{if((await fetch(address())).ok)break}catch{}if(i>60)throw Error('PHP startup');await delay(100)}
  const profile=path.join(out,'profile');chrome=spawn(process.env.CHROME_BINARY||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile],{windowsHide:true,stdio:'ignore'});
  for(let i=0;!fs.existsSync(path.join(profile,'DevToolsActivePort'));i++){if(i>100)throw Error('Chrome startup');await delay(100)}
  const [debugPort,endpoint]=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').trim().split('\n');socket=new WebSocket('ws://127.0.0.1:'+debugPort+endpoint);await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j});
  socket.onmessage=e=>{const m=JSON.parse(e.data);if(!m.id)return;const job=jobs.get(m.id);jobs.delete(m.id);clearTimeout(job.timer);m.error?job.reject(Error(m.error.message)):job.resolve(m.result)};
  const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const id=++sequence;jobs.set(id,{resolve,reject,timer:setTimeout(()=>reject(Error(method)),10000)});socket.send(JSON.stringify({id,method,params,sessionId}))});
  const {targetId}=await send('Target.createTarget',{url:'about:blank'}),{sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
  const ev=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true},sessionId);if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value};
  const wait=async expression=>{for(let i=0;;i++){if(await ev(expression))return;if(i>100)throw Error('Timeout: '+expression);await delay(50)}};
  await send('Page.enable',{},sessionId);await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]},sessionId);
  await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.testErrors=[];addEventListener('error',e=>testErrors.push(e.message));addEventListener('securitypolicyviolation',e=>testErrors.push(e.violatedDirective));"},sessionId);
  const navigate=async url=>{await send('Page.navigate',{url},sessionId);const parsed=new URL(url);const expected=parsed.searchParams.get('route')==='/courts/show'?`new URL(location.href).searchParams.get('facility')===${JSON.stringify(parsed.searchParams.get('id'))}`:`location.href===${JSON.stringify(url)}`;await wait(`(${expected}) && document.readyState==='complete'`)};
  const services=()=>ev("document.querySelector('[data-facility-tab=services]').click()");
  for(const width of [1440,390]){
   await send('Emulation.setDeviceMetricsOverride',{width,height:950,deviceScaleFactor:1,mobile:width<600},sessionId);
   await navigate(address());
   await ev("[...document.querySelectorAll('.facility-detail-link')].find(a=>new URL(a.href).searchParams.get('id')==='1').click()");
   await wait("document.querySelector('#detail-drawer').open && !!document.querySelector('.facility-service-tables') && !document.querySelector('#facility-detail-content').hasAttribute('aria-busy')");
   await services();
   check(await ev("!document.querySelector('[data-facility-panel=services]').hidden && document.querySelectorAll('.facility-service-tables table').length===3"),'AJAX drawer real tables at '+width);
   check(await ev("getComputedStyle(document.querySelector('.facility-service-tables table')).backgroundColor==='rgb(255, 255, 255)'"),'light tables');
   check(await ev("[...document.querySelectorAll('.facility-service-tables td')].some(e=>e.textContent.includes('70.000')) && [...document.querySelectorAll('.facility-service-tables td')].some(e=>e.textContent.includes('80.000'))"),'different day prices visible');
   check(await ev("document.querySelectorAll('.facility-service-group').length===3"),'real service groups');
   check(await ev('document.documentElement.scrollWidth<=innerWidth+1'),'no page overflow');
   check(await ev("document.querySelectorAll('.facility-service-tables button,.facility-service-tables input').length===0"),'read-only public catalog');
   await ev("document.querySelector('[data-facility-tab=services]').scrollIntoView({block:'start'})");
   await delay(180);const {data}=await send('Page.captureScreenshot',{format:'png'},sessionId);fs.writeFileSync(path.join(out,'tables-'+width+'.png'),Buffer.from(data,'base64'));
   for(const tab of ['info','images','rules','reviews']){
    await ev(`document.querySelector('[data-facility-tab=${tab}]').click()`);
    check(await ev(`!document.querySelector('[data-facility-panel=${tab}]').hidden && document.querySelector('[data-facility-panel=services]').hidden`),'other tab '+tab+' still works');
   }
   check(await ev("new URL(document.querySelector('.detail-book a').href).searchParams.get('id')==='1'"),'booking link correct');
   await services();await ev("document.querySelector('[data-facility-tab=services]').focus();document.querySelector('[data-facility-tab=services]').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}))");
   check(await ev("document.querySelector('[data-facility-tab=images]').getAttribute('aria-selected')==='true'"),'keyboard tabs unchanged');
   check(await ev('testErrors.length===0'),'no JavaScript/CSP errors');
   await navigate(address('/courts/show',{id:'6'}));await wait("document.querySelector('#detail-drawer').open");await services();
   check(await ev("document.querySelectorAll('.facility-service-prices').length===1 && document.querySelector('.facility-service-tables').textContent.includes('40.000')"),'single type direct URL');
   await navigate(address('/courts/show',{id:'2'}));await wait("document.querySelector('#detail-drawer').open");await services();
   check(await ev("document.querySelector('.facility-service-tables').textContent.includes('Cơ sở chưa cập nhật bảng giá.') && document.querySelector('.facility-service-tables').textContent.includes('Cơ sở chưa cập nhật dịch vụ.')"),'empty catalog');
  }
  await navigate(address('/courts/schedule',{id:'1'}));await wait("!!document.querySelector('#court-schedule')");
  check(await ev("document.querySelector('#court-schedule').dataset.slotCount==='36'"),'existing schedule still renders 30-minute slots');
  check(!/PHP (Warning|Fatal error|Parse error)/.test(log),'no PHP runtime errors');
  console.log('PASS: '+checks+' actual Chrome/HTTP checks; SQLite RAM only. Screenshots: '+out);
 }finally{fs.writeFileSync(path.join(out,'server.log'),log);for(const job of jobs.values())clearTimeout(job.timer);if(socket)socket.close();if(chrome)chrome.kill();php.kill()}
})().catch(error=>{console.error(error);process.exitCode=1});
