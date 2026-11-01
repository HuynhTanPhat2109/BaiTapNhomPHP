// Real Chrome DOM/AJAX regression test, with PHP-rendered fixtures; no database.
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const chrome = process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const html = execFileSync(php, ['-r', `
require 'vendor/autoload.php';
$_ENV['APP_BASE_PATH']=''; $_SESSION=[];
$error=null; $key=str_repeat('a',64); $bookingBackUrl='/';
$user=['ten_khach_hang'=>'Test customer','so_dien_thoai_khach'=>'0901234567'];
if (getenv('BOOKING_TEST_GUEST')) $user=null;
$draft=['input'=>['date'=>'2099-01-01'],'extras'=>['note'=>'','services'=>[]]];
$quote=['fingerprint'=>str_repeat('b',64),'facility'=>['ten_co_so'=>'Test facility','dia_chi'=>'Test','gio_mo_cua'=>'05:00','gio_dong_cua'=>'23:00'],
'lines'=>[['name'=>'Court A','sport'=>'Test sport','start'=>'10:00','end'=>'10:30','minutes'=>30,'amount'=>'40000.00']],
'court_total'=>'40000.00','service_total'=>'0.00','total'=>'40000.00','minutes'=>30,'services'=>[],
'offers'=>[1=>['purchasable'=>true,'available'=>5,'ten_dich_vu'=>'Water','don_gia'=>'10000.00','don_vi_tinh'=>'Bottle']]];
$quote['offers'][2]=array_replace($quote['offers'][1],['ten_dich_vu'=>'Second drink','don_gia'=>'15000.00']);
require 'app/views/courts/booking-details.php';
`], {encoding:'utf8'}).replace(/<link[^>]+>|<script[^>]+><\/script>/g, '');
const test = async () => {
    const results=[];
    const check=(ok,label)=>{if(!ok) throw new Error(label);results.push(label);};
    const sleep=ms=>new Promise(r=>setTimeout(r,ms));
    const form=document.querySelector('[data-booking-form]');
    const note=form.querySelector('textarea');
    const original=form.outerHTML;
    let mode='ok', calls=0, delayed, lastNote;
    const input=()=>form.querySelector('input[type=number]');
    const confirm=()=>form.querySelector('[data-confirm-booking]') || {disabled:true};
    const feedback=()=>form.querySelector('[data-booking-feedback]');
    const preview=()=>form.querySelector('[data-selected-services]').textContent;
    const response=(quantity, noteValue, second=0)=>{
        const doc=new DOMParser().parseFromString(original,'text/html');
        doc.querySelector('input[type=number]').setAttribute('value',quantity);
        doc.querySelector('textarea').textContent=noteValue;
        doc.querySelector('[data-service-amount]').textContent=(quantity*12000)+' TEST';
        doc.querySelector('[name="services[2]"]').setAttribute('value',second);
        doc.querySelector('[data-service-row="2"] [data-service-amount]').textContent=(second*15000)+' TEST';
        doc.querySelector('.booking-service-name small').textContent='12000 TEST / Bottle';
        doc.querySelector('.booking-total').textContent=(40000+quantity*12000+second*15000)+' TEST';
        doc.querySelector('.booking-schedule-price strong').textContent='40000 TEST';
        doc.querySelector('[name=quote]').value='quote-'+quantity;
        return {ok:true,text:async()=>doc.body.innerHTML};
    };
    window.fetch=async (url,options)=>{
        if (typeof url !== 'string' || !url.includes('route=%2Fbooking%2Freview')) throw new Error('Wrong AJAX URL: '+String(url));
        if(options.method!=='POST'||options.body.get('_token')?.length!==64||options.body.get('draft')?.length!==64)throw new Error('Invalid review request contract');
        calls++;lastNote=options.body.get('note');
        const quantity=Number(options.body.get('services[1]'));
        if(mode==='network')throw new Error('Test network error');
        if(mode==='conflict')return {ok:false,text:async()=>'<p class="booking-error">Test stock conflict</p>'};
        if(mode==='csrf')return {ok:false,status:403,text:async()=>'<h1>Forbidden</h1>'};
        if(mode==='invalid')return {ok:true,status:200,text:async()=>'<h1>Wrong response</h1>'};
        if(mode==='delay')return new Promise(resolve=>{delayed=()=>resolve(response(quantity,lastNote));});
        return response(quantity,lastNote,Number(options.body.get('services[2]')));
    };
    const edit=async(quantity)=>{input().value=quantity;input().dispatchEvent(new Event('input',{bubbles:true}));await sleep(450);};
    try {
        check(!form.querySelector('.booking-summary').contains(note),'note outside replaced summary');
        await edit(1);
        check(preview().includes('12000 TEST') && form.querySelector('.booking-total').textContent==='52000 TEST','backend amounts update preview and total');
        check(form.querySelector('.booking-service-name small').textContent.includes('12000 TEST'),'backend service unit price refreshed');
        check(form.querySelector('textarea')===note,'note DOM survives AJAX');
        note.value='first note';note.dispatchEvent(new Event('input',{bubbles:true}));await sleep(450);
        check(lastNote==='first note','note listener after first AJAX');
        note.value='second note';note.dispatchEvent(new Event('input',{bubbles:true}));await sleep(450);
        check(lastNote==='second note','note listener after repeated AJAX');
        form.querySelector('[data-quantity="1"]').click();await sleep(450);
        check(preview().includes('24000 TEST'),'delegated increment after row refresh');
        form.querySelector('[data-quantity="-1"]').click();await sleep(450);
        check(preview().includes('12000 TEST'),'delegated decrement after row refresh');
        const second=form.querySelector('[name="services[2]"]');second.value='1';second.dispatchEvent(new Event('input',{bubbles:true}));await sleep(450);
        check(preview().includes('12000 TEST')&&preview().includes('15000 TEST')&&form.querySelector('.booking-total').textContent==='67000 TEST','two services update individual amounts and total');
        form.querySelector('[data-service-row="2"] [data-remove-service]').click();await sleep(450);
        check(form.querySelector('.booking-total').textContent==='52000 TEST','removing second service preserves first');
        form.querySelector('[data-remove-service]').click();await sleep(450);
        check(!form.querySelector('.booking-selected-list')&&form.querySelector('.booking-total').textContent==='40000 TEST','remove updates preview and total');
        mode='network';await edit(1);
        check(!feedback().hidden&&feedback().textContent.includes('Test network error')&&confirm().disabled,'network failure visible; confirmation blocked');
        check(!preview().includes('Đang cập nhật')&&preview().includes('Chưa cập nhật được'),'failed AJAX ends loading state');
        mode='csrf';await edit(1);
        check(feedback().textContent.includes('CSRF')&&confirm().disabled,'HTTP 403 feedback');
        mode='invalid';await edit(1);
        check(feedback().textContent.includes('HTTP 200')&&confirm().disabled,'invalid HTML response rejected');
        mode='conflict';await edit(2);
        check(feedback().textContent.includes('Test stock conflict')&&confirm().disabled,'server conflict visible; confirmation blocked');
        mode='delay';await edit(1);
        check(typeof delayed==='function','delayed response started');
        mode='ok';await edit(3);delayed();await sleep(50);
        check(preview().includes('36000 TEST')&&form.querySelector('.booking-total').textContent==='76000 TEST'&&form.elements.quote.value==='quote-3','stale response cannot overwrite newest quote');
        check((!form.querySelector('[data-confirm-booking]')||!confirm().disabled)&&form.elements._token.value.length===64,'confirmation restored and CSRF preserved');
        document.querySelector('#result').textContent=JSON.stringify({pass:results.length,results,calls});
    } catch(error) {document.querySelector('#result').textContent=JSON.stringify({fail:error.message,feedback:feedback()?.textContent,results});}
};
const dir=fs.mkdtempSync(path.resolve('storage/temp/booking-review-'));
const file=path.join(dir,'test.html');
fs.writeFileSync(file,'<!doctype html><meta charset="utf-8">'+html+'<pre id="result"></pre><script>'+fs.readFileSync('public/assets/js/booking.js','utf8')+'</script><script>('+test.toString()+')()</script>');
const output=execFileSync(chrome,['--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--user-data-dir='+path.join(dir,'profile'),'--virtual-time-budget=12000','--dump-dom','file:///'+file.replaceAll('\\','/')],{encoding:'utf8',timeout:45000,stdio:['ignore','pipe','ignore']});
const match=output.match(/<pre id="result">([^<]+)<\/pre>/);
assert(match,'Browser did not finish regression checks');
const result=JSON.parse(match[1].replaceAll('&amp;','&').replaceAll('&gt;','>').replaceAll('&lt;','<'));
assert(!result.fail,JSON.stringify(result));
console.log('PASS: '+result.pass+' browser booking review checks (mock HTTP, real PHP view/JS, no database).');
console.log(result.results.join('\n'));
