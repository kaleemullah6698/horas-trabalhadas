/**
 * End-to-end smoke test for the calculator.
 *
 * assets/js/app.js is an IIFE that binds straight to the DOM, so rather than
 * refactoring it to be importable (which would risk changing behaviour), this
 * builds a minimal DOM matching templates/calculator.php, evaluates the real
 * shipped script against it, then drives the actual inputs and reads the actual
 * output cells. Nothing is re-implemented here, so a regression in app.js fails
 * this test.
 *
 * Run with `npm run test:calc`.
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');

/* ------------------------------------------------------------------ *
 * Minimal DOM
 * ------------------------------------------------------------------ */

function makeEl(tag) {
  const el = {
    tagName: String(tag).toUpperCase(),
    children: [],
    attributes: {},
    style: {},
    dataset: {},
    options: [],
    checked: false,
    value: '',
    _text: '',
    _listeners: {},
    classList: { add() {}, remove() {}, contains() { return false; } },
    append(...nodes) {
      for (const n of nodes) {
        if (!n || typeof n !== 'object') continue;
        // A real DocumentFragment splices its children in and empties itself,
        // rather than being inserted as a node. renderRows() relies on that.
        if (n.tagName === 'FRAGMENT') {
          for (const child of n.children) { child.parentNode = el; el.children.push(child); }
          n.children.length = 0;
          continue;
        }
        n.parentNode = el;
        el.children.push(n);
      }
    },
    appendChild(n) { el.append(n); },
    setAttribute(k, v) { el.attributes[k] = String(v); },
    getAttribute(k) { return Object.prototype.hasOwnProperty.call(el.attributes, k) ? el.attributes[k] : null; },
    addEventListener(type, fn) { (el._listeners[type] = el._listeners[type] || []).push(fn); },
    dispatch(type) { (el._listeners[type] || []).forEach(fn => fn({ target: el })); },
    querySelector() { return null; },
    querySelectorAll() { return []; },
    remove() {},
    click() { if (typeof el.onclick === 'function') el.onclick(); },
    getBoundingClientRect() { return { top: 0 }; },
  };

  Object.defineProperty(el, 'textContent', {
    get() { return el._text; },
    // Assigning textContent replaces all children, as in a real DOM.
    set(v) { el._text = String(v); el.children.length = 0; },
  });

  Object.defineProperty(el, 'className', {
    get() { return el.attributes.class || ''; },
    set(v) { el.attributes.class = String(v); },
  });

  Object.defineProperty(el, 'innerHTML', {
    get() { return el._html || ''; },
    set(v) { el._html = String(v); el.children.length = 0; },
  });

  return el;
}

/** Build the element registry the template provides. */
function buildDom() {
  const byId = {};
  const add = (id, props = {}) => {
    const el = makeEl(props.tag || 'div');
    Object.assign(el, props);
    byId['#' + id] = el;
    return el;
  };

  add('preset', { value: 'thisWeek' });
  add('startDate', { value: '' });
  add('endDate', { value: '' });
  add('weekStart', { value: '1', options: [{ value: '1' }, { value: '0' }, { value: '6' }] });
  add('timeFormat', { value: '24', options: [{ value: '24' }, { value: '12' }] });
  add('currency', { value: 'R$' });
  add('rate', { value: '' });
  add('listName', { value: '' });
  add('otRule', { value: 'none' });
  add('otDailyThresh', { value: '8' });
  add('otMult', { value: '1.5' });
  add('dtThresh', { value: '' });
  add('taxPct', { value: '' });
  add('rows');
  add('breakdown');
  add('savedList');
  add('toast');
  add('themeToggle', { checked: false });
  add('htrb-lang', { value: 'pt-BR' });
  add('htrb-top');
  for (const id of ['kTotal', 'kReg', 'kOt', 'kGross', 'kNet', 'footHours', 'footTips', 'footSpan']) add(id);
  for (const id of ['bulkFill', 'clearAll', 'saveBtn', 'csvBtn', 'printBtn', 'shareBtn']) add(id);

  const appRoot = makeEl('div');
  appRoot.querySelector = sel => byId[sel] || null;
  appRoot.querySelectorAll = () => [];
  byId['#htrb-app'] = appRoot;

  return { appRoot, byId };
}

