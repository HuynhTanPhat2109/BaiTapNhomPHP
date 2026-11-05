// Invoked only by the opt-in MariaDB test; no application database or mock PDO/authentication.
const {chromium}=require(process.env.PLAYWRIGHT_PATH || 'D:/KiemThuPM/SOT357-65134364/node_modules/playwright-core');
const fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process');
if(!/^stryke_repeat_test_[a-f0-9]{10}$/.test(process.env.STRYKE_REPEAT_TEST_DB||''))throw Error('Run via repeat-booking-integration.php --run');
const out=path.resolve('storage/temp/repeat-booking-review'),ids=JSON.parse(fs.readFileSync(out+'/fixture-ids.json','utf8'));
const url=(route,query={})=>process.env.STRYKE_REPEAT_TEST_URL+'/index.php?'+new URLSearchParams({route,...query});
const schedule=url('/courts/schedule',{id:ids.facility,date:ids.date}),results=[],errors=[],network=[],consoleMessages=[];
const check=(ok,label)=>{results.push({pass:!!ok,label});if(!ok)throw Error(label);};
const wait=async(fn,label)=>{for(let i=0;i<100;i++){if(await fn())return;await new Promise(r=>setTimeout(r,100));}throw Error('Timeout: '+label);};
let browser;
(async()=>{try{
 browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_BINARY||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const context=await browser.newContext(),page=await context.newPage();
 // Only external QR pixels are substituted, not any application route or SQL response.
 await context.route('https://img.vietqr.io/**',r=>r.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aLX0AAAAASUVORK5CYII=','base64')}));
 const observe=p=>{p.on('pageerror',e=>errors.push(e.message));p.on('console',message=>consoleMessages.push({type:message.type(),text:message.text()}));p.on('response',r=>{if(r.url().startsWith(process.env.STRYKE_REPEAT_TEST_URL))network.push({url:r.url(),status:r.status()});});};observe(page);
 const cell=time=>page.locator(`.time-cell[data-court="${ids.court}"][data-start="${time}"]`);
 const button=()=>page.locator('#booking-next button[type=submit]');
 const login=async(p,username)=>{await p.goto(url('/login'));await p.locator('[name=username]').fill(username);await p.locator('[name=password]').fill(process.env.STRYKE_REPEAT_TEST_PASSWORD);await Promise.all([p.waitForNavigation(),p.locator('form button[type=submit]').click()]);};
 await login(page,'repeattestuser');await page.goto(schedule);await page.evaluate(()=>document.fonts.ready);
 await cell('13:00').click();await cell('13:30').click();
 await wait(()=>network.some(n=>n.url.includes('booking%2Fquote')&&n.status===409),'missing afternoon price rejected');
 check(await button().isDisabled(),'13:00–14:00 cannot proceed without a configured rate');
 if(process.argv[2]==='baseline'){
   check(await page.locator('#selection-feedback').count()===0,'reproduced: PHP markup omits the feedback target used by schedule.js');
   await page.screenshot({path:out+'/missing-price-before.png',fullPage:true});return;
 }
 await wait(async()=>/Chưa có bảng giá/.test(await page.locator('#selection-feedback').innerText()),'backend quote error becomes visible');
 check(await page.locator('#selection-feedback').isVisible(),'customer sees the actual pricing error beside Next');
 for(const width of [1440,1024,768,375]){
   await page.setViewportSize({width,height:1000});await page.evaluate(()=>document.fonts.ready);
   check(await page.locator('#selection-feedback').isVisible(),'pricing feedback visible at '+width);
   await page.screenshot({path:out+'/missing-price-after-'+width+'.png',fullPage:true});
   if(!(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)))fs.writeFileSync(out+'/overflow-'+width+'.json',JSON.stringify(await page.evaluate(()=>[...document.querySelectorAll('body *')].map(e=>({tag:e.tagName,cls:e.className,right:e.getBoundingClientRect().right,width:e.getBoundingClientRect().width,scroll:e.scrollWidth,overflow:getComputedStyle(e).overflowX})).filter(e=>e.right>innerWidth+1)),null,2));
   check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'no page overflow at '+width);
 }
 await page.setViewportSize({width:1440,height:1000});
 const token=async(p)=>p.locator('input[name=_token]').first().inputValue();
 const pick=(start,end)=>({facility:ids.facility,date:ids.date,slots:[{court:ids.court,start,end}]});
 const quote=async(start,end)=>context.request.post(url('/booking/quote'),{form:{_token:await token(page),selection:JSON.stringify(pick(start,end))}});
 const create=async(start,end,services=false)=>{
   await page.goto(schedule);
   const minutes=time=>Number(time.slice(0,2))*60+Number(time.slice(3));
   for(let time=minutes(start);time<minutes(end);time+=30)await cell(String(Math.floor(time/60)).padStart(2,'0')+':'+String(time%60).padStart(2,'0')).click();
   await wait(async()=>await button().isEnabled(),'priced selection ready');
   await Promise.all([page.waitForNavigation(),button().click()]);
   check(new URL(page.url()).searchParams.get('route')==='/booking/details','actual schedule form creates a new draft');
   if(services){const draft=await page.locator('[name=draft]').inputValue();await context.request.post(url('/booking/review'),{form:{_token:await token(page),draft,[`services[${ids.service}]`]:'2',note:''}});await page.reload();}
   await Promise.all([page.waitForNavigation(),page.locator('[data-confirm-booking]').click()]);
   check(new URL(page.url()).searchParams.get('route')==='/booking/wait','actual confirmation creates booking and payment');
   return new URL(page.url()).searchParams.get('id');
 };
 const first=await create('10:00','10:30',true);
 check(await page.locator('.stryke-bank-qr-thumb').isVisible(),'new booking displays QR before evidence upload');
 const image=Buffer.from(await page.evaluate(()=>{const c=document.createElement('canvas');c.width=600;c.height=800;const g=c.getContext('2d');g.fillStyle='#fff';g.fillRect(0,0,600,800);g.fillStyle='#000';g.font='24px sans-serif';g.fillText('TEST ONLY - NOT A BANK TRANSFER',20,80);return c.toDataURL('image/png').split(',')[1];}),'base64');
 await page.locator('#stryke-proof-input').setInputFiles({name:'test-proof.png',mimeType:'image/png',buffer:image});
 await Promise.all([page.waitForNavigation(),page.locator('[data-submit-payment]').click()]);
 check(await page.locator('[data-receipt-waiting]').isVisible(),'real MySQL upload shows evidence sent, not payment success');
 const stored=await context.request.get(url('/booking/receipt',{id:first}));check(stored.status()===200 && stored.headers()['content-type']==='image/png','owner reads the linked image through production route');
 const operatorContext=await browser.newContext(),operator=await operatorContext.newPage();observe(operator);await login(operator,'repeattestadmin');
 await operator.goto(url('/admin/bookings/show',{id:first}));
 await operator.locator('[data-booking-action=confirm]').first().click();const dialog=operator.locator('#booking-dialog-'+first);
 check(await dialog.locator('.booking-receipt-card img').evaluate(async e=>{await e.decode();return e.naturalWidth===600;}),'Admin loads the exact uploaded file from real database link');
 await dialog.getByRole('link',{name:'Xem ảnh lớn',exact:true}).click();check(await operator.locator('#booking-receipt-viewer').evaluate(e=>e.open),'large receipt viewer opens');await operator.locator('[data-receipt-close]').click();
 const version=await dialog.locator('[name=receipt_version]').inputValue();
 await dialog.locator('[data-booking-form=confirm] button[type=submit]').click();await Promise.all([operator.waitForNavigation(),operator.locator('[data-confirm-accept]').click()]);
 check((await operator.locator('body').innerText()).includes('Đã xác nhận thanh toán'),'manual confirmation succeeds on the migrated MariaDB schema');
 await operatorContext.request.post(url('/admin/bookings/confirm'),{form:{_token:await token(operator),id:first,receipt_version:version},maxRedirects:0});
 // Use the real header Back after the first booking is confirmed, then select another slot.
 await page.reload();await page.locator('[data-booking-back]').click();await page.waitForURL(u=>u.searchParams.get('route')==='/courts/schedule');
 check(await cell('10:00').isDisabled(),'confirmed slot remains booked after returning to schedule');
 check((await quote('10:00','10:30')).status()===409,'backend rejects booking a confirmed court interval');
 const second=await create('11:00','11:30');check(second!==first,'same logged-in customer creates a different booking');
 await page.goto(schedule);check(await cell('11:00').isDisabled(),'live second booking holds only its selected slot');
 check((await quote('11:00','11:30')).status()===409,'backend rejects a live overlapping hold');
 const cancel=await context.request.post(url('/booking/cancel'),{headers:{Accept:'application/json'},form:{_token:await token(page),id:second}});
 check(cancel.status()===200 && (await cancel.json()).state==='DA_HUY','actual POST cancels the second pending booking');
 await page.reload();check(await cell('11:00').isEnabled(),'cancellation releases that slot');
 const third=await create('11:00','11:30');check(third!==second,'released slot can be booked again using a new request key');
 execFileSync(process.env.PHP_BINARY||'D:/PHP/php/php.exe',['-d','variables_order=EGPCS','tests/repeat-booking-integration.php','--expire',third],{windowsHide:true});
 await page.goto(schedule);check(await cell('11:00').isEnabled(),'expired hold is free before database cleanup');
 const fourth=await create('11:00','11:30');check(fourth!==third,'expired interval can be booked by the same customer again');
 execFileSync(process.env.PHP_BINARY||'D:/PHP/php/php.exe',['-d','variables_order=EGPCS','tests/repeat-booking-integration.php','--add-price',String(ids.court)],{windowsHide:true});
 const fifth=await create('13:00','14:00');
 check(fifth!==fourth && (await page.locator('body').innerText()).includes('13:00'),'13:00-14:00 works after supplying a valid afternoon rate in the test database only');
 check(errors.length===0,'no JavaScript runtime exceptions in calendar, upload or manual confirmation');
 check(!network.some(n=>n.status>=500),'no HTTP 5xx in actual application requests');
 await operatorContext.close();await context.close();
}catch(error){
 if(results.every(result=>result.pass))results.push({pass:false,label:error.message});
 throw error;
}finally{
 if(browser)await browser.close();fs.writeFileSync(out+'/browser-'+(process.argv[2]||'regression')+'-results.json',JSON.stringify({results,errors,consoleMessages,network},null,2));
 console.log(`Chrome: ${results.filter(r=>r.pass).length} PASS, ${results.filter(r=>!r.pass).length} FAIL`);
}})().catch(e=>{console.error(e);process.exitCode=1;});
