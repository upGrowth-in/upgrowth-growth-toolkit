#!/usr/bin/env node
// Local stdio transport for the Growth Toolkit, for MCP clients and directories
// that start servers as a local process (for example Glama's build checks).
// It reads newline-delimited JSON-RPC from stdin, passes each message to the
// same handler the Cloudflare Worker uses, and writes replies to stdout.
// Usage: node src/stdio.mjs
import { createInterface } from 'node:readline';
import worker from './worker.mjs';

const ENDPOINT = 'https://upgrowth.in/connected-app/mcp';

async function handle(line) {
  const text = line.trim();
  if (!text) return;
  let res;
  const headers = { 'content-type': 'application/json', accept: 'application/json', 'user-agent': 'upgrowth-stdio' };
  // stdio has no HTTP headers. For modern (2026-07-28) requests, mirror the body fields
  // the HTTP transport requires so the shared handler's header validation passes.
  try {
    const msg = JSON.parse(text);
    const p = (msg && msg.params) || {};
    const v = p._meta && p._meta['io.modelcontextprotocol/protocolVersion'];
    if (typeof v === 'string') {
      headers['mcp-protocol-version'] = v;
      headers['mcp-method'] = String(msg.method || '');
      const name = msg.method === 'tools/call' ? p.name : msg.method === 'resources/read' ? p.uri : null;
      if (typeof name === 'string') headers['mcp-name'] = '=?base64?' + Buffer.from(name, 'utf8').toString('base64') + '?=';
    } else if (msg && msg.method === 'server/discover') {
      headers['mcp-method'] = 'server/discover';
    }
  } catch { /* the handler reports parse errors */ }
  try {
    res = await worker.fetch(new Request(ENDPOINT, { method: 'POST', headers, body: text }));
  } catch (err) {
    process.stderr.write('stdio handler error: ' + (err && err.message ? err.message : String(err)) + '\n');
    return;
  }
  const body = (await res.text()).trim();
  if (!body) return; // notifications get no reply
  process.stdout.write(body.replace(/\n/g, ' ') + '\n');
}

let queue = Promise.resolve();
const rl = createInterface({ input: process.stdin, crlfDelay: Infinity });
rl.on('line', (line) => { queue = queue.then(() => handle(line)); });
rl.on('close', () => { queue.then(() => process.exit(0)); });