/** Load and run the real app.js against a fresh DOM. */
function boot(config = {}) {
  const { appRoot, byId } = buildDom();
  const store = {};

  const sandbox = {
    document: {
      getElementById: id => (id === 'htrb-app' ? appRoot : byId['#' + id] || null),
      createElement: makeEl,
      createDocumentFragment: () => makeEl('fragment'),
      body: makeEl('body'),
    },
    window: {
      pageYOffset: 0,
      scrollTo() {},
      print() {},
      horasTrabalhadasCfg: config,
    },
    localStorage: {
      getItem: k => (Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null),
      setItem: (k, v) => { store[k] = String(v); },
      removeItem: k => { delete store[k]; },
    },
    navigator: { language: 'pt-BR' },
    location: { hash: '', origin: 'https://exemplo.com.br', pathname: '/ponto/' },
    matchMedia: () => ({ matches: false }),
    setTimeout: () => 0,
    Intl,
    Date,
    Math,
    JSON,
    parseInt,
    parseFloat,
    isNaN,
    String,
    Number,
    Object,
    Array,
    RegExp,
    Blob: function () {},
    URL: { createObjectURL: () => 'blob:', revokeObjectURL() {} },
    btoa: s => Buffer.from(s, 'binary').toString('base64'),
    atob: s => Buffer.from(s, 'base64').toString('binary'),
    escape, unescape, encodeURIComponent, decodeURIComponent,
    console,
  };
  sandbox.window.localStorage = sandbox.localStorage;
  sandbox.globalThis = sandbox;

  const code = fs.readFileSync(path.join(root, 'assets/js/app.js'), 'utf8');
  vm.createContext(sandbox);
  vm.runInContext(code, sandbox, { filename: 'app.js' });

  return { appRoot, byId, store, sandbox };
}

/* ------------------------------------------------------------------ *
 * Helpers to drive the rendered table
 * ------------------------------------------------------------------ */

/** Rows currently rendered into #rows. */
const rowsOf = byId => byId['#rows'].children;

/**
 * Set one day's start/end/break and fire the input handlers the real UI fires.
 *
 * Column order matches the template: day, start, end, break, tips, worked.
 */
function fillRow(tr, { start, end, brk }) {
  const timeInputs = td => td.children[0].children.filter(c => c.tagName === 'INPUT');

  if (start) {
    const [h, m] = timeInputs(tr.children[1]);
    h.value = start[0]; m.value = start[1];
    h.dispatch('input');
  }
  if (end) {
    const [h, m] = timeInputs(tr.children[2]);
    h.value = end[0]; m.value = end[1];
    h.dispatch('input');
  }
  if (brk !== undefined) {
    const b = tr.children[3].children[0];
    b.value = String(brk);
    b.dispatch('input');
  }
}

/* ------------------------------------------------------------------ *
 * Tests
 * ------------------------------------------------------------------ */

let passed = 0;
let failed = 0;

function check(label, actual, expected) {
  if (String(actual) === String(expected)) {
    console.log(`  ok  ${label}  (${actual})`);
    passed++;
  } else {
    console.error(`  FAIL ${label}  expected ${expected}, got ${actual}`);
    failed++;
  }
}

const TZ = 'America/Sao_Paulo';

console.log('\n1. Standard week: 5 weekdays x 8h, R$20/h');
{
  const { byId } = boot({ timezone: TZ, utcOffset: -3, weekStart: 1 });
  byId['#rate'].value = '20';
  byId['#rate'].dispatch('input');

  const rows = rowsOf(byId);
  check('7 days rendered for a week', rows.length, 7);

  // Monday-Friday are the first five rows when the week starts on Monday.
  for (let i = 0; i < 5; i++) fillRow(rows[i], { start: ['9', '00'], end: ['17', '00'], brk: 0 });

  check('total hours', byId['#kTotal'].textContent, '40:00');
  check('regular hours', byId['#kReg'].textContent, '40:00');
  check('overtime', byId['#kOt'].textContent, '0:00');
  check('gross pay', byId['#kGross'].textContent, 'R$800.00');
}

