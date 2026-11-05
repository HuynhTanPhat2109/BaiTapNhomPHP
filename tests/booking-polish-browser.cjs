// Real PHP routes/controllers/services, Chrome UI, RAM-only booking writes and employee form reads.
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright-core');
const { spawn } = require('node:child_process');
const fs = require('node:fs'), path = require('node:path'), net = require('node:net');
const root = path.resolve(__dirname, '..'), out = path.join(root, 'storage/temp/booking-polish-review');
const results = [], servers = [];
const check = (ok, label) => { results.push({ pass: Boolean(ok), label }); if (!ok) throw Error(label); };
fs.mkdirSync(out + '/sessions', { recursive: true });
async function start(fixture, env) {
  const socket = net.createServer(); await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const server = spawn(process.env.PHP_BINARY || 'D:/PHP/php/php.exe', ['-S', `127.0.0.1:${port}`, '-t', 'public', `tests/fixtures/${fixture}.php`], {
    cwd: root, windowsHide: true, env: { ...process.env, ...env }, stdio: ['ignore', 'pipe', 'pipe'],
  });
  const state = { server, log: '', fixture }; servers.push(state);
  server.stdout.on('data', chunk => state.log += chunk); server.stderr.on('data', chunk => state.log += chunk);
  for (let i = 0; ; i++) {
    const ready = await new Promise(resolve => { const s = net.connect(port, '127.0.0.1'); s.once('connect', () => { s.destroy(); resolve(true); }); s.once('error', () => resolve(false)); });
    if (ready) break; if (i > 100 || server.exitCode !== null) throw Error('Fixture did not start'); await new Promise(resolve => setTimeout(resolve, 50));
  }
  return (route, query = {}) => `http://127.0.0.1:${port}/index.php?${new URLSearchParams({ route, ...query })}`;
}
let browser;
(async () => {
  browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  const url = await start('booking-actions', { STRYKE_BOOKING_ACTIONS_UI: '1', STRYKE_PR6_UI: '1', STRYKE_PR6_SESSIONS: out + '/sessions', STRYKE_RECEIPT_ROOT: out+'/private' });
  fs.mkdirSync(out+'/private',{recursive:true});
  const sample=await browser.newPage();
  fs.writeFileSync(out+'/private/sample-proof.png',Buffer.from(await sample.evaluate(()=>{const c=document.createElement('canvas');c.width=1000;c.height=1500;const g=c.getContext('2d');g.fillStyle='#fff';g.fillRect(0,0,c.width,c.height);g.fillStyle='#13251b';g.font='36px sans-serif';['TEST FIXTURE','NOT A BANK TRANSACTION','Booking evidence preview test','Customer image - operator must check funds'].forEach((s,i)=>g.fillText(s,30,100+i*90));return c.toDataURL('image/png').split(',')[1];}),'base64'));
  await sample.close();
  const meta = async (context, query = {}) => (await context.request.get(url('/__booking_actions/meta', query))).json();
  const post = (context, action, form, query = {}) => context.request.post(url('/admin/bookings/' + action, query), { form, maxRedirects: 0 });
  const valid = (token, state) => { const p=state?.payments.find(p=>p.ma_dat_san===1); return { id:'1',_token:token,from_list:'1','list[q]':'PR6','list[page]':'1','list[per_page]':'20',
    receipt_version:p?.anh_minh_chung ? require('node:crypto').createHash('sha256').update(p.anh_minh_chung+'|'+(p.minh_chung_gui_luc||'')).digest('hex') : '0'.repeat(64) }; };
  const open = async (page, id = 1) => {
    const menu = page.locator(`[data-row-actions]:has(summary[aria-label="Thao tác cho booking #${id}"])`);
    await menu.locator('summary').click(); await menu.locator('[data-booking-action="confirm"]').click();
    return page.locator(`#booking-dialog-${id}`);
  };
  for (const role of ['QUAN_TRI_TAP_DOAN', 'QUAN_LY_CONG_TY', 'QUAN_LY_CO_SO', 'NHAN_VIEN']) {
    const context = await browser.newContext({ extraHTTPHeaders: { 'X-Stryke-Test-Role': role }, reducedMotion: 'reduce' });
    const page = await context.newPage(), errors = []; page.on('pageerror', error => errors.push(error.message));
    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      check((await page.goto(url('/admin/bookings', { reset: '1' }))).ok(), `${role}/${width}: real scoped list loads`);
      const menu = page.locator('[data-row-actions]:has(summary[aria-label="Thao tác cho booking #1"])');
      await menu.locator('summary').click();
      const styles = await menu.locator('.list-action-menu__panel > .list-action').evaluateAll(items => items.map(item => ({ width: item.getBoundingClientRect().width,
        background: getComputedStyle(item).backgroundColor, icons: item.querySelectorAll('svg').length, height: item.getBoundingClientRect().height })));
      check(styles.length === 4 && styles.every(item => item.icons === 1 && Math.abs(item.width - styles[0].width) < 1 && item.height >= 40)
        && new Set(styles.map(item => item.background)).size === 4, `${role}/${width}: original themed menu icons, spacing and equal widths`);
      check(await menu.locator('.list-action-menu__panel').evaluate(panel => { const r=panel.getBoundingClientRect(); return panel.matches(':popover-open') && r.left>=0 && r.right<=innerWidth && r.bottom<=innerHeight; }), `${role}/${width}: top-layer menu fits viewport`);
      await page.keyboard.press('Escape'); check(!(await menu.evaluate(e=>e.open)), `${role}/${width}: menu Escape`);
      await menu.locator('summary').click(); await page.locator('h1').click(); check(!(await menu.evaluate(e=>e.open)), `${role}/${width}: menu outside close`);
      let dialog = await open(page), form = dialog.locator('[data-booking-form="confirm"]');
      check(await form.locator('[type="submit"]').isDisabled() && (await dialog.innerText()).includes('chưa gửi minh chứng'), `${role}/${width}: absent evidence disables confirmation`);
      check(!(await dialog.innerText()).includes('Giao dịch ngân hàng') && !(await dialog.innerText()).includes('Chưa kết nối'), `${role}/${width}: no bank synchronization card or dependency`);
      await page.keyboard.press('Escape');
      check(!(await dialog.evaluate(e=>e.open)), `${role}/${width}: original dialog Escape`);
      await page.goto(url('/admin/bookings', { seed_receipt: '1' }));
      dialog = await open(page); form = dialog.locator('[data-booking-form="confirm"]');
      check(await dialog.locator('.booking-action-section').count() === 2 && (await dialog.innerText()).includes('Ảnh minh chứng chuyển khoản'), `${role}/${width}: same confirmation dialog has two balanced evidence cards`);
      const dialogText=await dialog.innerText();
      check(['60.000','PR6-1','Tạo booking lúc','Hạn giữ sân','Trạng thái booking','Gửi lúc'].every(value => (dialogText.includes(value))), `${role}/${width}: database booking context remains present`);
      check(await form.locator('[name="so_tien_thuc_nhan"], [name="ma_giao_dich"], [name="ngay_thanh_toan"], [name="da_doi_chieu"]').count() === 0, `${role}/${width}: no bank inputs or checkbox in normal confirmation`);
      check(new URL(await form.getAttribute('action'),page.url()).searchParams.get('route')==='/admin/bookings/confirm'
        && await form.locator('[name="_token"]').count()===1 && await form.locator('[name="id"]').inputValue()==='1'
        && await form.locator('[name="list[page]"]').inputValue()==='1', `${role}/${width}: original POST/CSRF/booking/list context preserved`);
      check(await form.locator('[type="submit"]').isEnabled(), `${role}/${width}: linked proof plus eligible booking enables human confirmation without API`);
      const img=dialog.locator('.booking-receipt-card img'); await img.waitFor();
      check(await img.evaluate(async e=>{await e.decode();return e.complete && e.naturalWidth>0 && getComputedStyle(e).objectFit==='contain';}), `${role}/${width}: correct booking image loads without cropping`);
      check(new URL(await img.getAttribute('src'),page.url()).searchParams.get('id')==='1', `${role}/${width}: image bound to booking ID`);
      await dialog.getByRole('link',{name:'Xem ảnh lớn',exact:true}).click();
      const viewer=page.locator('#booking-receipt-viewer');
      check(await viewer.evaluate(e=>e.open) && await dialog.evaluate(e=>e.open), `${role}/${width}: image viewer preserves confirmation dialog`);
      await viewer.locator('[data-receipt-zoom]').click(); check(await viewer.locator('[data-receipt-zoom]').getAttribute('aria-pressed')==='true', `${role}/${width}: zoom works`);
      await viewer.locator('[data-receipt-close]').click(); check(!(await viewer.evaluate(e=>e.open)) && await dialog.evaluate(e=>e.open), `${role}/${width}: closing image returns to existing dialog`);
      await form.locator('[type="submit"]').click();
      check((await page.locator('#workspace-confirm-description').innerText())==='Bạn xác nhận đã kiểm tra tài khoản nhận tiền và nhận đủ số tiền của booking này?', `${role}/${width}: explicit human responsibility before final POST`);
      check((await page.locator('[data-confirm-cancel]').innerText())==='Quay lại', `${role}/${width}: final review has Back/Confirm`);
      await page.locator('[data-confirm-cancel]').click();
      check(await dialog.evaluate(e=>e.open) && await form.locator('[type="submit"]').isEnabled(), `${role}/${width}: review cancellation preserves original modal`);
      check(await dialog.evaluate(e=>{const r=e.getBoundingClientRect();return r.left>=0 && r.right<=innerWidth && e.scrollWidth<=e.clientWidth+1;}), `${role}/${width}: responsive modal has no horizontal overflow`);
      if (role==='QUAN_TRI_TAP_DOAN') { await dialog.evaluate(e=>{e.scrollTop=0;}); await page.screenshot({path:`${out}/receipt-modal-${width}.png`}); }
      const review = form.getByRole('link',{name:'Xem đối soát'});
      check(new URL(await review.getAttribute('href'),page.url()).searchParams.get('route')==='/admin/bookings/show', `${role}/${width}: original reconciliation route remains available`);
      await review.click();
      check(await page.locator('#booking-operations').isVisible() && await page.locator('#booking-operations [name="ly_do_doi_soat"]').count()===1, `${role}/${width}: reconciliation remains separate from confirmation`);
      await page.evaluate(()=>document.fonts.ready);
      const fits=await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1);
      if(!fits) { await page.screenshot({path:`${out}/detail-overflow-${role}-${width}.png`}); fs.writeFileSync(`${out}/overflow.json`,JSON.stringify(await page.evaluate(()=>[...document.querySelectorAll('body *')].map(e=>({tag:e.tagName,cls:e.className,x:e.getBoundingClientRect().x,right:e.getBoundingClientRect().right})).filter(e=>e.right>innerWidth+1)),null,2)); }
      check(fits, `${role}/${width}: detail has no page overflow`);
    }
    let initial=await meta(context,{reset:'1'});
    await post(context,'confirm',{...valid(initial.token),confirmation_mode:'manual',so_tien_thuc_nhan:'60000',ma_giao_dich:'FORGED',ngay_thanh_toan:'2026-11-04T10:05',verified:'1',da_doi_chieu:'1'});
    check(JSON.stringify(await meta(context))===JSON.stringify(initial), `${role}: forged manual/API values cannot bypass missing receipt`);
    const csrf=await post(context,'confirm',{...valid(initial.token),_token:'invalid'});
    check(csrf.status()===403 && JSON.stringify(await meta(context))===JSON.stringify(initial), `${role}: original CSRF guard before writes`);
    initial=await meta(context,{seed_receipt:'1'});
    for (const id of ['2','3','4','7']) {
      if (id==='7' && role==='QUAN_TRI_TAP_DOAN') continue;
      await post(context,'confirm',{...valid(initial.token),id});
      check(JSON.stringify(await meta(context))===JSON.stringify(initial), `${role}: cancelled/expired/paid/foreign #${id} cannot confirm`);
    }
    if (role!=='QUAN_TRI_TAP_DOAN') {
      check((await context.request.get(url('/admin/bookings/show',{id:'7'}))).status()===404, `${role}: foreign booking detail inaccessible`);
      check((await context.request.get(url('/booking/receipt',{id:'7'}))).status()===404, `${role}: foreign image inaccessible`);
    }
    await page.goto(url('/admin/bookings'));
    const dialog=await open(page), form=dialog.locator('[data-booking-form="confirm"]');
    let submissions=0;page.on('request',r=>{if(r.method()==='POST' && new URL(r.url()).searchParams.get('route')==='/admin/bookings/confirm')submissions++;});
    await form.locator('[type="submit"]').click();
    await Promise.all([page.waitForNavigation(),page.locator('[data-confirm-accept]').evaluate(b=>{b.click();b.click();})]);
    let after=await meta(context), paid=after.payments.find(p=>p.ma_dat_san===1);
    check(submissions===1 && Number(after.stock)===Number(initial.stock)-2 && Number(after.invoices)===1, `${role}: double click sends one POST, one stock deduction and invoice`);
    check(paid.trang_thai==='THANH_CONG' && after.bookings[0].trang_thai_dat_san==='DA_XAC_NHAN', `${role}: atomic payment and booking confirmation`);
    check(paid.ma_giao_dich===null && paid.ngay_thanh_toan===null && Number(paid.ma_tai_khoan_xac_nhan)===1 && Boolean(paid.xac_nhan_thu_cong_luc), `${role}: actor/time audit kept separate from actual bank fields`);
    check(after.bookings[0].het_han_giu_san_luc===initial.bookings[0].het_han_giu_san_luc, `${role}: original six minute hold unchanged`);
    await post(context,'confirm',valid(initial.token,initial)); check(JSON.stringify(await meta(context))===JSON.stringify(after), `${role}: repeated request cannot deduct twice`);
    await page.goto(url('/admin/bookings/show',{id:'1'}));
    check((await page.locator('#booking-operations').innerText()).includes('Operator fixture'), `${role}: confirmation history displays actual operator`);
    initial=await meta(context,{reset:'1',seed_receipt:'1'});
    await post(context,'confirm',valid(initial.token,initial),{invoice_error:'1'});
    check(JSON.stringify(await meta(context))===JSON.stringify(initial), `${role}: invoice failure rolls back payment/booking/stock/audit`);
    await post(context,'reconcile',{id:'1',_token:initial.token,so_tien_thuc_nhan:'55000',ma_giao_dich:'RAM-REVIEW',ly_do_doi_soat:'Wrong amount fixture'});
    after=await meta(context); check(Number(after.stock)===200 && Number(after.invoices)===0 && after.bookings[0].trang_thai_dat_san==='CHO_THANH_TOAN', `${role}: reconciliation does not confirm or deduct`);
    await post(context,'confirm',valid(initial.token));check(JSON.stringify(await meta(context))===JSON.stringify(after), `${role}: known mismatch cannot use normal receipt confirmation`);
    initial=await meta(context,{reset:'1',seed_receipt:'1',expire_fixture:'1'});
    await post(context,'confirm',valid(initial.token,initial));check(JSON.stringify(await meta(context))===JSON.stringify(initial), `${role}: proof cannot restore expired hold`);
    check(errors.length===0, `${role}: no JavaScript exceptions`);
    await context.close();
  }
  // Real multipart upload and customer/admin routes; all writes target SQLite RAM and private fixture files.
  const context=await browser.newContext({extraHTTPHeaders:{'X-Stryke-Test-Role':'KHACH_HANG'}}), page=await context.newPage(), customerErrors=[];
  page.on('pageerror',error=>customerErrors.push(error.message));
  let initial=await meta(context,{reset:'1'});
  await page.goto(url('/booking/wait',{id:'1'}));
  check(await page.locator('.stryke-bank-qr-thumb').isVisible() && await page.locator('#stryke-proof-input').inputValue()==='', 'customer sees QR before selecting any evidence');
  const png=Buffer.from(await page.evaluate(()=>{const c=document.createElement('canvas');c.width=1000;c.height=1500;const g=c.getContext('2d');g.fillStyle='white';g.fillRect(0,0,c.width,c.height);g.fillStyle='#13251b';g.font='36px sans-serif';['TEST FIXTURE - NOT A BANK TRANSFER','Booking evidence UI test','Do not use on the application database'].forEach((s,i)=>g.fillText(s,30,100+i*70));return c.toDataURL('image/png').split(',')[1];}),'base64');
  const input=page.locator('#stryke-proof-input');
  await input.setInputFiles({name:'proof.png',mimeType:'image/png',buffer:png});
  check(await page.locator('[data-upload-preview]').isVisible(), 'customer local preview');
  await page.locator('[data-remove-file]').click();check(await input.inputValue()==='' && await page.locator('[data-upload-placeholder]').isVisible(), 'customer replaces/removes image before upload');
  await input.setInputFiles({name:'bad.svg',mimeType:'image/svg+xml',buffer:Buffer.from('<svg/>')});check(await input.inputValue()==='', 'client clears unsupported image type');
  const upload=(file,extra={},headers={})=>context.request.post(url('/booking/receipt'),{headers,multipart:{id:'1',_token:initial.token,...extra,...(file?{bien_lai:file}:{})},maxRedirects:0});
  for(const [file,label] of [[null,'missing file'],[{name:'fake.jpg',mimeType:'image/jpeg',buffer:Buffer.from('<?php echo "malicious"; ?>')},'forged JPEG MIME'],[{name:'bad.svg',mimeType:'image/svg+xml',buffer:Buffer.from('<svg onload="alert(1)"/>')},'active SVG'],[{name:'too-large.png',mimeType:'image/png',buffer:Buffer.alloc(5*1024*1024+1,65)},'over 5 MB']]) {
    await upload(file);check(JSON.stringify(await meta(context))===JSON.stringify(initial),'server rejects '+label+' without writes');
  }
  check((await upload({name:'proof.png',mimeType:'image/png',buffer:png},{_token:'invalid'})).status()===403,'upload requires CSRF');
  check((await upload({name:'proof.png',mimeType:'image/png',buffer:png},{},{'X-Stryke-Customer':'20'})).status()===404,'foreign customer cannot attach image');
  const response=await upload({name:'proof.php',mimeType:'image/png',buffer:png});check(response.status()===303,'real HTTP upload succeeds with server-controlled image filename');
  let after=await meta(context), payment=after.payments.find(p=>p.ma_dat_san===1);
  check(payment.anh_minh_chung.endsWith('.png') && !payment.anh_minh_chung.includes('proof.php') && Boolean(payment.minh_chung_gui_luc),'proof persisted with safe generated path and actual submission time');
  check(after.stock===initial.stock && after.bookings[0].trang_thai_dat_san==='CHO_THANH_TOAN' && after.bookings[0].het_han_giu_san_luc===initial.bookings[0].het_han_giu_san_luc,'submission preserves physical/virtual stock and original hold');
  for(const [mime,ext] of [['image/jpeg','jpg'],['image/webp','webp']]) {
    const bytes=Buffer.from(await page.evaluate(type=>{const c=document.createElement('canvas');c.width=1000;c.height=1500;const g=c.getContext('2d');g.fillStyle='white';g.fillRect(0,0,c.width,c.height);g.fillStyle='black';g.font='36px sans-serif';g.fillText('TEST FIXTURE - '+type,30,100);return c.toDataURL(type).split(',')[1];},mime),'base64');
    await input.setInputFiles({name:'proof.'+ext,mimeType:mime,buffer:bytes}); await page.locator('[data-upload-preview]').waitFor({state:'visible'});
    check(await page.locator('[data-upload-preview]').isVisible(),mime+': client preview accepts format');
    check((await upload({name:'proof.'+ext,mimeType:mime,buffer:bytes})).status()===303,mime+': actual server multipart upload succeeds');
    const served=await context.request.get(url('/booking/receipt',{id:'1'})); check(served.headers()['content-type']===mime,mime+': protected endpoint serves validated actual MIME');
  }
  for(const width of [1440,1024,768,375]) {await page.setViewportSize({width,height:1000});await page.goto(url('/booking/wait',{id:'1'}));check(await page.locator('[data-receipt-waiting]').isVisible() && !(await page.locator('[data-payment-success]').isVisible()),'customer/'+width+': evidence submitted is waiting, not paid');check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'customer/'+width+': responsive without overflow');await page.screenshot({path:`${out}/receipt-customer-${width}.png`});}
  const image=await context.request.get(url('/booking/receipt',{id:'1'}));check(image.status()===200 && image.headers()['content-type']==='image/webp' && image.headers()['cache-control'].includes('no-store'),'authenticated owner can retrieve protected image');
  check((await context.request.get(url('/booking/receipt',{id:'1'}),{headers:{'X-Stryke-Customer':'20'}})).status()===404,'other customer cannot read image');
  check((await context.request.get(url('/booking/receipt',{id:'1'}),{headers:{'X-Stryke-Test-Role':'NHAN_VIEN','X-Stryke-Facility':'2'}})).status()===404,'foreign staff cannot read image');
  check((await context.request.get(url('/booking/receipt',{id:'1'}),{headers:{'X-Stryke-Test-Role':'QUAN_LY_CONG_TY','X-Stryke-Company':'2'}})).status()===404,'foreign company cannot read image');
  const guest=await browser.newContext({extraHTTPHeaders:{'X-Stryke-Test-Role':'GUEST'}});
  check((await guest.request.get(url('/booking/receipt',{id:'1'}),{maxRedirects:0})).status()!==200,'anonymous request cannot retrieve image');await guest.close();
  check((await context.request.post(url('/admin/bookings/confirm'),{form:valid(initial.token),maxRedirects:0})).status()===403,'customer cannot forge staff confirmation');
  await page.setExtraHTTPHeaders({'X-Stryke-Test-Role':'QUAN_TRI_TAP_DOAN'});await page.goto(url('/admin/bookings'));
  const uploadedDialog=await open(page);check(await uploadedDialog.locator('.booking-receipt-card img').evaluate(async e=>{await e.decode();return e.complete && e.naturalWidth===1000;}),'Admin sees the exact customer uploaded image');
  const oldVersion=await uploadedDialog.locator('[name="receipt_version"]').inputValue();
  await upload({name:'replacement.png',mimeType:'image/png',buffer:png},{},{'X-Stryke-Test-Role':'KHACH_HANG'});
  const replaced=await meta(context);
  await context.request.post(url('/admin/bookings/confirm'),{headers:{'X-Stryke-Test-Role':'QUAN_TRI_TAP_DOAN'},form:{id:'1',_token:initial.token,receipt_version:oldVersion},maxRedirects:0});
  check(JSON.stringify(await meta(context))===JSON.stringify(replaced),'replaced image invalidates stale operator review before any payment/stock write');
  await page.goto(url('/admin/bookings'));await open(page);
  await uploadedDialog.getByRole('link',{name:'Xem ảnh lớn',exact:true}).click();await page.locator('[data-receipt-zoom]').click();check(await page.locator('#booking-receipt-viewer').evaluate(e=>e.classList.contains('is-zoomed')),'uploaded full-size proof can be enlarged');await page.locator('[data-receipt-close]').click();
  await uploadedDialog.locator('[data-booking-form="confirm"] [type="submit"]').click();await Promise.all([page.waitForNavigation(),page.locator('[data-confirm-accept]').click()]);
  after=await meta(context);check(after.payments[0].trang_thai==='THANH_CONG' && Number(after.stock)===198,'uploaded proof confirms successfully through real controller');
  // Start a new draft and create the booking/payment through Phat's actual HTTP flow.
  await page.setExtraHTTPHeaders({'X-Stryke-Test-Role':'KHACH_HANG'});
  initial=await meta(context,{reset:'1'});
  const date=new Date(Date.now()+2*86400000).toLocaleDateString('en-CA',{timeZone:'Asia/Ho_Chi_Minh'});
  let bookingResponse=await context.request.post(url('/booking/start'),{form:{_token:initial.token,selection:JSON.stringify({facility:1,date,slots:[{court:1,start:'14:00',end:'14:30'}]})},maxRedirects:0});
  check(bookingResponse.status()===303,'actual booking start creates customer session draft');
  const draft=new URL(bookingResponse.headers().location,url('/')).searchParams.get('draft');
  await context.request.post(url('/booking/review'),{form:{_token:initial.token,draft,'services[1]':'2',note:'Receipt flow fixture only'}});
  await page.goto(url('/booking/details',{draft}));
  const quote=await page.locator('[name="quote"]').inputValue();
  bookingResponse=await context.request.post(url('/booking/confirm'),{form:{_token:initial.token,draft,quote,'services[1]':'2',note:'Receipt flow fixture only'},maxRedirects:0});
  check(bookingResponse.status()===303,'actual booking confirmation creates booking and pending payment');
  const bookingId=new URL(bookingResponse.headers().location,url('/')).searchParams.get('id');
  check(Number(bookingId)>9,'new booking ID comes from production writer, not preseeded fixture');
  await page.goto(url('/booking/wait',{id:bookingId}));
  check(await page.locator('.stryke-bank-qr-thumb').isVisible(),'new booking displays QR without requiring evidence first');
  await page.locator('#stryke-proof-input').setInputFiles({name:'new-booking.png',mimeType:'image/png',buffer:png});
  await Promise.all([page.waitForNavigation(),page.locator('[data-submit-payment]').click()]);
  check(await page.locator('[data-receipt-waiting]').isVisible(),'new booking upload uses original customer form and displays waiting');
  await page.setExtraHTTPHeaders({'X-Stryke-Test-Role':'QUAN_TRI_TAP_DOAN'});await page.goto(url('/admin/bookings'));
  const createdDialog=await open(page,Number(bookingId));
  check(new URL(await createdDialog.locator('.booking-receipt-card img').getAttribute('src'),page.url()).searchParams.get('id')===bookingId,'new booking modal shows only its linked evidence');
  await createdDialog.locator('[data-booking-form="confirm"] [type="submit"]').click();
  await Promise.all([page.waitForNavigation(),page.locator('[data-confirm-accept]').click()]);
  after=await meta(context);const newPayment=after.payments.find(p=>String(p.ma_dat_san)===bookingId);
  check(newPayment.trang_thai==='THANH_CONG' && Number(after.stock)===198 && Number(after.invoices)===1,'complete draft → QR → upload → manual confirmation → one stock deduction');
  initial=await meta(context,{reset:'1',seed_receipt:'1',seed_recorded:'1'});
  await page.goto(url('/admin/bookings/show',{id:'1'}));
  const exception=page.locator('[data-manual-payment]');
  check(await exception.locator('[type="checkbox"]').count()===0,'reconciliation exception does not require a checkbox');
  await exception.locator('[name="so_tien_thuc_nhan"]').fill('60000');
  await exception.locator('[name="ma_giao_dich"]').fill('RAM-RECONCILED-TRANSACTION');
  await exception.locator('[name="ngay_thanh_toan"]').fill('2026-11-05T08:00');
  await exception.locator('[type="submit"]').click();
  await Promise.all([page.waitForNavigation(),page.locator('[data-confirm-accept]').click()]);
  after=await meta(context);check(after.payments[0].trang_thai==='THANH_CONG' && after.payments[0].ma_giao_dich==='RAM-RECONCILED-TRANSACTION'
    && after.payments[0].ngay_thanh_toan==='2026-11-05 08:00:00' && Number(after.stock)===198,'existing exception reconciliation preserves explicit bank facts and finalizes once');
  await page.setExtraHTTPHeaders({'X-Stryke-Test-Role':'KHACH_HANG'});
  await page.goto(url('/booking/wait',{id:'1',reset:'1',seed_receipt:'1',expire_fixture:'1'}));
  check(!(await page.locator('[data-receipt-waiting]').isVisible()) && await page.locator('[data-payment-expired]').isVisible()
    && !(await page.locator('[data-payment-success]').isVisible()),'customer expired evidence does not look confirmed or held');
  check(customerErrors.length===0,'customer/upload/expiry flow has no JavaScript exceptions');
  await context.close();
  const employees = await start('admin-navigation', { STRYKE_NAVIGATION_TEST: '1', STRYKE_NAVIGATION_SESSIONS: out + '/employees' });
  for (const [role, area] of [['QUAN_TRI_TAP_DOAN', 'admin'], ['QUAN_LY_CONG_TY', 'company']]) {
    const context = await browser.newContext({ extraHTTPHeaders: { 'X-Stryke-Test-Role': role } });
    const page = await context.newPage();
    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      check((await page.goto(employees(`/${area}/employees/create`))).ok(), `${role}/${width}: employee creation form loads`);
      let submissions = 0; page.on('request', request => { if (request.method() === 'POST') submissions++; });
      const input = page.locator('#password'), toggle = page.locator('.pw-toggle[data-target="password"]');
      check(await input.getAttribute('type') === 'password' && await input.inputValue() === '' && await toggle.getAttribute('type') === 'button', `${role}/${width}: password starts blank and masked, toggle cannot submit`);
      await input.fill('Only-In-Memory!A'); await toggle.click();
      check(await input.getAttribute('type') === 'text' && await toggle.getAttribute('aria-pressed') === 'true' && await toggle.getAttribute('aria-label') === 'Ẩn mật khẩu', `${role}/${width}: reveal state and accessibility labels`);
      await toggle.press('Space');
      check(await input.getAttribute('type') === 'password' && await input.inputValue() === 'Only-In-Memory!A', `${role}/${width}: keyboard hides without losing value`);
      await toggle.press('Enter'); await toggle.press('Enter');
      check(await input.getAttribute('type') === 'password' && submissions === 0, `${role}/${width}: Enter toggles without POST`);
      const geometry = await toggle.evaluate(button => { const input = document.getElementById(button.dataset.target), b = button.getBoundingClientRect(), r = input.getBoundingClientRect(); return { padding: parseFloat(getComputedStyle(input).paddingRight), width: b.width, fits: b.left >= r.left && b.right <= r.right + 1 }; });
      check(geometry.fits && geometry.padding >= geometry.width && await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${role}/${width}: eye icon never overlaps input text or breaks layout`);
      check(await page.evaluate(() => ![...Object.values(localStorage), ...Object.values(sessionStorage)].some(value => value.includes('Only-In-Memory!A'))), `${role}/${width}: password not persisted in browser storage`);
      await input.fill(''); await input.scrollIntoViewIfNeeded();
      await page.screenshot({ path: `${out}/password-${area}-${width}.png` });
      await page.reload(); check(await input.inputValue() === '', `${role}/${width}: no saved password/hash rendered on reload`);
    }
    await context.close();
  }
})().catch(error => { results.push({ pass: false, label: error.stack }); process.exitCode = 1; }).finally(async () => {
  if (browser) await browser.close();
  for (const { server, log, fixture } of servers) {
    server.kill(); fs.writeFileSync(`${out}/${fixture}.log`, log);
    results.push({ pass: !/PHP (?:Warning|Fatal error|Parse error)/.test(log), label: fixture + ': no PHP warnings or fatal errors' });
  }
  fs.writeFileSync(out + '/browser-results.json', JSON.stringify(results, null, 2));
  console.log(`${results.filter(row => row.pass).length} PASS / ${results.filter(row => !row.pass).length} FAIL; ${out}`);
  results.filter(row => !row.pass).forEach(row => console.error(row.label));
  if (results.some(row => !row.pass)) process.exitCode = 1;
});
