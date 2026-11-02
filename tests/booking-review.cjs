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
    const dialog=form.querySelector('[data-service-dialog]');
    const open=()=>form.querySelector('[data-open-services]').click();
    const apply=async()=>{form.querySelector('[data-apply-services]').click();await sleep(30);};
    const edit=(id,qty)=>{const node=form.querySelector('[name="services['+id+']"]');node.value=qty;node.dispatchEvent(new Event('input',{bubbles:true}));};
    const total=()=>form.querySelector('.booking-total').textContent;
    const remove=async id=>{form.querySelector('[data-remove-applied="'+id+'"]').click();await sleep(30);};
    try {
        check(!form.querySelector('.booking-summary').contains(note),'note outside replaced summary');
        const initialTotal=total(),initialPreview=preview();
        open();edit(1,1);edit(2,2);
        form.querySelector('[data-quantity="1"]').click();
        form.querySelector('[data-quantity="-1"]').click();
        await sleep(450);
        check(calls===0&&total()===initialTotal&&preview()===initialPreview,'modal edits do not request or change applied display');
        check(!new FormData(form).has('services[1]'),'unapplied edits excluded from submit data');
        form.querySelector('[data-close-services]').click();open();
        check(input().value==='0','closing X discards temporary quantities');
        edit(1,2);dialog.dispatchEvent(new Event('cancel',{cancelable:true}));open();
        check(input().value==='0','Escape cancel discards edits');
        edit(1,1);edit(2,1);await apply();
        check(calls===1&&!dialog.open&&preview().includes('12000 TEST')&&preview().includes('15000 TEST')&&total()==='67000 TEST','apply uses authoritative backend amounts for both services');
        check(form.querySelector('.booking-service-name small').textContent.includes('12000 TEST'),'backend unit price refreshed');
        check(form.querySelector('textarea')===note,'note DOM survives update');
        open();check(input().value==='1','reopen restores applied quantity');
        await apply();check(calls===1,'unchanged apply avoids redundant request');
        open();edit(1,3);await apply();
        check(total()==='91000 TEST'&&preview().includes('36000 TEST'),'quantity update applied');
        await remove(1);
        check(total()==='55000 TEST'&&!form.querySelector('[data-remove-applied="1"]')&&input().value==='0','external delete updates amount and resets modal quantity');
        await remove(2);
        check(total()==='40000 TEST'&&!form.querySelector('.booking-selected-list'),'delete last service returns server zero-service quote');
        open();edit(1,1);mode='network';await apply();
        check(dialog.open&&total()==='40000 TEST'&&!form.querySelector('.booking-selected-list')&&!feedback().hidden&&confirm().disabled,'failed apply preserves accepted display and blocks confirmation');
        check(!dialog.querySelector('[data-service-feedback]').hidden&&input().value==='1','failure visible in modal and candidate retained for retry');
        mode='ok';await apply();
        check(total()==='52000 TEST'&&!dialog.open,'retry succeeds');
        for(const failure of ['network','csrf','invalid','conflict']) {
            mode=failure;await remove(1);
            check(total()==='52000 TEST'&&form.querySelector('[data-remove-applied="1"]')&&input().value==='1'&&!feedback().hidden,'failed external delete preserved selection: '+failure);
        }
        mode='ok';await remove(1);
        check(total()==='40000 TEST','external delete retry succeeds');
        open();edit(1,2);mode='delay';const before=calls;await apply();
        form.querySelector('[data-apply-services]').click();form.querySelector('[data-quantity="1"]').click();
        check(calls===before+1&&input().value==='2','rapid clicks while pending produce one request');
        check(total()==='40000 TEST','pending request does not show optimistic payment');
        delayed();await sleep(30);mode='ok';
        check(total()==='64000 TEST'&&form.elements.quote.value==='quote-2','accepted quantities and fingerprint synchronized');
        const payload=new FormData(form);
        check(payload.get('services[1]')==='2'&&!payload.has('services[2]')&&payload.get('quote')==='quote-2','confirmation sends applied services and fingerprint');
        note.value='first note';note.dispatchEvent(new Event('input',{bubbles:true}));await sleep(450);
        check(lastNote==='first note'&&total()==='64000 TEST','note listener remains active with applied services');
        note.value='second note';note.dispatchEvent(new Event('input',{bubbles:true}));open();edit(1,4);await sleep(450);
        check(lastNote==='first note','pending note update cannot overwrite modal draft');
        form.querySelector('[data-close-services]').click();await sleep(30);
        check(lastNote==='second note'&&input().value==='2'&&total()==='64000 TEST','close discards modal changes while saving pending note');
        check((!form.querySelector('[data-confirm-booking]')||!confirm().disabled)&&form.elements._token.value.length===64,'confirmation enabled with CSRF preserved');
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
