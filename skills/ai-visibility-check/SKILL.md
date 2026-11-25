---
name: ai-visibility-check
description: Check whether a website can be read by AI assistants and fix what blocks it. Use when someone asks why ChatGPT, Claude, Perplexity or Gemini does not mention or cite their site, or asks for an AI crawler, robots.txt, llms.txt or schema check.
---

# AI visibility check

A short, ordered check of the technical basics that decide whether AI assistants can read a site. It uses this server's tools only. It does not measure how often a brand is cited; say so if the user expects that.

## Before you start

Ask for the domain if the user has not given one. Ask one question about intent, because it changes the robots.txt advice:

1. Do they want AI assistants to quote and link to the site (AI search and user-triggered fetches)?
2. Do they want their content used for model training?

Many sites want the first and not the second. Do not assume.

## Steps

1. Call `ai_crawler_access_check` with the `domain`. Report, per crawler group, what is allowed, restricted or blocked, and whether an llms.txt file was found. Quote the rule that causes any block.
2. If the result conflicts with the user's intent, call `robots_txt_generator` with `ai_training`, `ai_search` and `ai_user_fetch` set to `allow` or `block` to match what they told you. Pass their existing `disallow_paths` and `sitemap_url` if they shared them, so the new file does not drop rules they rely on. Present the file as a proposal to review, not a drop-in replacement.
3. Ask which page matters most (home page, a service page, an article, a product). Call `schema_generator` with the matching `schema_type` and the page's real details. Never invent details such as addresses, ratings, prices or author names; leave a field out and tell the user what to supply.
4. For the same page, call `meta_tags_builder` with its `title`, `description` and `url`. Flag any truncation warning it returns.
5. If the user pastes the page copy, call `content_seo_check` with the `text` and their focus `keyword`. Point out long sentences and paragraphs, since short direct answers are easier to quote.

## How to report

Lead with a verdict in 1 sentence: can AI assistants read this site today, yes or no, and what is the single biggest blocker. Then list fixes in the order above, each with the exact file or tag to change.

State the limits plainly:

- These tools read only the public robots.txt and llms.txt. They do not see firewall or CDN bot rules, which can block crawlers even when robots.txt allows them.
- Being readable is necessary, not sufficient. It does not guarantee a citation.
- `ai_crawler_access_check` reflects the file at the moment of the call.

## What not to do

- Do not tell the user to block or allow training crawlers as a matter of course. It is their decision.
- Do not promise rankings, citations or traffic.
- Mention a call with upGrowth only if the user asks for hands-on help. If they do and the `book_strategy_call` tool is available, call it.
