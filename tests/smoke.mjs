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

// ---- Protocol eras and Skills over MCP ----
import { createHash } from 'node:crypto';
import { collectSkillFiles } from '../scripts/sync-skills.mjs';

const V = '2026-07-28';
const post = async (body, headers = {}, url = 'https://example.com/connected-app/mcp') => {
  const res = await worker.fetch(new Request(url, { method: 'POST', headers: { 'content-type': 'application/json', ...headers }, body: JSON.stringify(body) }));
  return { status: res.status, body: await res.json() };
};
const modern = (method, params = {}, opt = {}) => {
  const name = method === 'tools/call' ? params.name : method === 'resources/read' ? params.uri : null;
  const headers = { 'mcp-protocol-version': V, 'mcp-method': method, ...(name ? { 'mcp-name': name } : {}), ...(opt.headers || {}) };
  const meta = { 'io.modelcontextprotocol/protocolVersion': V, 'io.modelcontextprotocol/clientCapabilities': {}, ...(opt.meta || {}) };
  return post({ jsonrpc: '2.0', id: 7, method, params: { ...params, _meta: meta } }, headers, opt.url);
};

// Legacy: version negotiation no longer echoes versions the server does not implement.
assert.equal((await rpc('initialize', { protocolVersion: '2025-11-25' })).protocolVersion, '2025-11-25');
assert.equal((await rpc('initialize', { protocolVersion: '2026-07-28' })).protocolVersion, '2025-06-18');
assert.equal((await rpc('initialize', { protocolVersion: 'nonsense' })).protocolVersion, '2025-06-18');
assert.ok(init.capabilities.resources, 'legacy initialize declares resources');
assert.equal(init.capabilities.extensions, undefined, 'legacy capabilities stay within the legacy schema');
// Legacy results are unchanged in shape.
const legacyList = await post({ jsonrpc: '2.0', id: 1, method: 'tools/list' }, { 'mcp-protocol-version': '2025-06-18' });
assert.equal(legacyList.status, 200);
assert.equal(legacyList.body.result.resultType, undefined);
assert.equal(legacyList.body.result.ttlMs, undefined);
assert.deepEqual((await post({ jsonrpc: '2.0', id: 1, method: 'ping' })).body.result, {});
const legacyUnknown = await post({ jsonrpc: '2.0', id: 1, method: 'nope/nope' });
assert.equal(legacyUnknown.status, 200);
assert.equal(legacyUnknown.body.error.code, -32601);

// Modern: server/discover.
const disc = await modern('server/discover');
assert.equal(disc.status, 200);
const d = disc.body.result;
assert.equal(d.resultType, 'complete');
assert.deepEqual(d.supportedVersions, [V]);
assert.deepEqual(d.capabilities.extensions['io.modelcontextprotocol/skills'], {});
assert.ok(d.capabilities.resources && d.capabilities.tools);
assert.equal(d._meta['io.modelcontextprotocol/serverInfo'].name, 'upgrowth-growth-toolkit');
assert.equal(d.cacheScope, 'public');
assert.ok(Number.isInteger(d.ttlMs) && d.ttlMs >= 0);
assert.ok(d.instructions.includes('skill://ai-visibility-check/SKILL.md'));

// Modern: tools.
const mList = (await modern('tools/list')).body.result;
assert.equal(mList.resultType, 'complete');
assert.equal(mList.tools.length, 29);
assert.equal(mList.cacheScope, 'public');
const mCall = await modern('tools/call', { name: 'roas_check', arguments: { ad_spend: 50000, ad_revenue: 210000, gross_margin_pct: 40 } });
assert.equal(mCall.status, 200);
assert.equal(mCall.body.result.resultType, 'complete');
assert.equal(mCall.body.result.structuredContent.roas, 4.2);
const mCallB64 = await modern('tools/call', { name: 'roas_check', arguments: { ad_spend: 1, ad_revenue: 2, gross_margin_pct: 40 } }, { headers: { 'mcp-name': '=?base64?' + Buffer.from('roas_check').toString('base64') + '?=' } });
assert.equal(mCallB64.status, 200);

