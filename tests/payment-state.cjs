// Real polling code with deterministic DOM/HTTP fixtures; no database.
const fs=require('node:fs'), vm=require('node:vm'), assert=require('node:assert/strict');
function page(state,payment,receipt,expires='2026-11-02 10:06:00') {
    const nodes=Object.fromEntries(['data-countdown','data-wait-message','data-payment-expired','data-payment-reconcile','.stryke-bottom-bar','.stryke-countdown-banner']
        .map(k=>[k,{hidden:true,textContent:''}]));
    const heading={textContent:''},description={textContent:''};
    const result={hidden:true,querySelector:s=>s==='[data-result-title]'?heading:description};
    nodes['data-payment-result']=result;
    const pending={hidden:false},timers=new Map(),calls=[];
    let timerId=0,response={state,payment_status:payment,receipt_submitted:receipt,now:'2026-11-02T10:01:00+07:00'};
    const waiting={dataset:{now:'2026-11-02T10:00:00+07:00',expires,state,statusUrl:'/status',expireUrl:'/expire',bookingId:'1',paymentStatus:payment,receiptSubmitted:receipt?'1':'0'},
        querySelector:s=>nodes[s.replace(/^\[|\]$/g,'')]||null,querySelectorAll:()=>[pending]};
    vm.runInNewContext(fs.readFileSync('public/assets/js/booking.js','utf8'),{
        document:{querySelector:s=>s==='[data-booking-wait]'?waiting:(s==='input[name="_token"]'?{value:'test-csrf'}:null),addEventListener:()=>{}},
        performance:{now:()=>0},Date,FormData,
        setInterval:(fn,ms)=>{timers.set(++timerId,{fn,ms});return timerId;},clearInterval:id=>timers.delete(id),
        fetch:async(url,options)=>{calls.push({url,options});return {ok:true,json:async()=>response};}
    });
    return {result,heading,pending,nodes,timers,calls,set:value=>{response={...response,...value};},poll:()=>[...timers.values()].find(t=>t.ms===5000).fn()};
}
(async()=>{
    const p=page('CHO_THANH_TOAN','CHO_THANH_TOAN',false);
    assert(!p.pending.hidden);
    p.set({payment_status:'THANH_CONG'});await p.poll();
    assert(p.result.hidden&&p.pending.hidden,'recorded payment alone never confirms or offers more payment');
    p.set({state:'CHO_XAC_NHAN',payment_status:'CHO_THANH_TOAN',receipt_submitted:true});await p.poll();
    assert(!p.result.hidden&&p.heading.textContent.includes('biên lai'));
    assert(p.calls.every(c=>c.url!=='/expire'));
    p.set({state:'DA_XAC_NHAN',payment_status:'THANH_CONG'});await p.poll();
    assert(p.heading.textContent==='Thanh toán đã thành công!'&&!p.result.hidden);
    assert.equal(p.timers.size,0);
    for(const state of ['HET_HAN','DA_HUY']){
        const ended=page(state,'CHO_THANH_TOAN',true);
        assert(ended.result.hidden&&!ended.nodes['data-payment-expired'].hidden,'old receipt cannot revive '+state);
        assert.equal(ended.calls.length,0);
    }
    const review=page('CHO_XAC_NHAN','CHO_THANH_TOAN',true,'2026-10-01 10:06:00');
    assert(!review.result.hidden&&review.calls.length===0,'overdue receipt review never expires');
    const expiry=page('CHO_THANH_TOAN','CHO_THANH_TOAN',false,'2026-10-01 10:06:00');
    assert.equal(expiry.calls[0].url,'/expire');assert.equal(expiry.calls[0].options.body.get('_token'),'test-csrf');
    const missing=page('DA_XAC_NHAN','CHO_THANH_TOAN',false);
    assert(missing.result.hidden,'booking alone cannot assert verified payment');
    console.log('PASS: 15 payment/receipt polling assertions.');
})().catch(e=>{console.error(e);process.exitCode=1;});
