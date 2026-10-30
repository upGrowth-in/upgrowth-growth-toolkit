// Smoke test: node tests/smoke.mjs
import worker from '../src/worker.mjs';
import assert from 'node:assert/strict';

const rpc = async (method, params = {}) => {
  const res = await worker.fetch(new Request('https://example.com/connected-app/mcp', {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ jsonrpc: '2.0', id: 1, method, params }),
  }));
  return (await res.json()).result;
};

const init = await rpc('initialize', { protocolVersion: '2025-06-18' });
assert.equal(init.serverInfo.name, 'upgrowth-growth-toolkit');

const { tools } = await rpc('tools/list');
assert.equal(tools.length, 29, 'expected 29 tools');
for (const t of tools) {
  assert.ok(t.title, `${t.name} has a title`);
  assert.equal(t.annotations.readOnlyHint, true, `${t.name} is read-only`);
  assert.equal(t.annotations.title, t.title, `${t.name} has annotations.title`);
}

const call = (name, args) => rpc('tools/call', { name, arguments: args });

const roas = await call('roas_check', { ad_spend: 50000, ad_revenue: 210000, gross_margin_pct: 40 });
assert.equal(roas.isError, false);
assert.equal(roas.structuredContent.roas, 4.2);

const be = await call('break_even', { fixed_costs: 50000, price_per_unit: 200, variable_cost_per_unit: 120 });
assert.equal(be.isError, false);
assert.equal(be.structuredContent.break_even_units, 625);
assert.equal(roas.structuredContent.break_even_roas, 2.5);

const bad = await call('roas_check', { ad_spend: -1, ad_revenue: 10, gross_margin_pct: 40 });
assert.equal(bad.isError, true);

console.log(`OK: ${tools.length} tools, all read-only; sample calls pass.`);
