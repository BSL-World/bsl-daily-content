const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('media/js/daily-content.js', 'utf8');
const start = source.indexOf('        const locale = container.dataset.locale');
const end = source.indexOf('\n        const renderCalendar', start);
assert.ok(start >= 0 && end > start);
const format = (locale, pageLanguage) => vm.runInNewContext(source.slice(start, end) + '\nweekdayNames;', {
    container: { dataset: locale ? { locale } : {} },
    document: { documentElement: { lang: pageLanguage } },
    navigator: { language: 'ru-RU' }, Intl, Date,
});
assert.deepEqual(Array.from(format('en-GB', 'ru-RU')), ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
assert.deepEqual(Array.from(format('ru-RU', 'en-GB')), ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс']);
assert.deepEqual(Array.from(format('', 'en-GB')), ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
const php = fs.readFileSync('src/Extension/Bsldailycontent.php', 'utf8');
assert.ok(php.includes('$calendarLocale = $this->escape($languageTag);'));
assert.ok(php.includes('data-locale="{$calendarLocale}"'));
console.log('PASS: English and Russian weekdays follow Joomla locale; HTML language fallback works');
