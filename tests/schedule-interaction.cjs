// Actual UI script with a small DOM fixture. HTTP prices are tested against real PHP/MySQL separately.
const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
class Element {
    constructor(data={}) {
        this.dataset=data; this.disabled=false; this.textContent=''; this.children=[]; this.events={}; this.classes=new Set();
        this.classList={contains:x=>this.classes.has(x),add:(...xs)=>xs.forEach(x=>this.classes.add(x)),remove:(...xs)=>xs.forEach(x=>this.classes.delete(x)),toggle:(x,on)=>on?this.classes.add(x):this.classes.delete(x)};
    }
    addEventListener(name,fn){this.events[name]=fn;}
    setAttribute(name,value){this[name]=value;}
    click(){this.events.click?.();}
    appendChild(node){node.parent=this;this.children.push(node);}
    remove(){this.parent.children=this.parent.children.filter(n=>n!==this);}
    querySelectorAll(selector){return this.children.filter(n=>n.className===selector.slice(1));}
    querySelector(selector){return this.querySelectorAll(selector)[0]||null;}
}
const rows=['A','B'].map((name,court)=>{
    const row=new Element({name,court:String(court+1)});
    const sport=new Element();sport.className='sport';sport.textContent='Cầu lông';row.querySelector=()=>sport;
    row.cells=['09:00','09:30','10:00','10:30'].map((start,i)=>{
        const cell=new Element({court:String(court+1),index:String(i),start,end:['09:30','10:00','10:30','11:00'][i]});
        cell.closest=()=>row;cell.querySelector=()=>new Element();cell.classList.add('is-free');return cell;
    });return row;
});
rows[0].cells[2].disabled=true;
rows[0].cells[2].classList.remove('is-free');rows[0].cells[2].classList.add('is-booked');
const cells=rows.flatMap(r=>r.cells), root=new Element({now:'2026-10-31T08:31:00+07:00',date:'2026-10-31'});
root.style={setProperty(){}};
const minus=new Element({zoom:'-1'}),plus=new Element({zoom:'1'});
root.querySelectorAll=s=>s==='.time-cell'?cells:[minus,plus];root.querySelector=s=>s.includes('-1')?minus:s.includes('data-zoom')?plus:null;
const nodes={'court-schedule':root};
for(const id of ['selection-title','selection-preview','selection-feedback','clear-selection','zoom-level','selection-duration','selected-courts'])nodes[id]=new Element();
const empty=new Element();empty.className='selection-empty';nodes['selected-courts'].appendChild(empty);
let tick,clock=0;
const events={},windowEvents={},timers=new Map();let timerId=0,fetchCount=0,respond;
const documentMock={hidden:false,getElementById:id=>nodes[id],createElement:()=>new Element(),addEventListener:(name,fn)=>{events[name]=fn;}};
vm.runInNewContext(fs.readFileSync('public/assets/js/schedule.js','utf8'),{
    window:{addEventListener:(name,fn)=>{windowEvents[name]=fn;}},document:documentMock,
    performance:{now:()=>clock},Date,URL,AbortController,location:{href:'http://localhost/index.php'},
    setInterval:fn=>{tick=fn;},setTimeout:(fn,ms)=>{timers.set(++timerId,{fn,ms});return timerId;},clearTimeout:id=>timers.delete(id),
    fetch:()=>{fetchCount++;return respond();}
});
const count=()=>cells.filter(c=>c.classes.has('is-selected')).length;
const groups=()=>nodes['selected-courts'].querySelectorAll('.selected-court-row');
assert.equal(nodes['selection-duration'].textContent,'0 giờ 00 phút');
rows[0].cells[0].click();assert.equal(count(),1);rows[0].cells[0].click();assert.equal(count(),0);
rows[0].cells[0].click();rows[0].cells[1].click();
assert.equal(groups().length,1);assert.equal(groups()[0].children[1].textContent,'09:00–10:00');
rows[0].cells[3].click();assert.equal(groups().length,2,'noncontiguous cells remain separate');
rows[1].cells[0].click();assert.equal(count(),4);assert.equal(nodes['selection-duration'].textContent,'2 giờ');
rows[0].cells[2].click();assert.equal(count(),4,'disabled cell rejected');
rows[0].cells[1].click();assert.equal(count(),3);assert.equal(nodes['selection-duration'].textContent,'1 giờ 30 phút');
groups()[0].children[3].click();assert.equal(count(),2,'remove range updates grid');
assert.equal(rows[0].cells[0]['aria-pressed'],'false');
plus.click();assert.equal(nodes['zoom-level'].textContent,'125%');minus.click();assert.equal(nodes['zoom-level'].textContent,'Fit');
assert.equal(rows[0].cells[0].dataset.end,'09:30');
clock=30*60*1000;tick();assert(rows[1].cells[0].disabled);assert.equal(count(),1,'elapsed slot removed without clearing future selection');
nodes['clear-selection'].click();assert.equal(count(),0);assert(nodes['clear-selection'].disabled);
console.log('PASS: multi-court toggle, gaps, grouping, totals, remove, aria, expiry and zoom; browser geometry tested separately.');

