const {chromium}=require(process.env.PLAYWRIGHT_PATH||'D:/KiemThuPM/SOT357-65134364/node_modules/playwright-core');
const fs=require('node:fs'),path=require('node:path');
if(!/^stryke_employee_test_[a-f0-9]{10}$/.test(process.env.STRYKE_EMPLOYEE_TEST_DB||''))throw Error('Run via employee-scope-integration.php --run');
const out=path.resolve('storage/temp/dashboard-employee-review'),ids=JSON.parse(fs.readFileSync(out+'/employee-fixture-ids.json','utf8'));
const base=process.env.STRYKE_EMPLOYEE_TEST_URL,url=(route,query={})=>base+'/index.php?'+new URLSearchParams({route,...query});
const results=[],errors=[];let browser;
const check=(ok,label)=>{results.push({pass:!!ok,label});if(!ok)throw Error(label);};
(async()=>{try{
 browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_BINARY||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const login=async(username)=>{
  const context=await browser.newContext({reducedMotion:'reduce'}),page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  page.on('console',message=>{if(message.type()==='error'&&/Content Security Policy|Refused to/.test(message.text()))errors.push(message.text());});
  await page.goto(url('/login'));await page.locator('[name=username]').fill(username);await page.locator('[name=password]').fill(process.env.STRYKE_EMPLOYEE_TEST_PASSWORD);
  await Promise.all([page.waitForNavigation(),page.locator('form button[type=submit]').click()]);check(!page.url().includes('route=%2Flogin'),username+': actual login succeeds');return {context,page};
 };
 const csrf=async(page)=>page.locator('[name=_token]').first().inputValue();
 const data=(name,facility)=>({ho_ten:name,ma_co_so:String(facility),ngay_sinh:'',gioi_tinh:'',so_dien_thoai:'0900000000',email:'',dia_chi:'',chuc_vu:'',ngay_vao_lam:'',trang_thai:'DANG_LAM'});
 const admin=await login('scope.admin');
 await admin.page.goto(url('/admin/employees',{q:'TEST Foreign Staff B'}));check((await admin.page.locator('tbody').innerText()).includes('TEST Foreign Staff B'),'Admin sees personnel across companies');await admin.context.close();
 const company=await login('scope.company');
 await company.page.goto(url('/company/employees',{per_page:'50'}));let body=await company.page.locator('tbody').innerText();
 check(body.includes('TEST Staff X')&&body.includes('TEST Staff Y')&&body.includes('TEST Company Manager')&&!body.includes('TEST Foreign Staff B'),'company sees all own facilities and managers, without foreign employees');
 check(body.includes('TEST Company A')&&body.includes('TEST Facility X')&&body.includes('TEST Facility Y'),'list displays company/facility names');
 for(const action of ['show','edit'])check((await company.context.request.get(url('/company/employees/'+action,{id:ids.staffB}))).status()===404,'company rejects foreign '+action+' URL');
 let token=await csrf(company.page);
 check((await company.context.request.post(url('/company/employees/update'),{form:{...data('FORGED FOREIGN',ids.z),id:String(ids.staffB),_token:token},maxRedirects:0})).status()===404,'company cannot update a foreign employee');
 check((await company.context.request.get(url('/company/employees/edit',{id:ids.manager}))).status()===403,'company cannot edit own/peer company manager');
 check((await company.context.request.post(url('/company/employees/status'),{form:{_token:token,id:String(ids.manager),trang_thai:'DA_NGHI'},maxRedirects:0})).status()===403,'company cannot deactivate own/peer company manager');
 await company.page.goto(url('/company/employees',{q:'scope.staffx'}));check(await company.page.locator('tbody tr').count()===1,'search includes account username');
 await company.page.goto(url('/company/employees',{ma_co_so:String(ids.y)}));body=await company.page.locator('tbody').innerText();check(body.includes('TEST Staff Y')&&!body.includes('TEST Staff X'),'company facility filter works');
 await company.page.goto(url('/company/employees',{q:'TEST Facility Y'}));check((await company.page.locator('tbody').innerText()).includes('TEST Staff Y'),'search includes facility name');
 await company.page.goto(url('/company/employees/create'));token=await csrf(company.page);
 check((await company.context.request.post(url('/company/employees'),{form:{...data('COMPANY NEW',ids.y),_token:token},maxRedirects:0})).status()===303,'company creates an employee in an own facility');
 check((await company.context.request.post(url('/company/employees'),{form:{...data('FORGED ASSIGNMENT',ids.z),_token:token},maxRedirects:0})).status()===422,'company cannot assign a foreign facility');
 check((await company.context.request.post(url('/company/employees'),{form:{...data('FORGED ADMIN',ids.x),_token:token,cap_tai_khoan:'1',username:'forged.admin',password:process.env.STRYKE_EMPLOYEE_TEST_PASSWORD,role:'QUAN_TRI_TAP_DOAN'},maxRedirects:0})).status()===422,'company cannot issue Admin');
 check((await company.context.request.get(url('/company/employees/show',{id:ids.manager}))).status()===200,'company can read its manager profile without gaining write permission');
 check((await company.context.request.post(url('/company/employees/update'),{form:{...data('TEST Staff Y',ids.x),id:String(ids.staffY),_token:token,ma_cong_ty:String(ids.companyB),role:'QUAN_TRI_TAP_DOAN'},maxRedirects:0})).status()===303,'company can reassign own staff within company; forged company/role fields grant no authority');
 check((await company.context.request.post(url('/company/employees/update'),{form:{...data('TEST Staff Y',ids.z),id:String(ids.staffY),_token:token},maxRedirects:0})).status()===422,'company cannot move an employee into another company facility');
 check((await company.context.request.post(url('/company/employees/update'),{form:{...data('TEST Staff Y',ids.y),id:String(ids.staffY),_token:token},maxRedirects:0})).status()===303,'company can restore an own facility assignment');
 const facility=await login('scope.facility');
 for(const [actor,prefix,label] of [[company,'/company','company'],[facility,'/facility','facility']]){
  for(const width of [1440,1024,768,375]){
   await actor.page.setViewportSize({width,height:900});await actor.page.goto(url(prefix+'/employees'));await actor.page.evaluate(()=>document.fonts.ready);
   check(await actor.page.locator('.admin-sidebar [aria-current=page]').count()===1,label+'/'+width+': one active employee menu');
   check(await actor.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),label+'/'+width+': table scrolls inside content without page overflow');
   await actor.page.screenshot({path:out+'/'+label+'-employees-'+width+'.png',fullPage:true});
   if(width>=1024){await actor.page.locator('[data-sidebar-collapse]').click();check(await actor.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),label+'/'+width+': list adapts to compact sidebar');await actor.page.locator('[data-sidebar-collapse]').click();}
   else{await actor.page.locator('#admin-menu-toggle').click();check(await actor.page.locator('#admin-sidebar').getAttribute('aria-modal')==='true',label+'/'+width+': mobile employee navigation opens');await actor.page.locator('#admin-sidebar-close').click();}
   await actor.page.goto(url(prefix+'/employees/create'));await actor.page.evaluate(()=>document.fonts.ready);
   check(await actor.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),label+'/'+width+': form fits viewport');
   await actor.page.screenshot({path:out+'/'+label+'-create-'+width+'.png',fullPage:true});
   for(const action of ['edit','show']){
    const response=await actor.page.goto(url(prefix+'/employees/'+action,{id:ids.staffX}));await actor.page.evaluate(()=>document.fonts.ready);
    check(response.status()===200&&await actor.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),label+'/'+width+': '+action+' fits viewport and remains accessible');
    await actor.page.screenshot({path:out+'/'+label+'-'+action+'-'+width+'.png',fullPage:true});
   }
  }
 }
 await facility.page.goto(url('/facility/employees',{per_page:'50'}));body=await facility.page.locator('tbody').innerText();
 check(body.includes('TEST Staff X')&&!body.includes('TEST Staff Y')&&!body.includes('TEST Foreign Staff B'),'facility sees only its assigned facility');
 for(const id of [ids.staffY,ids.staffB])for(const action of ['show','edit'])check((await facility.context.request.get(url('/facility/employees/'+action,{id}))).status()===404,'facility rejects out-of-scope '+action+' #'+id);
 check((await facility.context.request.get(url('/facility/employees',{ma_co_so:ids.y}))).status()===403,'facility cannot forge the list filter');
 check((await facility.context.request.get(url('/facility/employees/edit',{id:ids.facilityManager}))).status()===403,'facility cannot edit itself or another manager');
 for(const prefix of ['/admin','/company'])check((await facility.context.request.get(url(prefix+'/employees'))).status()===403,'facility cannot bypass its role using '+prefix+' employee routes');
 await facility.page.goto(url('/facility/employees/create'));token=await csrf(facility.page);
 check(await facility.page.locator('select[name=ma_co_so]').isDisabled()&&await facility.page.locator('select[name=role] option[value=QUAN_LY_CO_SO]').count()===0,'facility form locks assignment and offers no manager role');
 for(const role of ['QUAN_TRI_TAP_DOAN','QUAN_LY_CONG_TY','QUAN_LY_CO_SO'])check((await facility.context.request.post(url('/facility/employees'),{form:{...data('FORGED HIGHER ROLE',ids.x),_token:token,cap_tai_khoan:'1',username:'forged.role',password:process.env.STRYKE_EMPLOYEE_TEST_PASSWORD,role},maxRedirects:0})).status()===422,'facility backend rejects issuing '+role);
 check((await facility.context.request.post(url('/facility/employees'),{form:{...data('FORGED OTHER FACILITY',ids.y),_token:token},maxRedirects:0})).status()===403,'facility cannot create in a sibling facility');
 const createData=data('CREATED BY FACILITY',ids.x);delete createData.ma_co_so;
 check((await facility.context.request.post(url('/facility/employees'),{form:{...createData,_token:token,cap_tai_khoan:'1',username:'scope.newstaff',password:process.env.STRYKE_EMPLOYEE_TEST_PASSWORD,role:'NHAN_VIEN'},maxRedirects:0})).status()===303,'missing client facility is safely bound by server');
 check((await facility.context.request.post(url('/facility/employees/update'),{form:{...data('FORGED SIBLING UPDATE',ids.x),_token:token,id:String(ids.staffY)},maxRedirects:0})).status()===404,'facility cannot update a sibling employee');
 check((await facility.context.request.post(url('/facility/employees/status'),{form:{_token:token,id:String(ids.facilityManager),trang_thai:'DA_NGHI'},maxRedirects:0})).status()===403,'facility cannot disable its manager');
 check((await facility.context.request.post(url('/facility/employees'),{form:{...data('CSRF ATTACK',ids.x),_token:'invalid'},maxRedirects:0})).status()===403,'CSRF still protects employee writes');
 const filtered={q:'PAGE STAFF',page:'2',per_page:'10',sort:'name_asc'};
 await facility.page.setViewportSize({width:1440,height:900});await facility.page.goto(url('/facility/employees',filtered));
 check(await facility.page.locator('tbody tr').count()===10,'backend pagination has ten rows on filtered page two');
 await facility.page.locator('tbody .admin-table-link').first().click();
 check(new URL(await facility.page.locator('.detail-back, .detail-back-link, .admin-back-link').first().getAttribute('href'),facility.page.url()).searchParams.get('page')==='2','detail Back preserves page and filters');
 await facility.page.goto(url('/facility/employees',filtered));
 await facility.page.locator('tbody .list-action--edit').first().click();
 await facility.page.locator('[name=chuc_vu]').fill('Scope UX test');await facility.page.locator('[name=email]').fill('invalid-email');
 const invalidResponse=facility.page.waitForResponse(response=>response.request().method()==='POST');
 await facility.page.locator('form.module-form').evaluate(form=>{form.noValidate=true;form.requestSubmit();});
 check((await invalidResponse).status()===422,'server validation rejects malformed form data');await facility.page.waitForLoadState();
 check(await facility.page.locator('[name=chuc_vu]').inputValue()==='Scope UX test'&&await facility.page.locator('[name=email]').inputValue()==='invalid-email','validation keeps submitted non-secret form values');
 check(new URL(await facility.page.locator('.admin-back-link').first().getAttribute('href'),facility.page.url()).searchParams.get('page')==='2','validation keeps filtered list return context');
 await facility.page.locator('[name=email]').fill('');await Promise.all([facility.page.waitForNavigation(),facility.page.locator('form.module-form button[type=submit]').click()]);
 const returned=new URL(facility.page.url());check(returned.searchParams.get('page')==='2'&&returned.searchParams.get('q')==='PAGE STAFF'&&returned.searchParams.get('sort')==='name_asc','save returns to filtered page two');
 check((await facility.page.locator('body').innerText()).includes('Đã cập nhật nhân viên'),'success notice survives redirect');
 await facility.page.locator('tbody .list-action--stop').first().click();check(await facility.page.locator('#workspace-confirm').isVisible(),'status change still requires confirmation');
 await Promise.all([facility.page.waitForNavigation(),facility.page.locator('[data-confirm-accept]').click()]);
 check(new URL(facility.page.url()).searchParams.get('page')==='2'&&await facility.page.locator('tbody .list-action--activate').count()===1,'status change updates the record and returns to filtered page two');
 await facility.page.locator('tbody .list-action--activate').first().click();await Promise.all([facility.page.waitForNavigation(),facility.page.locator('[data-confirm-accept]').click()]);
 check(await facility.page.locator('tbody .list-action--activate').count()===0,'facility can reactivate its own employee');
 await facility.page.locator('.list-toolbar__actions a').click();check(new URL(facility.page.url()).searchParams.get('q')===null,'Reset clears filters');
 for(const size of ['20','50']){await facility.page.goto(url('/facility/employees',{q:'PAGE STAFF',per_page:size}));check(await facility.page.locator('tbody tr').count()===(size==='20'?20:25),'backend page size '+size);}
 const staff=await login('scope.staffx');await staff.page.goto(url('/staff'));token=await csrf(staff.page);
 for(const prefix of ['/admin','/company','/facility']){
  for(const action of ['','/show','/create','/edit'])check((await staff.context.request.get(url(prefix+'/employees'+action,{id:ids.staffX}))).status()===403,'staff denied GET '+prefix+'/employees'+action);
  for(const action of ['','/update','/status'])check((await staff.context.request.post(url(prefix+'/employees'+action),{form:{...data('FORGED STAFF',ids.x),id:String(ids.staffX),_token:token},maxRedirects:0})).status()===403,'staff denied POST '+prefix+'/employees'+action);
 }
 check(await staff.page.locator('.admin-sidebar a[href*="employees"]').count()===0,'ordinary staff has no employee menu');
 const customer=await login('scope.customer');check((await customer.context.request.get(url('/facility/employees'))).status()===403,'customer cannot access employee management');
 check(errors.length===0,'no JavaScript runtime exceptions or CSP violations');
 for(const [actor,prefix,label] of [[company,'/company','company'],[facility,'/facility','facility'],[staff,'/staff','staff']]){
  for(const width of [1440,1024,768,375]){
   await actor.page.setViewportSize({width,height:900});
   const response=await actor.page.goto(url(prefix==='/company'?'/company/profile':prefix+'/co-so'));
   await actor.page.evaluate(()=>document.fonts.ready);
   check(response.status()===200&&await actor.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),label+'/'+width+': scoped organization profile fits viewport');
   const content=await actor.page.locator('.admin-workspace').innerText();
   check(content.includes('TEST Company A')&&!content.includes('TEST Company B')&&!content.includes('TEST Facility Z'),label+'/'+width+': profile contains only the assigned company');
   check(await actor.page.locator('.admin-workspace .list-toolbar').count()===0,label+'/'+width+': organization profile has no list filters');
   if(label==='company')check(content.includes('TEST Facility X')&&content.includes('TEST Facility Y')&&await actor.page.locator('a.list-action--view').count()===2,'company profile shows all own facilities and detail links');
   else check(content.includes('TEST Facility X')&&!content.includes('TEST Facility Y')&&await actor.page.locator('.admin-workspace a[href*="co-so/create"]').count()===0,label+': direct assigned facility profile has no create');
   if(label==='staff')check(await actor.page.locator('.admin-workspace a[href*="/edit"],.admin-workspace a[href*="/create"],.admin-workspace a[href*="/delete"]').count()===0,'staff organization profile is read-only');
   await actor.page.screenshot({path:out+'/'+label+'-profile-'+width+'.png',fullPage:true});
  }
  if(label!=='company')for(const action of ['','/show'])check((await actor.context.request.get(url(prefix+'/co-so'+action,{id:ids.y}))).status()===403,label+': forged sibling profile URL '+action+' is blocked');
 }
 for(const actor of [company,facility,staff,customer])await actor.context.close();
}catch(error){if(results.every(row=>row.pass))results.push({pass:false,label:error.message});throw error;}
finally{if(browser)await browser.close();fs.writeFileSync(out+'/employee-browser-results.json',JSON.stringify({results,errors},null,2));console.log(`${results.filter(r=>r.pass).length} PASS / ${results.filter(r=>!r.pass).length} FAIL`);}})().catch(e=>{console.error(e);process.exitCode=1;});