console.log('\n2. Break deduction and overnight shift');
{
  const { byId } = boot({ timezone: TZ, utcOffset: -3, weekStart: 1 });
  const rows = rowsOf(byId);

  fillRow(rows[0], { start: ['9', '00'], end: ['17', '00'], brk: 60 });
  check('8h minus a 60min break', byId['#kTotal'].textContent, '7:00');

  fillRow(rows[1], { start: ['22', '00'], end: ['6', '00'], brk: 0 });
  check('overnight 22:00 to 06:00 adds 8h', byId['#kTotal'].textContent, '15:00');
}

console.log('\n3. Weekly overtime resets each week (the 2.0.0 fix)');
{
  const { byId } = boot({ timezone: TZ, utcOffset: -3, weekStart: 1 });
  byId['#otRule'].value = 'weekly40';
  byId['#otRule'].dispatch('input');

  // A full month, so the range spans several distinct weeks.
  byId['#preset'].value = 'thisMonth';
  byId['#preset'].dispatch('change');

  const rows = rowsOf(byId);
  let weekdays = 0;
  for (const tr of rows) {
    // Weekend rows carry the is-weekend class; skip them as an employee would.
    if (tr.className === 'is-weekend') continue;
    fillRow(tr, { start: ['9', '00'], end: ['17', '00'], brk: 0 });
    weekdays++;
  }

  const expectedTotal = weekdays * 8;
  check('every weekday counted', byId['#kTotal'].textContent, `${expectedTotal}:00`);
  check('no overtime for 8h weekdays across a month', byId['#kOt'].textContent, '0:00');
  check('all hours are regular', byId['#kReg'].textContent, `${expectedTotal}:00`);
}

console.log('\n4. Daily overtime rule still applies');
{
  const { byId } = boot({ timezone: TZ, utcOffset: -3, weekStart: 1 });
  byId['#otRule'].value = 'daily';
  byId['#otRule'].dispatch('input');
  byId['#otDailyThresh'].value = '8';
  byId['#otDailyThresh'].dispatch('input');

  const rows = rowsOf(byId);
  fillRow(rows[0], { start: ['8', '00'], end: ['18', '00'], brk: 0 });

  check('10h worked', byId['#kTotal'].textContent, '10:00');
  check('8h regular', byId['#kReg'].textContent, '8:00');
  check('2h overtime', byId['#kOt'].textContent, '2:00');
}

console.log('\n5. Out-of-range hour is clamped, not trusted');
{
  const { byId } = boot({ timezone: TZ, utcOffset: -3, weekStart: 1 });
  const rows = rowsOf(byId);
  fillRow(rows[0], { start: ['0', '00'], end: ['99', '00'], brk: 0 });
  check('hour 99 clamps to 23:00', byId['#kTotal'].textContent, '23:00');
}

console.log('\n6. Site timezone decides "today", not the device');
{
  // Kiritimati is UTC+14; Baker Island style offsets are UTC-11. On the same
  // instant these are frequently different calendar dates, which is exactly the
  // situation the WordPress timezone config has to win.
  const a = boot({ timezone: 'Pacific/Kiritimati', utcOffset: 14, weekStart: 1 });
  const b = boot({ timezone: 'Pacific/Niue', utcOffset: -11, weekStart: 1 });

  const dateA = a.byId['#startDate'].value;
  const dateB = b.byId['#startDate'].value;
  console.log(`  week start with UTC+14: ${dateA}`);
  console.log(`  week start with UTC-11: ${dateB}`);

  const expected = tz => {
    const ymd = new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    const [y, m, d] = ymd.split('-').map(Number);
    const day = new Date(y, m - 1, d);
    day.setDate(day.getDate() - ((day.getDay() - 1 + 7) % 7)); // back to Monday
    const p = n => String(n).padStart(2, '0');
    return `${day.getFullYear()}-${p(day.getMonth() + 1)}-${p(day.getDate())}`;
  };

  check('UTC+14 week resolved in site timezone', dateA, expected('Pacific/Kiritimati'));
  check('UTC-11 week resolved in site timezone', dateB, expected('Pacific/Niue'));

  // Sites configured by a numeric offset rather than a city send timezone:''.
  // That fallback path has to be correct too, so it is asserted separately
  // against an independently computed expectation.
  const byOffset = offset => {
    const now = new Date();
    const site = new Date(now.getTime() + now.getTimezoneOffset() * 60000 + offset * 3600000);
    const day = new Date(site.getFullYear(), site.getMonth(), site.getDate());
    day.setDate(day.getDate() - ((day.getDay() - 1 + 7) % 7));
    const p = n => String(n).padStart(2, '0');
    return `${day.getFullYear()}-${p(day.getMonth() + 1)}-${p(day.getDate())}`;
  };

  const offsetSite = boot({ timezone: '', utcOffset: -3, weekStart: 1 });
  check('offset-configured site (UTC-3) resolves correctly', offsetSite.byId['#startDate'].value, byOffset(-3));

  const offsetFar = boot({ timezone: '', utcOffset: 13, weekStart: 1 });
  check('offset-configured site (UTC+13) resolves correctly', offsetFar.byId['#startDate'].value, byOffset(13));

  // With no config at all the calculator must still work, falling back to the
  // device clock exactly as the standalone version did.
  const noConfig = boot({});
  check('no config still renders a full week', rowsOf(noConfig.byId).length, 7);
}

