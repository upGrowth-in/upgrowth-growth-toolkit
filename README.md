# upGrowth Growth Toolkit (MCP server)

[![upGrowth-in/upgrowth-growth-toolkit MCP server](https://glama.ai/mcp/servers/upGrowth-in/upgrowth-growth-toolkit/badges/score.svg)](https://glama.ai/mcp/servers/upGrowth-in/upgrowth-growth-toolkit) [![upGrowth Growth Toolkit MCP connector](https://glama.ai/mcp/connectors/in.upgrowth/growth-toolkit/badges/score.svg)](https://glama.ai/mcp/connectors/in.upgrowth/growth-toolkit)
[![upGrowth Growth Toolkit MCP connector](https://glama.ai/mcp/connectors/in.upgrowth/growth-toolkit/badges/score.svg)](https://glama.ai/mcp/connectors/in.upgrowth/growth-toolkit)

A free, public, read-only [Model Context Protocol](https://modelcontextprotocol.io) server with 29 growth marketing tools. Connect it to Claude, ChatGPT, Gemini CLI or any MCP client and ask growth maths, SEO and AI-visibility questions in plain language.

- **Endpoint:** `https://upgrowth.in/connected-app/mcp` (Streamable HTTP). Alias: `https://mcp.upgrowth.in/mcp`
- **Auth:** none. No sign-up, no API key.
- **Data:** your inputs and the answers are never stored. We keep anonymous usage counts (event, tool name, host and a broad client type such as ChatGPT or Claude) in Cloudflare Workers Analytics Engine; no IP addresses. 28 of the 29 tools make no outside calls; `ai_crawler_access_check` fetches only the public `robots.txt` and `llms.txt` of the domain you name.
- **Official MCP Registry:** `in.upgrowth/growth-toolkit`
- **Docs and setup guide:** https://upgrowth.in/growth-toolkit-mcp/
- **Terms:** https://upgrowth.in/growth-toolkit-terms/
- **Version:** 1.5.0

Built and maintained by [upGrowth Digital](https://upgrowth.in/), a growth marketing consultancy in Pune, India.

## Connect it

**Claude (all plans):** Customize > Connectors > "+ Add" > "Add custom connector". Name it upGrowth, paste the endpoint URL, choose "No sign in" under Authentication and click Add. On Team and Enterprise plans an owner adds it first under Organization settings > Connectors.

**ChatGPT (web):** Plugins > Add > "Add custom MCP server". Paste `https://mcp.upgrowth.in/mcp` as the server URL, set Authentication to "No authentication", then "Create as a plugin" and "Connect". In a chat, type `@upGrowth`. The ChatGPT address serves 28 tools (the call-booking tool is left out).

**Gemini CLI:** `gemini mcp add --transport http upgrowth https://upgrowth.in/connected-app/mcp`, or add to `~/.gemini/settings.json`:

```json
{
  "mcpServers": {
    "upgrowth": { "httpUrl": "https://upgrowth.in/connected-app/mcp" }
  }
}
```

**Claude Code:**

```bash
claude mcp add --transport http upgrowth https://upgrowth.in/connected-app/mcp
```

**Any other client:** point it at the endpoint with Streamable HTTP transport.

## Tools

### Ads, payments and unit economics
| Tool | What it does |
|---|---|
| `unit_economics` | CAC, LTV, LTV:CAC ratio and payback months |
| `max_affordable_cac` | The most you can pay per customer or per lead |
| `roas_check` | ROAS and break-even ROAS from spend, revenue and margin |
| `ad_budget_planner` | Ad budget needed to hit a customer target |
| `ppc_forecast` | Clicks, conversions, revenue and profit from a PPC budget |
| `acos_check` | Amazon ACoS, TACoS and break-even ACoS |
| `campaign_roi` | Marketing campaign ROI |
| `break_even` | Break-even units and revenue |
| `mdr_charges` | Card or payment gateway MDR, GST on it and net settlement |
| `customer_lifetime_value` | Repeat-purchase CLV, with optional discounting |

### Growth and reporting
| Tool | What it does |
|---|---|
| `growth_rate` | Period growth, CAGR and compound monthly growth |
| `period_change` | YoY, MoM or simple % change |
| `ytd_growth` | Year-to-date growth against last year |
| `growth_projection` | Revenue projection and doubling time |
| `ecommerce_metrics` | AOV, conversion rate and revenue per visitor |
| `attrition_rate` | Attrition and churn rate |
| `share_of_voice` | Share of voice across brands |

### SEO and AI visibility
| Tool | What it does |
|---|---|
| `ai_crawler_access_check` | Whether a site's robots.txt blocks GPTBot, ClaudeBot, PerplexityBot and other AI crawlers |
| `robots_txt_generator` | robots.txt that allows or blocks AI crawlers by purpose (training, search, user fetch), never Googlebot |
| `schema_generator` | JSON-LD for FAQ, LocalBusiness, Organization, Article, Product, Person and Breadcrumb |
| `meta_tags_builder` | Title, meta description, Open Graph and Twitter card tags |
| `content_seo_check` | Word count, keyword density and readability of pasted text |
| `seo_roi` | SEO return and break-even traffic |

### Creator and publisher income
| Tool | What it does |
|---|---|
| `youtube_earnings` | YouTube earnings estimate and Partner Program watch-hour progress |
| `instagram_earnings` | Instagram creator earnings estimate |
| `engagement_rate` | Engagement rate by followers or reach |
| `website_ad_revenue` | AdSense-style website ad revenue |

### Research and contact
| Tool | What it does |
|---|---|
| `find_growth_moves` | Documented 2026 growth moves by Indian companies (fintech, ecommerce, healthtech, SaaS) from upGrowth's [Growth Moves](https://upgrowth.in/growth-moves/) research, each with a source link |
| `book_strategy_call` | Returns a link to book a call with upGrowth |

Every tool is annotated `readOnlyHint: true` and `destructiveHint: false`.

## Skills

The server also publishes 2 [Agent Skills](https://agentskills.io/specification) through the MCP [Skills extension](https://modelcontextprotocol.io/extensions/skills/overview) (`io.modelcontextprotocol/skills`). A skill is an ordered workflow across the tools above. Clients that support the extension find them with `skills/list`; any client can read them as resources.

| Skill | URI | What it does |
|---|---|---|
| `ai-visibility-check` | `skill://ai-visibility-check/SKILL.md` | Checks whether AI assistants can read a site, then works through robots.txt, schema and meta tag fixes |
| `paid-growth-diagnostic` | `skill://paid-growth-diagnostic/SKILL.md` | Runs unit economics, affordable CAC, ROAS and budget in order and ends with a verdict |

Skills are text only (no scripts). Their source is in `skills/`.

## Example prompts

- "My CAC is Rs 2,000, LTV Rs 9,000 and monthly margin per customer Rs 750. What's my LTV:CAC and payback?"
- "I spent Rs 50,000 on Meta and made Rs 2,10,000 at 40% margin. Am I above break-even ROAS?"
- "Is GPTBot or ClaudeBot blocked on example.com?"
- "Write a robots.txt that blocks AI training bots but allows AI search."
- "Generate LocalBusiness schema for a dental clinic in Pune."
- "Estimate monthly YouTube earnings for 200,000 views at Rs 80 RPM."

## Repository layout

```
src/worker.mjs          Cloudflare Worker (the deployed server, single file, data and skills inlined)
skills/                 Source of the skills; `node scripts/sync-skills.mjs` copies them into the Worker
scripts/sync-skills.mjs Inlines skills/ into src/worker.mjs
php/mcp.php             PHP implementation of the same tools (legacy protocol revisions only, no skills)
php/data/               Growth Moves dataset used by find_growth_moves (PHP copy)
tests/smoke.mjs         Smoke test against src/worker.mjs
server.json             Official MCP Registry entry
wrangler.toml           Example Wrangler config
```

## Run it yourself

**Cloudflare Workers:**

```bash
npx wrangler dev          # local
npx wrangler deploy       # set your own routes in wrangler.toml first
```

**PHP 8+:** copy `php/` to any web server and POST JSON-RPC to `mcp.php`.

**Test:**

```bash
node tests/smoke.mjs
```

## Protocol notes

- Single `POST` endpoint, JSON-RPC 2.0, JSON responses (no SSE stream needed).
- Serves 2 protocol eras on the same endpoint:
  - **2026-07-28 (stateless):** `server/discover`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, `skills/list` and `skills/get`. Requests carry their version in `_meta` and the `MCP-Protocol-Version` header; an unsupported version gets error `-32022` with the supported list.
  - **2025-11-25 and earlier (`initialize` handshake):** `initialize`, `ping`, `tools/list`, `tools/call`, `resources/list` and `resources/read`. `2025-06-18` by default; the client's requested version is echoed only when it is one of `2025-11-25`, `2025-06-18`, `2025-03-26` or `2024-11-05`.
- The public endpoint is rate limited per IP. If you need heavy use, self-host it.

## Licence

MIT. See [LICENSE](LICENSE). The Growth Moves dataset text is © upGrowth Digital LLP; each entry links its original source.

## Contact

Questions, bugs or tool requests: open an issue, or email amol@upgrowth.in.
