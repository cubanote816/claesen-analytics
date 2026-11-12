// Standalone harness for the prospects floating "Start Mailing Campaign" button (CLA-676).
// Run with: node --test Modules/Prospects/tests/js/floating-mailing-button.test.mjs
// There is no JS test runner in this repo, so this file is NOT executed in CI.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const view = fs.readFileSync(
    path.resolve(here, '../../resources/views/filament/prospects/floating-mailing-button.blade.php'),
    'utf8',
);
const script = view.match(/<script>([\s\S]*)<\/script>/)[1];

function classList() {
    const set = new Set();
    return { add: (c) => set.add(c), remove: (c) => set.delete(c), has: (c) => set.has(c) };
}

function setup({ withTable = true, initialized = true } = {}) {
    const fab = { classList: classList(), remove() {} };
    const badge = { textContent: '0' };
    const tableEl = {};
    const state = { count: 0, mountCalls: [] };
    const tableData = {
        getSelectedRecordsCount: () => state.count,
        mountAction: (...args) => state.mountCalls.push(args),
    };
    const root = {
        querySelector: (sel) => (sel === 'table' ? {} : sel.includes('filamentTable') ? (withTable ? tableEl : null) : null),
        querySelectorAll: (sel) => (sel === 'input.fi-ta-record-checkbox:checked'
            ? Array.from({ length: 10 }, (_, i) => ({ value: String(i + 1) }))
            : []),
        getAttribute: () => 'wire-1',
        contains: () => true,
    };
    const component = { sets: [], calls: [], set(...a) { this.sets.push(a); }, call(...a) { this.calls.push(a); } };
    const effects = [];
    const intervals = [];
    const document = {
        getElementById: (id) => (id === 'prospect-fab' ? fab : badge),
        querySelectorAll: (sel) => (sel === '[wire\\:id]' ? [root] : []),
        querySelector: (sel) => (sel === 'table' ? { closest: () => root } : null),
        body: { appendChild() {} },
        addEventListener() {},
    };
    const window = {
        location: { pathname: '/prospects' },
        Livewire: { hook() {}, find: () => component },
        Alpine: {
            $data: (el) => (el === tableEl ? (initialized ? tableData : {}) : undefined),
            effect: (fn) => { effects.push(fn); fn(); },
        },
        addEventListener() {},
    };
    const sandbox = { window, document, Livewire: window.Livewire, setTimeout() {}, setInterval: (fn) => intervals.push(fn) };
    vm.runInNewContext(script, sandbox);
    // Re-run the reactive effects (if any) and the polling fallback, as the browser would.
    const tick = () => { effects.forEach((fn) => fn()); intervals.forEach((fn) => fn()); };
    return { fab, badge, state, window, component, tick, effects };
}

test('badge shows the 10 selected records', () => {
    const t = setup();
    t.state.count = 10; t.tick();
    assert.equal(t.badge.textContent, 10);
    assert.equal(t.fab.classList.has('is-visible'), true);
});

test('select-all mode shows the table count, not the visible rows', () => {
    const t = setup();
    t.state.count = 20; t.tick();
    assert.equal(t.badge.textContent, 20);
    assert.equal(t.fab.classList.has('is-visible'), true);
});

test('one exclusion after select-all shows 19', () => {
    const t = setup();
    t.state.count = 20; t.tick();
    t.state.count = 19; t.tick();
    assert.equal(t.badge.textContent, 19);
});

test('zero selected hides the button', () => {
    const t = setup();
    t.state.count = 5; t.tick();
    assert.equal(t.fab.classList.has('is-visible'), true);
    t.state.count = 0; t.tick();
    assert.equal(t.fab.classList.has('is-visible'), false);
});

test('click delegates to the table mountAction and never overwrites the selection', () => {
    const t = setup();
    t.state.count = 20; t.tick();
    t.window.__prospectsStartMailing();
    // JSON round-trip: objects built inside the vm sandbox have a different prototype.
    assert.deepEqual(JSON.parse(JSON.stringify(t.state.mountCalls)), [['execute_campaign', {}, { table: true, bulk: true }]]);
    assert.equal(t.component.sets.length, 0);
    assert.equal(t.component.calls.length, 0);
});

test('click without table data does nothing and does not throw', () => {
    const t = setup({ withTable: false });
    assert.doesNotThrow(() => t.window.__prospectsStartMailing());
    assert.equal(t.state.mountCalls.length, 0);
    assert.equal(t.component.sets.length, 0);
    assert.equal(t.component.calls.length, 0);
});

// Livewire SPA navigation runs body scripts before Alpine initializes the table:
// $data() then returns an empty proxy, which must not throw or block a later bind.
test('existing table without initialized Alpine data is safe', () => {
    assert.doesNotThrow(() => setup({ initialized: false }));
});