(async()=>{
    root.dataset.endpoint='http://localhost/index.php?route=/courts/schedule&id=1';
    let snapshot={facility:1,date:root.dataset.date,now:root.dataset.now,
        slots:rows[0].cells.map(c=>[c.dataset.start,c.dataset.end]),
        courts:[{id:1,states:['free','free','booked','free']},{id:2,states:['free','free','free','free']}]};
    const flush=()=>new Promise(resolve=>setImmediate(resolve));
    const response=()=>({ok:true,json:async()=>structuredClone(snapshot)});
    respond=async()=>response();clock=0;windowEvents.online();await flush();
    rows[0].cells[0].click();rows[1].cells[1].click();assert.equal(count(),2);
    snapshot.courts[0].states[0]='held';windowEvents.online();await flush();
    assert.equal(count(),1);assert(rows[0].cells[0].classes.has('is-held'));assert(rows[1].cells[1].classes.has('is-selected'));
    snapshot.courts[0].states[0]='free';windowEvents.online();await flush();assert(!rows[0].cells[0].disabled);
    snapshot.now='2026-10-31T09:29:59+07:00';windowEvents.online();await flush();
    assert(!rows[1].cells[1].disabled,'09:29:59 permits 09:30');clock+=1000;tick();
    assert(rows[1].cells[1].disabled,'09:30 locks the starting slot');assert.equal(count(),0);
    root.dataset.date='2026-11-01';snapshot.date=root.dataset.date;windowEvents.online();await flush();
    assert(!rows[0].cells[0].disabled,'future day never locked by today time');
    rows[1].cells[1].click();assert.equal(count(),1);
    respond=async()=>{throw new Error('offline')};windowEvents.online();await flush();assert.equal(count(),1,'outage preserves selection');
    rows[1].cells[3].click();await flush();assert.equal(count(),1,'stale schedule blocks new choices');
    let oldResolve;respond=()=>new Promise(resolve=>{oldResolve=resolve});windowEvents.online();
    const requests=fetchCount;windowEvents.online();assert.equal(fetchCount,requests,'one request at a time');
    documentMock.hidden=true;events.visibilitychange();
    documentMock.hidden=false;respond=async()=>response();events.visibilitychange();await flush();
    assert(!rows[0].cells[0].disabled);const old=structuredClone(snapshot);old.courts[0].states[0]='booked';
    oldResolve({ok:true,json:async()=>old});await flush();assert(!rows[0].cells[0].disabled,'aborted stale response cannot overwrite newer state');
    assert.equal(count(),1,'resume preserves valid selection');
    console.log('PASS: realtime hold/release, valid selection preservation, exact 09:30 lock, future date, offline, tab resume and stale response rejection.');
})().catch(error=>{console.error(error);process.exitCode=1;});