// Modern: errors.
const expectErr = async (p, status, code) => { const r = await p; assert.equal(r.status, status); assert.equal(r.body.error.code, code); return r; };
const unsup = await expectErr(modern('tools/list', {}, { meta: { 'io.modelcontextprotocol/protocolVersion': '2099-01-01' }, headers: { 'mcp-protocol-version': '2099-01-01' } }), 400, -32022);
assert.ok(unsup.body.error.data.supported.includes(V));
assert.equal(unsup.body.error.data.requested, '2099-01-01');
await expectErr(modern('tools/list', {}, { headers: { 'mcp-protocol-version': '2025-06-18' } }), 400, -32020);
await expectErr(modern('tools/list', {}, { headers: { 'mcp-method': 'tools/call' } }), 400, -32020);
await expectErr(modern('tools/call', { name: 'roas_check', arguments: {} }, { headers: { 'mcp-name': 'break_even' } }), 400, -32020);
await expectErr(post({ jsonrpc: '2.0', id: 1, method: 'tools/list', params: { _meta: { 'io.modelcontextprotocol/protocolVersion': V } } }, { 'mcp-protocol-version': V, 'mcp-method': 'tools/list' }), 400, -32602);
await expectErr(post({ jsonrpc: '2.0', id: 1, method: 'server/discover' }), 400, -32602);
await expectErr(modern('ping'), 404, -32601);
await expectErr(modern('nope/nope'), 404, -32601);

// Skills: the inlined copy matches skills/ on disk, and the manifest matches the bytes served.
const onDisk = collectSkillFiles();
const { skills } = (await modern('skills/list')).body.result;
assert.equal(skills.length, 2);
assert.deepEqual(skills.map((s) => s.uri), ['skill://ai-visibility-check/SKILL.md', 'skill://paid-growth-diagnostic/SKILL.md']);
const toolNames = new Set(tools.map((t) => t.name));
for (const sk of skills) {
  const dir = sk.uri.split('/').slice(-2)[0];
  assert.equal(sk.frontmatter.name, dir, 'skill name matches its directory');
  assert.match(sk.frontmatter.name, /^[a-z0-9]+(-[a-z0-9]+)*$/);
  assert.ok(sk.frontmatter.name.length <= 64 && sk.frontmatter.description.length <= 1024);
  assert.ok(!/: |["'#]/.test(sk.frontmatter.description), 'description stays a plain YAML scalar');
  assert.ok(sk.resources.some((r) => r.uri === sk.uri), 'manifest includes SKILL.md');
  for (const r of sk.resources) {
    const read = await modern('resources/read', { uri: r.uri });
    assert.equal(read.status, 200);
    const c = read.body.result.contents[0];
    assert.equal(c.text, onDisk[r.uri], r.uri + ' matches skills/ on disk (run node scripts/sync-skills.mjs)');
    const bytes = Buffer.from(c.text, 'utf8');
    assert.equal(r.size, bytes.length);
    assert.equal(r.digest, 'sha256:' + createHash('sha256').update(bytes).digest('hex'));
    assert.equal(read.body.result.cacheScope, 'public');
  }
  const got = (await modern('skills/get', { uri: sk.uri })).body.result;
  assert.deepEqual(got.skill, sk);
  assert.equal(got.resultType, 'complete');
  // Every tool a skill names in backticks must exist.
  for (const m of onDisk[sk.uri].matchAll(/`([a-z]+(?:_[a-z]+)+)`/g)) {
    if (!toolNames.has(m[1])) {
      const props = tools.flatMap((t) => Object.keys(t.inputSchema.properties || {}));
      assert.ok(props.includes(m[1]), `${sk.frontmatter.name} names \`${m[1]}\`, which is not a tool or tool input`);
    }
  }
}
assert.equal(Object.keys(onDisk).length, skills.reduce((n, s) => n + s.resources.length, 0), 'every file under skills/ is in a manifest');
await expectErr(modern('skills/get', { uri: 'skill://missing/SKILL.md' }), 200, -32602);
await expectErr(modern('resources/read', { uri: 'skill://missing/SKILL.md' }), 200, -32602);
const resList = (await modern('resources/list')).body.result;
assert.equal(resList.resources.length, 2);
assert.equal(resList.resources[0].mimeType, 'text/markdown');
// Legacy clients can read the same skill files as plain resources.
assert.equal((await rpc('resources/list')).resources.length, 2);
assert.ok((await rpc('resources/read', { uri: skills[0].uri })).contents[0].text.startsWith('---\nname: ai-visibility-check\n'));

// The ChatGPT host hides the call-booking tool in both eras.
const gpt = 'https://mcp.upgrowth.in/mcp';
assert.equal((await modern('tools/list', {}, { url: gpt })).body.result.tools.length, 28);
assert.ok(!(await modern('server/discover', {}, { url: gpt })).body.result.instructions.includes('book_strategy_call when'));

console.log(`OK: dual-era protocol and ${skills.length} skills verified.`);