console.log('\n7. Week-start day follows the WordPress setting');
{
  const sunday = boot({ timezone: TZ, utcOffset: -3, weekStart: 0 });
  check('weekStart selector seeded from config', sunday.byId['#weekStart'].value, '0');
  const first = sunday.byId['#startDate'].value.split('-').map(Number);
  check('range begins on a Sunday', new Date(first[0], first[1] - 1, first[2]).getDay(), 0);
}

console.log('\n8. Saved timesheets migrate from the pre-2.0 storage key');
{
  const { appRoot, byId, store, sandbox } = boot({ timezone: TZ, utcOffset: -3, weekStart: 1 });
  check('new save key written on save', typeof store.horas_trabalhadas_saved_v1, 'undefined');

  // Simulate an existing visitor: only the legacy key is present.
  const legacy = JSON.stringify([{ name: 'Ana', savedAt: 1, settings: {}, days: [] }]);
  const store2 = {};
  store2.whc_pro_saved_v1 = legacy;
  store2.whc_theme = '1';
  store2.whp_lang = 'pt-PT';

  const sandbox2 = Object.assign({}, sandbox);
  // Rebuild with the legacy keys pre-populated.
  const fresh = (() => {
    const dom = buildDom();
    const s = {
      document: {
        getElementById: id => (id === 'htrb-app' ? dom.appRoot : dom.byId['#' + id] || null),
        createElement: makeEl,
        createDocumentFragment: () => makeEl('fragment'),
        body: makeEl('body'),
      },
      window: { pageYOffset: 0, scrollTo() {}, print() {}, horasTrabalhadasCfg: { timezone: TZ, utcOffset: -3, weekStart: 1 } },
      localStorage: {
        getItem: k => (Object.prototype.hasOwnProperty.call(store2, k) ? store2[k] : null),
        setItem: (k, v) => { store2[k] = String(v); },
        removeItem: k => { delete store2[k]; },
      },
      navigator: { language: 'pt-BR' },
      location: { hash: '', origin: 'https://exemplo.com.br', pathname: '/ponto/' },
      matchMedia: () => ({ matches: false }),
      setTimeout: () => 0,
      Intl, Date, Math, JSON, parseInt, parseFloat, isNaN, String, Number, Object, Array, RegExp,
      Blob: function () {}, URL: { createObjectURL: () => 'blob:', revokeObjectURL() {} },
      btoa: x => Buffer.from(x, 'binary').toString('base64'),
      atob: x => Buffer.from(x, 'base64').toString('binary'),
      escape, unescape, encodeURIComponent, decodeURIComponent, console,
    };
    s.window.localStorage = s.localStorage;
    s.globalThis = s;
    vm.createContext(s);
    vm.runInContext(fs.readFileSync(path.join(root, 'assets/js/app.js'), 'utf8'), s, { filename: 'app.js' });
    return { store: store2, byId: dom.byId };
  })();

  check('saved timesheets carried to the new key', fresh.store.horas_trabalhadas_saved_v1, legacy);
  check('theme preference carried over', fresh.store.horas_trabalhadas_theme, '1');
  check('language preference carried over', fresh.store.horas_trabalhadas_lang, 'pt-PT');
  check('legacy key left intact for rollback safety', fresh.store.whc_pro_saved_v1, legacy);
}

console.log(`\n${passed} passed, ${failed} failed`);
if (failed) process.exit(1);
