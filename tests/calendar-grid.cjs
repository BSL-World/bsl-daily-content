const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('media/js/daily-content.js', 'utf8');
const render = source.slice(source.indexOf('        const renderCalendar = () => {'), source.indexOf('\n        renderCalendar();', source.indexOf('        const renderCalendar = () => {')));
class Element {
    constructor(tag) { this.tag = tag; this.children = []; this.attrs = {}; this.events = {}; this.classes = new Set(); this.classList = { add: name => this.classes.add(name) }; }
    setAttribute(name, value) { this.attrs[name] = value; }
    addEventListener(name, fn) { this.events[name] = fn; }
    append(...nodes) { this.children.push(...nodes); }
    appendChild(node) { this.append(node); }
    replaceChildren(fragment) { this.children = fragment.children; }
}
let checked = 0;
for (const year of [2024, 2026, 2027]) {
    for (let month = 1; month <= 12; month++) {
        const calendar = new Element('calendar');
        const context = {
            document: { createElement: tag => new Element(tag), createDocumentFragment: () => new Element('fragment') },
            displayedYear: year, displayedMonth: month, now: new Date(year, 10, 2),
            currentDate: () => '02-11', selectedDate: '02-11',
            availableDates: new Set(['02-11', '31-12', '01-01']),
            padNumber: n => String(n).padStart(2, '0'),
            months: Array.from({length: 12}, (_, i) => String(i + 1)),
            weekdayNames: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            dateFormatter: new Intl.DateTimeFormat('en-GB', {day: 'numeric', month: 'long', timeZone: 'UTC'}),
            container: {dataset: {}}, calendar, navigateToDate: date => { context.clicked = date; },
        };
        vm.createContext(context);
        vm.runInContext(render + '\nrenderCalendar();', context);
        const buttons = calendar.children[1].children.slice(7);
        const offset = (new Date(year, month - 1, 1).getDay() + 6) % 7;
        assert.equal(buttons.length, Math.ceil((offset + new Date(year, month, 0).getDate()) / 7) * 7);
        buttons.forEach((button, index) => {
            const date = new Date(Date.UTC(year, month - 1, index - offset + 1));
            const key = `${String(date.getUTCDate()).padStart(2, '0')}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
            assert.equal(button.tag, 'button');
            assert.equal(button.type, 'button');
            assert.equal(button.textContent, String(date.getUTCDate()));
            assert.equal(button.classes.has('is-outside-month'), date.getUTCMonth() + 1 !== month);
            assert.equal(button.classes.has('is-selected'), key === '02-11');
            assert.equal(button.classes.has('is-today'), key === '02-11');
            assert.equal(button.classes.has('is-available'), context.availableDates.has(key));
            button.events.click();
            assert.equal(context.clicked, key);
            checked++;
        });
        calendar.children[0].children[0].events.click();
        assert.equal(context.displayedMonth, month === 1 ? 12 : month - 1);
        calendar.children[0].children[2].events.click();
        assert.equal(context.displayedMonth, month);
    }
}
assert.match(source, /const selectedDate = requestedDateFromUrl\(\) \|\| currentDate\(\);/);
assert.match(source, /let displayedMonth = parsedDate.month;/);
console.log(`PASS: 36 months, ${checked} clickable dates, 4/5/6-week grids, leap year, year boundaries, states and month navigation`);
