// Real booking polling code, deterministic DOM/HTTP fixtures; no database or network.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const nodes = Object.fromEntries(['data-countdown','data-wait-message','data-payment-success','data-payment-expired','.stryke-bottom-bar']
    .map(key => [key, {hidden:true, textContent:''}]));
const pending = {hidden:false};
const timers = new Map();
let timerId = 0, calls = 0;
let response = {state:'CHO_THANH_TOAN',payment_status:'THANH_CONG',now:'2026-11-02T10:01:00+07:00'};
const waiting = {
    dataset:{now:'2026-11-02T10:00:00+07:00',expires:'2026-11-02 10:06:00',state:'CHO_THANH_TOAN',statusUrl:'/status'},
    querySelector:selector => nodes[selector.replace(/^\[|\]$/g,'')] || null,
    querySelectorAll:() => [pending]
};
vm.runInNewContext(fs.readFileSync('public/assets/js/booking.js','utf8'), {
    document:{querySelector:selector => selector==='[data-booking-wait]'?waiting:null,addEventListener:()=>{}},
    performance:{now:()=>0},Date,
    setInterval:(fn,ms)=>{timers.set(++timerId,{fn,ms});return timerId;},
    clearInterval:id=>timers.delete(id),
    fetch:async()=>{calls++;return {ok:true,json:async()=>response};}
});
(async()=>{
    const poll=[...timers.values()].find(timer=>timer.ms===5000).fn;
    await poll();
    assert(nodes['data-payment-success'].hidden,'payment success alone must not confirm booking');
    assert(!pending.hidden,'pending booking remains pending');
    assert([...timers.values()].some(timer=>timer.ms===5000),'polling must continue');
    response={...response,state:'DA_XAC_NHAN'};
    await poll();
    assert(!nodes['data-payment-success'].hidden,'server booking confirmation shows success');
    assert(pending.hidden);
    assert.equal(timers.size,0,'confirmed booking stops timers');
    assert.equal(calls,2);
    console.log('PASS: 7 payment/booking state polling assertions; no fake confirmation.');
})().catch(error=>{console.error(error);process.exitCode=1;});
