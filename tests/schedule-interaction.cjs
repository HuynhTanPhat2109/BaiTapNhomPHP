// Exercise the actual interaction script without external DOM packages.
const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
class Element {
    constructor(data = {}) { this.dataset = data; this.disabled = false; this.textContent = ''; this.events = {}; this.classes = new Set(); this.classList = {add: (...xs) => xs.forEach(x => this.classes.add(x)), remove: (...xs) => xs.forEach(x => this.classes.delete(x)), toggle: (x,on) => on ? this.classes.add(x) : this.classes.delete(x)}; }
    addEventListener(name, fn) { this.events[name] = fn; }
    setAttribute(name, value) { this[name] = value; }
    click() { this.events.click?.(); }
}
const rows = ['A', 'B'].map((name, court) => {
    const row = new Element({name, court: String(court)});
    row.cells = ['09:00', '09:30', '10:00', '10:30'].map((start, i) => {
        const cell = new Element({index: String(i), start, end: ['09:30','10:00','10:30','11:00'][i]});
        cell.closest = () => row; cell.querySelector = () => new Element(); return cell;
    });
    row.querySelectorAll = () => row.cells; return row;
});
rows[0].cells[2].disabled = true;
const cells = rows.flatMap(r => r.cells);
const root = new Element({now:'2026-10-23T08:31:00+07:00', date:'2026-10-23', endpoint:'http://localhost/index.php?route=/courts/schedule&id=1'});
root.style = {setProperty(){}};
const minus = new Element({zoom:'-1'}), plus = new Element({zoom:'1'});
root.querySelectorAll = selector => selector === '.time-cell' ? cells : [minus,plus];
root.querySelector = selector => selector.includes('-1') ? minus : plus;
const nodes = {'court-schedule':root,'selection-preview':new Element(),'selection-feedback':new Element(),'clear-selection':new Element(),'zoom-level':new Element()};
let tick;
vm.runInNewContext(fs.readFileSync('public/assets/js/schedule.js','utf8'), {document:{getElementById:id=>nodes[id],addEventListener(){}},performance:{now:()=>0},Date,URL,AbortController,location:{href:'http://localhost/'},setInterval:fn=>{tick=fn;},setTimeout:()=>1,clearTimeout(){},fetch(){throw new Error('Preview network tested separately in PHP HTTP suite');}});
assert.equal(nodes['clear-selection'].disabled,true);
rows[0].cells[0].click(); rows[0].cells[1].click();
assert.match(nodes['selection-preview'].textContent,/09:00–10:00/);
assert.equal(cells.filter(c=>c.classes.has('is-selected')).length,2);
rows[0].cells[3].click();
assert.equal(cells.filter(c=>c.classes.has('is-selected')).length,2,'blocked gap cannot be crossed');
rows[1].cells[0].click();
assert.match(nodes['selection-preview'].textContent,/^A/,'another court cannot join selection');
plus.click(); assert.equal(nodes['zoom-level'].textContent,'125%'); assert(root.classes.has('zoom-large'));
minus.click(); minus.click(); assert.equal(nodes['zoom-level'].textContent,'Fit'); assert(minus.disabled);
assert.equal(rows[0].cells[0].dataset.end,'09:30','zoom preserves slot duration');
nodes['clear-selection'].click(); assert.equal(cells.filter(c=>c.classes.has('is-selected')).length,0);
rows[1].cells[0].click(); assert.match(nodes['selection-preview'].textContent,/^B/);
tick();
console.log('PASS: grid selection, contiguous range, blocked gap, court boundary, reset and zoom interactions. Browser layout not covered.');
