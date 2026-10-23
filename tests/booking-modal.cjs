const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const handlers = {}, modalHandlers = {};
const classes = new Set();
let closeButton, opened = false, focused = false;
const normal = {};
const modal = {
    showModal(){opened=true;}, close(){opened=false;modalHandlers.close();},
    querySelector(){return {addEventListener(name,fn){closeButton=fn;}};},
    addEventListener(name,fn){modalHandlers[name]=fn;},
    getBoundingClientRect(){return {left:100,right:600,top:100,bottom:500};}
};
const document = {
    getElementById(id){return id==='booking-choice'?modal:id==='booking-normal'?normal:null;},
    querySelectorAll(){return [];}, querySelector(){return null;},
    addEventListener(name,fn){handlers[name]=fn;},
    body:{classList:{add:x=>classes.add(x),remove:x=>classes.delete(x)}}
};
vm.runInNewContext(fs.readFileSync('public/assets/js/courts.js','utf8'),{document});
for (const id of [12,37]) {
    const href=`http://localhost/index.php?route=%2Fcourts%2Fschedule&id=${id}`;
    const link={href,dataset:{},focus(){focused=true;}};
    let prevented=false;
    handlers.click({target:{closest:()=>link},preventDefault(){prevented=true;}});
    assert(prevented && opened); assert.equal(normal.href,href,'correct facility for card or dynamic drawer link');
    closeButton(); assert(!opened && focused && !classes.has('drawer-open'));
}
modal.showModal();modalHandlers.click({target:modal,clientX:10,clientY:10});assert(!opened,'overlay closes');
console.log('PASS: delegated booking modal links, facility destination, close button, overlay and focus restoration. Native Escape/focus trap require browser.');
