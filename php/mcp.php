<?php
/**
 * upGrowth Growth Toolkit: remote MCP server for Google Connected Apps.
 * Transport: Streamable HTTP (single POST endpoint, JSON responses).
 * Auth: none (public, read-only tools; no user data stored).
 * Host: upgrowth.in/connected-app/mcp.php  (PHP 7.4+)
 */

header('X-Content-Type-Options: nosniff');

const SERVER_NAME = 'upgrowth-growth-toolkit';
const SERVER_VERSION = '1.4.0';
const DEFAULT_PROTOCOL = '2025-06-18';
const BOOKING_URL = 'https://calendar.app.google/qKL9ceeHas4K4BF19';

$INSTRUCTIONS = 'upGrowth Growth Toolkit answers growth marketing maths and research questions. Use unit_economics for CAC, LTV, LTV:CAC and payback; growth_rate for period growth, CAGR or compound monthly growth; roas_check for return on ad spend and break-even ROAS; ad_budget_planner to estimate the ad budget needed for a customer target; find_growth_moves for documented 2026 growth moves by Indian companies in fintech, ecommerce, healthtech and SaaS from upGrowth\'s Growth Moves research, each with a source link; max_affordable_cac for the most a business can pay per customer or lead; period_change for YoY, MoM or simple % change; seo_roi for SEO return and break-even traffic; ai_crawler_access_check to see whether a website blocks AI crawlers in robots.txt; mdr_charges for card or payment gateway MDR, GST on it and net settlement; robots_txt_generator to write a robots.txt that allows or blocks AI crawlers by purpose; schema_generator for JSON-LD schema markup; content_seo_check for word count, keyword density and readability of pasted text; meta_tags_builder for title, description, Open Graph and Twitter tags; youtube_earnings, instagram_earnings, engagement_rate and website_ad_revenue for creator and publisher income; ytd_growth, growth_projection, ppc_forecast, customer_lifetime_value, ecommerce_metrics (AOV, conversion rate), acos_check, share_of_voice, break_even, campaign_roi and attrition_rate for business maths; book_strategy_call when the user asks to talk to upGrowth. All tools are read-only and store nothing; ai_crawler_access_check reads only the public robots.txt and llms.txt of the domain given.';

// ---------- HTTP handling ----------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Use POST with a JSON-RPC 2.0 body.']);
    exit;
}
$raw = file_get_contents('php://input');
if (strlen($raw) > 65536) { respond_error(null, -32600, 'Request too large', 413); }
$req = json_decode($raw, true);
if (!is_array($req)) { respond_error(null, -32700, 'Parse error', 400); }
if (isset($req[0])) { respond_error(null, -32600, 'Batch requests are not supported', 400); }

$id = array_key_exists('id', $req) ? $req['id'] : null;
$rpc = $req['method'] ?? '';
$params = isset($req['params']) && is_array($req['params']) ? $req['params'] : [];

// Notifications (no id) get 202 with no body.
if ($id === null && strpos($rpc, 'notifications/') === 0) { http_response_code(202); exit; }

switch ($rpc) {
    case 'initialize':
        $pv = isset($params['protocolVersion']) && is_string($params['protocolVersion']) ? $params['protocolVersion'] : DEFAULT_PROTOCOL;
        respond($id, [
            'protocolVersion' => $pv,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => SERVER_NAME, 'title' => 'upGrowth Growth Toolkit', 'version' => SERVER_VERSION],
            'instructions' => $INSTRUCTIONS,
        ]);
    case 'ping':
        respond($id, new stdClass());
    case 'tools/list':
        respond($id, ['tools' => tool_definitions()]);
    case 'tools/call':
        $name = $params['name'] ?? '';
        $args = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];
        respond($id, call_tool($name, $args));
    default:
        respond_error($id, -32601, 'Method not found: ' . $rpc, 200);
}

// ---------- JSON-RPC helpers ----------
function respond($id, $result) {
    header('Content-Type: application/json');
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function respond_error($id, $code, $message, $http = 200) {
    http_response_code($http);
    header('Content-Type: application/json');
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}
function tool_result($text, $structured) {
    return ['content' => [['type' => 'text', 'text' => $text]], 'structuredContent' => $structured, 'isError' => false];
}
function tool_error($text) {
    return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => true];
}

// ---------- Tool definitions ----------
function ro() { return ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false]; }

function tool_definitions() {
    $currency = ['type' => 'string', 'description' => 'ISO currency code for display, for example INR, USD or AED. Defaults to INR.', 'default' => 'INR'];
    return [
        [
            'name' => 'unit_economics',
            'title' => 'Unit economics (CAC, LTV, payback)',
            'description' => 'Calculates customer acquisition cost (CAC), customer lifetime value (LTV), the LTV to CAC ratio and CAC payback in months from spend, new customers, revenue per customer, gross margin and churn.',
            'inputSchema' => ['type' => 'object', 'properties' => [
                'acquisition_spend' => ['type' => 'number', 'minimum' => 0, 'description' => 'Total sales and marketing spend for the period, in currency units.'],
                'new_customers' => ['type' => 'integer', 'minimum' => 1, 'description' => 'New paying customers acquired in the same period.'],
                'monthly_revenue_per_customer' => ['type' => 'number', 'minimum' => 0, 'description' => 'Average revenue per customer per month (ARPU), in currency units.'],
                'gross_margin_pct' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100, 'description' => 'Gross margin as a percentage, for example 70.'],
                'monthly_churn_pct' => ['type' => 'number', 'exclusiveMinimum' => 0, 'maximum' => 100, 'description' => 'Share of customers lost per month as a percentage, for example 3.'],
                'currency' => $currency,
            ], 'required' => ['acquisition_spend', 'new_customers', 'monthly_revenue_per_customer', 'gross_margin_pct', 'monthly_churn_pct']],
            'annotations' => ro(),
        ],
        [
            'name' => 'growth_rate',
            'title' => 'Growth rate (period, CAGR, CMGR)',
            'description' => 'Calculates total growth and compound growth per period between a start and end value, for example month on month, quarter on quarter or year on year (CAGR).',
            'inputSchema' => ['type' => 'object', 'properties' => [
                'start_value' => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => 'Value at the start, for example revenue or users.'],
                'end_value' => ['type' => 'number', 'minimum' => 0, 'description' => 'Value at the end.'],
                'periods' => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => 'Number of periods between start and end, for example 12.'],
                'period_type' => ['type' => 'string', 'enum' => ['month', 'quarter', 'year'], 'description' => 'Length of 1 period.', 'default' => 'month'],
            ], 'required' => ['start_value', 'end_value', 'periods']],
            'annotations' => ro(),
        ],
        [
            'name' => 'roas_check',
            'title' => 'ROAS and break-even ROAS',
            'description' => 'Calculates return on ad spend from ad revenue and ad spend, and the break-even ROAS implied by gross margin, then says whether the campaign covers its product cost.',
            'inputSchema' => ['type' => 'object', 'properties' => [
                'ad_revenue' => ['type' => 'number', 'minimum' => 0, 'description' => 'Revenue attributed to the ads, in currency units.'],
                'ad_spend' => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => 'Ad spend for the same period, in currency units.'],
                'gross_margin_pct' => ['type' => 'number', 'exclusiveMinimum' => 0, 'maximum' => 100, 'description' => 'Gross margin as a percentage.'],
                'currency' => $currency,
            ], 'required' => ['ad_revenue', 'ad_spend', 'gross_margin_pct']],
            'annotations' => ro(),
        ],
        [
            'name' => 'ad_budget_planner',
            'title' => 'Ad budget planner',
            'description' => 'Estimates the monthly ad budget needed to reach a target number of new customers from cost per click, landing page conversion rate and lead to customer rate.',
            'inputSchema' => ['type' => 'object', 'properties' => [
                'target_customers' => ['type' => 'integer', 'minimum' => 1, 'description' => 'New customers wanted per month.'],
                'cost_per_click' => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => 'Expected average cost per click, in currency units.'],
                'click_to_lead_pct' => ['type' => 'number', 'exclusiveMinimum' => 0, 'maximum' => 100, 'description' => 'Share of clicks that become leads, as a percentage.'],
                'lead_to_customer_pct' => ['type' => 'number', 'exclusiveMinimum' => 0, 'maximum' => 100, 'description' => 'Share of leads that become customers, as a percentage.'],
                'currency' => $currency,
            ], 'required' => ['target_customers', 'cost_per_click', 'click_to_lead_pct', 'lead_to_customer_pct']],
            'annotations' => ro(),
        ],
        [
            'name' => 'find_growth_moves',
            'title' => 'Find Growth Moves research',
            'description' => 'Returns documented 2026 growth moves by Indian companies from upGrowth\'s Growth Moves research (January to September 2026), filtered by sector, company or keyword. Each move includes the leader, date and a link to the original source.',
            'inputSchema' => ['type' => 'object', 'properties' => [
                'sector' => ['type' => 'string', 'enum' => ['fintech', 'ecommerce', 'healthtech', 'saas', 'any'], 'description' => 'Sector to search. Use any to search all sectors.', 'default' => 'any'],
                'company' => ['type' => 'string', 'description' => 'Optional company name to match, for example Razorpay.'],
                'keyword' => ['type' => 'string', 'description' => 'Optional keyword to match in the move description, for example acquisition, UPI or AI agents.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 14, 'description' => 'Maximum moves to return.', 'default' => 5],
            ]],
            'annotations' => ro(),
        ],
        ...json_decode('[{"name": "max_affordable_cac", "title": "Maximum affordable CAC and cost per lead", "description": "Works back from customer lifetime value to the most a business can pay to acquire a customer (and, optionally, a lead) while keeping a target LTV to CAC ratio, 3:1 by default.", "inputSchema": {"type": "object", "properties": {"monthly_revenue_per_customer": {"type": "number", "exclusiveMinimum": 0, "description": "Average revenue per customer per month (ARPU), in currency units."}, "gross_margin_pct": {"type": "number", "exclusiveMinimum": 0, "maximum": 100, "description": "Gross margin as a percentage."}, "monthly_churn_pct": {"type": "number", "exclusiveMinimum": 0, "maximum": 100, "description": "Share of customers lost per month as a percentage."}, "target_ltv_to_cac": {"type": "number", "exclusiveMinimum": 0, "description": "Target LTV to CAC ratio, for example 3 for 3:1.", "default": 3}, "lead_to_customer_pct": {"type": "number", "exclusiveMinimum": 0, "maximum": 100, "description": "Optional. Share of leads that become customers, to work out the maximum cost per lead."}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["monthly_revenue_per_customer", "gross_margin_pct", "monthly_churn_pct"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "period_change", "title": "YoY, MoM or % change", "description": "Calculates the simple percentage change between 2 values, such as year over year (YoY), month over month (MoM), quarter over quarter or week over week, including sales or revenue growth and percentage increase or decrease.", "inputSchema": {"type": "object", "properties": {"previous_value": {"type": "number", "description": "Value in the earlier period."}, "current_value": {"type": "number", "description": "Value in the later period."}, "comparison": {"type": "string", "enum": ["yoy", "mom", "qoq", "wow", "custom"], "description": "Which comparison this is, used only for labelling.", "default": "custom"}}, "required": ["previous_value", "current_value"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "seo_roi", "title": "SEO ROI", "description": "Estimates the monthly return on an SEO investment from extra organic visits, conversion rate, value per conversion and monthly SEO cost, plus the break-even traffic gain.", "inputSchema": {"type": "object", "properties": {"monthly_organic_visits_gain": {"type": "number", "minimum": 0, "description": "Extra organic visits per month expected from SEO."}, "conversion_rate_pct": {"type": "number", "exclusiveMinimum": 0, "maximum": 100, "description": "Share of visits that convert, as a percentage."}, "value_per_conversion": {"type": "number", "minimum": 0, "description": "Average revenue per conversion (order value or deal value), in currency units."}, "monthly_seo_cost": {"type": "number", "exclusiveMinimum": 0, "description": "Monthly SEO spend, in currency units."}, "gross_margin_pct": {"type": "number", "exclusiveMinimum": 0, "maximum": 100, "description": "Optional gross margin as a percentage. Leave out to measure on revenue."}, "months": {"type": "number", "exclusiveMinimum": 0, "description": "Months to total over.", "default": 12}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["monthly_organic_visits_gain", "conversion_rate_pct", "value_per_conversion", "monthly_seo_cost"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "ai_crawler_access_check", "title": "AI crawler access check", "description": "Reads a website\'s public robots.txt and llms.txt and reports whether AI crawlers from OpenAI, Anthropic, Perplexity and Google (GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended and others) are allowed, restricted or blocked.", "inputSchema": {"type": "object", "properties": {"domain": {"type": "string", "description": "Public domain to check, for example example.com."}}, "required": ["domain"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": true}}]', true),
        ...json_decode('[{"name": "mdr_charges", "title": "MDR charges calculator", "description": "Calculates merchant discount rate (MDR) charges on card or payment gateway sales: the MDR fee, GST on that fee, total cost, effective rate and net settlement. Uses the MDR % the user gives; it does not look up provider rates.", "inputSchema": {"type": "object", "properties": {"sales_value": {"type": "number", "exclusiveMinimum": 0, "description": "Total value of the transactions, in currency units."}, "mdr_pct": {"type": "number", "minimum": 0, "maximum": 100, "description": "MDR or TDR charged by the provider, as a percentage, for example 1.8."}, "transactions": {"type": "integer", "minimum": 1, "description": "Optional. Number of transactions, needed only with a fixed fee per transaction."}, "fixed_fee_per_transaction": {"type": "number", "minimum": 0, "description": "Optional fixed fee per transaction, in currency units."}, "gst_pct": {"type": "number", "minimum": 0, "maximum": 100, "description": "GST charged on the MDR fee, as a percentage.", "default": 18}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["sales_value", "mdr_pct"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "robots_txt_generator", "title": "robots.txt generator for AI crawlers", "description": "Writes a robots.txt file that allows or blocks AI crawlers by purpose: model training (GPTBot, ClaudeBot, Google-Extended), AI search (OAI-SearchBot, Claude-SearchBot, PerplexityBot) and user-triggered fetches (ChatGPT-User, Claude-User, Perplexity-User), plus paths to keep private and a sitemap. Never blocks Googlebot.", "inputSchema": {"type": "object", "properties": {"ai_training": {"type": "string", "enum": ["allow", "block"], "description": "Whether AI model training crawlers may crawl the site.", "default": "allow"}, "ai_search": {"type": "string", "enum": ["allow", "block"], "description": "Whether AI search crawlers may crawl the site.", "default": "allow"}, "ai_user_fetch": {"type": "string", "enum": ["allow", "block"], "description": "Whether assistants may fetch pages when a user asks about them.", "default": "allow"}, "disallow_paths": {"type": "array", "items": {"type": "string"}, "maxItems": 20, "description": "Optional paths to keep out of all crawlers, each starting with /, for example /wp-admin/."}, "sitemap_url": {"type": "string", "description": "Optional full sitemap URL, for example https://example.com/sitemap.xml."}}}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}]', true),
        ...json_decode('[{"name": "schema_generator", "title": "Schema markup (JSON-LD) generator", "description": "Builds valid schema.org JSON-LD for a page: FAQ, local business, organization, article or blog post, product, person or breadcrumb. Checks the properties Google lists as required for that type and returns a ready-to-paste script tag.", "inputSchema": {"type": "object", "properties": {"schema_type": {"type": "string", "enum": ["faq", "local_business", "organization", "article", "product", "person", "breadcrumb"], "description": "Which schema to build."}, "data": {"type": "object", "description": "Fields for that type. faq: questions [{question, answer}]. local_business: name, business_type (for example Dentist), address {street, locality, region, postal_code, country}, telephone, url, image, price_range, geo {latitude, longitude}, opening_hours [{days: [Monday...], opens: \\"09:00\\", closes: \\"18:00\\"}]. organization: name, organization_type, url, logo, description, legal_name, founding_date, email, telephone, same_as [urls], address. article: headline, article_type (Article, BlogPosting or NewsArticle), url, images [urls], date_published, date_modified, authors [{name, url, job_title}], publisher {name, logo}. product: name, description, images, brand, sku, price, currency, availability (InStock, OutOfStock, PreOrder, SoldOut), price_valid_until, rating_value, review_count. person: name, url, job_title, works_for, image, description, same_as. breadcrumb: items [{name, url}]."}}, "required": ["schema_type", "data"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "content_seo_check", "title": "Content SEO check (word count, keyword density, readability)", "description": "Analyses text the user pastes: word, sentence and paragraph counts, reading time, approximate Flesch reading ease, long sentences and paragraphs, headings found, and focus keyword count, density and placement. Counts exactly, which assistants often get wrong.", "inputSchema": {"type": "object", "properties": {"text": {"type": "string", "description": "The article or page text, plain text, Markdown or HTML. Up to 60,000 characters."}, "keyword": {"type": "string", "description": "Optional focus keyword or phrase."}, "secondary_keywords": {"type": "array", "items": {"type": "string"}, "maxItems": 10, "description": "Optional extra keywords to count."}}, "required": ["text"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "meta_tags_builder", "title": "Meta tags builder (title, description, Open Graph, Twitter card)", "description": "Builds the HTML head tags for a page: title, meta description, canonical, Open Graph and Twitter card tags, with character counts and truncation warnings.", "inputSchema": {"type": "object", "properties": {"title": {"type": "string", "description": "Page title."}, "description": {"type": "string", "description": "Meta description."}, "url": {"type": "string", "description": "Canonical page URL."}, "image_url": {"type": "string", "description": "Optional share image URL."}, "site_name": {"type": "string", "description": "Optional site or brand name."}, "twitter_handle": {"type": "string", "description": "Optional X (Twitter) handle, for example @upgrowth."}, "page_type": {"type": "string", "enum": ["website", "article"], "default": "website", "description": "Open Graph type."}}, "required": ["title", "description", "url"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "youtube_earnings", "title": "YouTube earnings and watch hours calculator", "description": "Estimates YouTube ad earnings from monthly views and the RPM from YouTube Studio (or CPM, monetized playback % and the 55% long-form creator share), plus watch hours against the YouTube Partner Program threshold.", "inputSchema": {"type": "object", "properties": {"monthly_views": {"type": "number", "exclusiveMinimum": 0, "description": "Views per month on long-form videos."}, "rpm": {"type": "number", "minimum": 0, "description": "Revenue per 1,000 views from YouTube Studio, in currency units. Use this or cpm."}, "cpm": {"type": "number", "minimum": 0, "description": "Ad CPM, in currency units. Needs monetized_playback_pct."}, "monetized_playback_pct": {"type": "number", "minimum": 0, "maximum": 100, "description": "Share of views that show an ad, as a percentage."}, "avg_view_duration_minutes": {"type": "number", "minimum": 0, "description": "Optional average view duration in minutes, to work out watch hours."}, "subscribers": {"type": "integer", "minimum": 0, "description": "Optional subscriber count."}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["monthly_views"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "instagram_earnings", "title": "Instagram creator earnings calculator", "description": "Estimates monthly and yearly Instagram brand-deal income from the rates the user charges per post, per story or per 1,000 followers, and the cost per engagement a brand pays.", "inputSchema": {"type": "object", "properties": {"followers": {"type": "integer", "minimum": 1, "description": "Follower count."}, "posts_per_month": {"type": "number", "minimum": 0, "description": "Sponsored posts or reels per month."}, "rate_per_post": {"type": "number", "minimum": 0, "description": "Fee per sponsored post, in currency units. Use this or rate_per_1000_followers."}, "rate_per_1000_followers": {"type": "number", "minimum": 0, "description": "Fee per post per 1,000 followers, in currency units."}, "stories_per_month": {"type": "number", "minimum": 0, "description": "Optional sponsored stories per month."}, "rate_per_story": {"type": "number", "minimum": 0, "description": "Optional fee per story."}, "avg_engagements_per_post": {"type": "number", "minimum": 0, "description": "Optional average likes plus comments plus saves plus shares per post."}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["followers", "posts_per_month"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "engagement_rate", "title": "Engagement rate calculator", "description": "Calculates social media engagement rate for Instagram, YouTube, LinkedIn or any platform from likes, comments, shares and saves, by followers, reach, impressions or views.", "inputSchema": {"type": "object", "properties": {"likes": {"type": "number", "minimum": 0}, "comments": {"type": "number", "minimum": 0}, "shares": {"type": "number", "minimum": 0}, "saves": {"type": "number", "minimum": 0}, "base_type": {"type": "string", "enum": ["followers", "reach", "impressions", "views"], "default": "followers", "description": "What to divide by."}, "base_value": {"type": "number", "exclusiveMinimum": 0, "description": "Followers, reach, impressions or views."}, "posts": {"type": "integer", "minimum": 1, "default": 1, "description": "Number of posts the totals cover."}}, "required": ["base_value"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "website_ad_revenue", "title": "Website ad revenue (AdSense) calculator", "description": "Estimates display ad revenue for a website or blog from monthly pageviews and page RPM, or from ad impressions per page, CPM and fill rate.", "inputSchema": {"type": "object", "properties": {"monthly_pageviews": {"type": "number", "exclusiveMinimum": 0}, "page_rpm": {"type": "number", "minimum": 0, "description": "Earnings per 1,000 pageviews (from AdSense reports), in currency units. Use this or cpm."}, "ads_per_page": {"type": "number", "minimum": 0, "description": "Ad units per page, used with cpm."}, "cpm": {"type": "number", "minimum": 0, "description": "Earnings per 1,000 ad impressions, in currency units."}, "fill_rate_pct": {"type": "number", "minimum": 0, "maximum": 100, "default": 100, "description": "Share of ad requests filled."}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["monthly_pageviews"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "ytd_growth", "title": "Year-to-date (YTD) growth calculator", "description": "Compares year-to-date totals with the same period last year, from monthly values or YTD totals, and gives a simple full-year run rate.", "inputSchema": {"type": "object", "properties": {"this_year_monthly": {"type": "array", "items": {"type": "number"}, "maxItems": 12, "description": "This year\'s values by month so far, January first (or first month of the financial year)."}, "last_year_monthly": {"type": "array", "items": {"type": "number"}, "maxItems": 12, "description": "Last year\'s values for the same months."}, "this_ytd_total": {"type": "number", "description": "Alternative: this year\'s YTD total."}, "last_ytd_total": {"type": "number", "description": "Alternative: last year\'s total for the same period."}, "months_elapsed": {"type": "integer", "minimum": 1, "maximum": 12, "description": "Months covered, needed with totals for the run rate."}}}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "ppc_forecast", "title": "PPC and Google Ads forecast", "description": "Forecasts a paid search or social budget: clicks, conversions, cost per acquisition, revenue, ROAS, profit after ad spend and break-even CPA, from budget, CPC and conversion rate.", "inputSchema": {"type": "object", "properties": {"monthly_budget": {"type": "number", "exclusiveMinimum": 0}, "cost_per_click": {"type": "number", "exclusiveMinimum": 0}, "conversion_rate_pct": {"type": "number", "exclusiveMinimum": 0, "maximum": 100, "description": "Share of clicks that convert."}, "value_per_conversion": {"type": "number", "minimum": 0, "description": "Optional revenue per conversion."}, "gross_margin_pct": {"type": "number", "exclusiveMinimum": 0, "maximum": 100, "description": "Optional gross margin for profit and break-even CPA."}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["monthly_budget", "cost_per_click", "conversion_rate_pct"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "customer_lifetime_value", "title": "Customer lifetime value (repeat purchase)", "description": "Calculates customer lifetime value for repeat-purchase businesses such as D2C and ecommerce from average order value, purchases per year, lifespan and margin, with optional discounting and LTV to CAC.", "inputSchema": {"type": "object", "properties": {"average_order_value": {"type": "number", "exclusiveMinimum": 0}, "purchases_per_year": {"type": "number", "exclusiveMinimum": 0}, "customer_lifespan_years": {"type": "number", "exclusiveMinimum": 0}, "gross_margin_pct": {"type": "number", "exclusiveMinimum": 0, "maximum": 100}, "annual_discount_rate_pct": {"type": "number", "minimum": 0, "maximum": 100, "description": "Optional, to discount future years."}, "cac": {"type": "number", "exclusiveMinimum": 0, "description": "Optional customer acquisition cost."}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["average_order_value", "purchases_per_year", "customer_lifespan_years", "gross_margin_pct"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "ecommerce_metrics", "title": "Ecommerce metrics (AOV, conversion rate, revenue per visitor)", "description": "Calculates average order value, conversion rate, revenue per visitor and orders per customer, and the sessions needed to hit a revenue target.", "inputSchema": {"type": "object", "properties": {"revenue": {"type": "number", "minimum": 0}, "orders": {"type": "integer", "minimum": 1}, "sessions": {"type": "number", "exclusiveMinimum": 0, "description": "Optional sessions or visitors in the same period."}, "customers": {"type": "integer", "minimum": 1, "description": "Optional unique customers."}, "target_revenue": {"type": "number", "exclusiveMinimum": 0, "description": "Optional revenue goal for the same period length."}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["revenue", "orders"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "growth_projection", "title": "Growth and revenue projection", "description": "Projects a value forward at a compound growth rate per period (exponential growth), shows each period, the doubling time and how long it takes to reach a target.", "inputSchema": {"type": "object", "properties": {"start_value": {"type": "number", "exclusiveMinimum": 0}, "growth_rate_pct": {"type": "number", "exclusiveMinimum": -100, "description": "Growth per period as a percentage; negative for decline."}, "periods": {"type": "integer", "minimum": 1, "maximum": 120}, "period_type": {"type": "string", "enum": ["month", "quarter", "year"], "default": "month"}, "target_value": {"type": "number", "exclusiveMinimum": 0, "description": "Optional target to reach."}}, "required": ["start_value", "growth_rate_pct", "periods"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "acos_check", "title": "Amazon ACoS, TACoS and break-even ACoS", "description": "Calculates Amazon advertising cost of sales (ACoS), ROAS, break-even ACoS from margin, profit after ads, and TACoS against total sales.", "inputSchema": {"type": "object", "properties": {"ad_spend": {"type": "number", "exclusiveMinimum": 0}, "ad_sales": {"type": "number", "exclusiveMinimum": 0}, "gross_margin_pct": {"type": "number", "exclusiveMinimum": 0, "maximum": 100, "description": "Optional margin before ad costs."}, "total_sales": {"type": "number", "exclusiveMinimum": 0, "description": "Optional total sales including organic, for TACoS."}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["ad_spend", "ad_sales"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "share_of_voice", "title": "Share of voice calculator", "description": "Calculates share of voice for a brand against competitors on any metric: AI assistant citations or mentions, search impressions, ad impressions, social mentions or press coverage.", "inputSchema": {"type": "object", "properties": {"brand_name": {"type": "string"}, "brand_value": {"type": "number", "minimum": 0}, "competitors": {"type": "array", "maxItems": 20, "items": {"type": "object", "properties": {"name": {"type": "string"}, "value": {"type": "number", "minimum": 0}}, "required": ["name", "value"]}}, "metric": {"type": "string", "description": "What was counted, for example ChatGPT citations or impressions."}}, "required": ["brand_name", "brand_value", "competitors"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "break_even", "title": "Break-even calculator", "description": "Calculates break-even units and revenue from fixed costs, price and variable cost per unit, the contribution margin, and units needed for a target profit.", "inputSchema": {"type": "object", "properties": {"fixed_costs": {"type": "number", "minimum": 0}, "price_per_unit": {"type": "number", "exclusiveMinimum": 0}, "variable_cost_per_unit": {"type": "number", "minimum": 0}, "target_profit": {"type": "number", "minimum": 0, "description": "Optional profit goal."}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["fixed_costs", "price_per_unit", "variable_cost_per_unit"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "campaign_roi", "title": "Marketing campaign ROI", "description": "Calculates ROI for a marketing campaign or channel (content, email, events, influencer, recruitment marketing) on revenue and on gross profit.", "inputSchema": {"type": "object", "properties": {"cost": {"type": "number", "exclusiveMinimum": 0, "description": "Total campaign cost."}, "attributed_revenue": {"type": "number", "minimum": 0}, "gross_margin_pct": {"type": "number", "exclusiveMinimum": 0, "maximum": 100, "description": "Optional margin for profit-based ROI."}, "campaign_name": {"type": "string", "description": "Optional label."}, "currency": {"type": "string", "description": "ISO currency code for display, for example INR, USD or AED. Defaults to INR.", "default": "INR"}}, "required": ["cost", "attributed_revenue"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}, {"name": "attrition_rate", "title": "Attrition and churn rate calculator", "description": "Calculates employee attrition or customer churn rate for a period from separations and headcount at the start and end, with a simple annualised rate.", "inputSchema": {"type": "object", "properties": {"separations": {"type": "number", "minimum": 0, "description": "People or customers who left in the period."}, "headcount_start": {"type": "number", "minimum": 0}, "headcount_end": {"type": "number", "minimum": 0}, "period_months": {"type": "number", "exclusiveMinimum": 0, "maximum": 12, "default": 1}}, "required": ["separations", "headcount_start", "headcount_end"]}, "annotations": {"readOnlyHint": true, "destructiveHint": false, "idempotentHint": true, "openWorldHint": false}}]', true),
        [
            'name' => 'book_strategy_call',
            'title' => 'Book a strategy call with upGrowth',
            'description' => 'Returns the link to book a free 30-minute growth strategy call with upGrowth. Use only when the user asks to speak with upGrowth.',
            'inputSchema' => ['type' => 'object', 'properties' => new stdClass()],
            'annotations' => ro(),
        ],
    ];
}

// ---------- Tool implementations ----------
function num($a, $k) { return (isset($a[$k]) && is_numeric($a[$k])) ? (float)$a[$k] : null; }
function money($v, $cur) {
    $dec = abs($v) >= 100 ? 0 : 2;
    if ($cur !== 'INR') return $cur . ' ' . number_format($v, $dec);
    $neg = $v < 0 ? '-' : '';
    $parts = explode('.', number_format(abs($v), $dec, '.', ''));
    $int = $parts[0];
    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int = $rest . ',' . $last3;
    }
    return 'Rs ' . $neg . $int . (isset($parts[1]) ? '.' . $parts[1] : '');
}
function cur($a) {
    $c = isset($a['currency']) && is_string($a['currency']) ? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $a['currency']), 0, 3)) : 'INR';
    return $c === '' ? 'INR' : $c;
}


// ---------- v1.2 tools ----------
function trim_num($v, $d) { $s = number_format($v, $d); if (strpos($s, '.') !== false) $s = rtrim(rtrim($s, '0'), '.'); return $s; }
function t_max_cac($a) {
    $arpu = num($a, 'monthly_revenue_per_customer'); $gm = num($a, 'gross_margin_pct'); $churn = num($a, 'monthly_churn_pct'); $c = cur($a);
    $ratio = num($a, 'target_ltv_to_cac'); if ($ratio === null) $ratio = 3.0;
    $l2c = num($a, 'lead_to_customer_pct');
    if ($arpu === null || $gm === null || $churn === null) return tool_error('Missing a required number.');
    if ($arpu <= 0 || $gm <= 0 || $gm > 100 || $churn <= 0 || $churn > 100 || $ratio <= 0 || ($l2c !== null && ($l2c <= 0 || $l2c > 100))) return tool_error('Revenue, margin and churn must be above 0; percentages at most 100; target ratio above 0.');
    $gp = $arpu * $gm / 100; $life = 100 / $churn; $ltv = $gp * $life; $max = $ltv / $ratio; $pb = $max / $gp;
    $text = sprintf("LTV (gross margin basis): %s over about %.1f months\nMaximum affordable CAC at %s:1 LTV:CAC: %s\nCAC payback at that CAC: %s months", money($ltv, $c), $life, trim_num($ratio, 1), money($max, $c), number_format($pb, 1));
    $out = ['currency' => $c, 'ltv' => round($ltv, 2), 'target_ltv_to_cac' => $ratio, 'max_cac' => round($max, 2), 'payback_months_at_max_cac' => round($pb, 1)];
    if ($l2c !== null) { $cpl = $max * $l2c / 100; $text .= sprintf("\nMaximum affordable cost per lead at %s%% lead to customer: %s", trim_num($l2c, 1), money($cpl, $c)); $out['max_cpl'] = round($cpl, 2); }
    $text .= "\nAbove these numbers, each new customer earns back less than the target ratio. Ratios vary by business model and stage.";
    return tool_result($text, $out);
}
function t_change($a) {
    $p = num($a, 'previous_value'); $n = num($a, 'current_value');
    $lab = isset($a['comparison']) && in_array($a['comparison'], ['yoy', 'mom', 'qoq', 'wow', 'custom'], true) ? $a['comparison'] : 'custom';
    if ($p === null || $n === null) return tool_error('Missing a required number.');
    if ($p == 0) return tool_error('Previous value is 0, so the % change is undefined. Report the absolute change instead.');
    $d = $n - $p; $pct = $d / abs($p) * 100;
    $names = ['yoy' => 'Year over year (YoY)', 'mom' => 'Month over month (MoM)', 'qoq' => 'Quarter over quarter (QoQ)', 'wow' => 'Week over week (WoW)', 'custom' => 'Change'];
    $text = sprintf("%s change: %s%s%%\nAbsolute change: %s%s (from %s to %s)\nFormula: (current - previous) / previous x 100", $names[$lab], $pct >= 0 ? '+' : '', number_format($pct, 2), $d >= 0 ? '+' : '', trim_num($d, 2), trim_num($p, 2), trim_num($n, 2));
    return tool_result($text, ['comparison' => $lab, 'previous_value' => $p, 'current_value' => $n, 'absolute_change' => round($d, 2), 'pct_change' => round($pct, 2)]);
}
function t_seo_roi($a) {
    $v = num($a, 'monthly_organic_visits_gain'); $cr = num($a, 'conversion_rate_pct'); $val = num($a, 'value_per_conversion'); $cost = num($a, 'monthly_seo_cost'); $c = cur($a);
    $gm = num($a, 'gross_margin_pct'); if ($gm === null) $gm = 100.0; $m = num($a, 'months'); if ($m === null) $m = 12.0;
    if ($v === null || $cr === null || $val === null || $cost === null) return tool_error('Missing a required number.');
    if ($v < 0 || $cr <= 0 || $cr > 100 || $val < 0 || $cost <= 0 || $gm <= 0 || $gm > 100 || $m <= 0) return tool_error('Cost and conversion rate must be above 0; percentages at most 100.');
    $conv = $v * $cr / 100; $rev = $conv * $val; $gp = $rev * $gm / 100; $roi = ($gp - $cost) / $cost * 100; $tc = $cost * $m; $tg = $gp * $m; $be = $cost / (($cr / 100) * $val * $gm / 100);
    $text = sprintf("Extra conversions a month: %s\nExtra revenue a month: %s%s\nMonthly SEO ROI: %s%% on %s a month\nOver %s months: %s %s against %s cost\nBreak-even: about %s extra organic visits a month\nThis assumes the traffic gain is already reached; SEO gains usually build over several months.",
        number_format($conv, 1), money($rev, $c), $gm < 100 ? sprintf(' (gross profit %s at %s%% margin)', money($gp, $c), trim_num($gm, 1)) : '', number_format($roi, 1), money($cost, $c), trim_num($m, 1), money($tg, $c), $gm < 100 ? 'gross profit' : 'revenue', money($tc, $c), number_format(ceil($be)));
    return tool_result($text, ['currency' => $c, 'conversions_per_month' => round($conv, 2), 'revenue_per_month' => round($rev, 2), 'gross_profit_per_month' => round($gp, 2), 'monthly_roi_pct' => round($roi, 2), 'months' => $m, 'total_cost' => round($tc, 2), 'total_return' => round($tg, 2), 'break_even_visits_per_month' => (int)ceil($be)]);
}
function ai_bots() { return [
    ['GPTBot', 'OpenAI', 'content that may be used to train OpenAI models'], ['OAI-SearchBot', 'OpenAI', 'showing sites in ChatGPT search results'],
    ['ChatGPT-User', 'OpenAI', 'pages fetched when a ChatGPT user asks a question'], ['ClaudeBot', 'Anthropic', 'public web content for training Anthropic models'],
    ['Claude-SearchBot', 'Anthropic', 'improving the relevance of Claude search results'], ['Claude-User', 'Anthropic', 'pages fetched when a Claude user asks a question'],
    ['PerplexityBot', 'Perplexity', 'showing and linking sites in Perplexity search results'], ['Google-Extended', 'Google', 'use of content for Gemini training and grounding (not Google Search)'],
    ['Googlebot', 'Google', 'Google Search crawling']]; }
function parse_robots($txt) {
    $groups = []; $cur = -1; $lastUA = false;
    foreach (preg_split('/\r?\n/', $txt) as $line) {
        $line = trim(preg_replace('/#.*$/', '', $line)); if ($line === '') continue;
        if (!preg_match('/^([A-Za-z-]+)\s*:\s*(.*)$/', $line, $m)) continue;
        $k = strtolower($m[1]); $v = trim($m[2]);
        if ($k === 'user-agent') { if ($cur < 0 || !$lastUA) { $groups[] = ['agents' => [], 'rules' => []]; $cur = count($groups) - 1; } $groups[$cur]['agents'][] = strtolower($v); $lastUA = true; }
        else { $lastUA = false; if ($cur >= 0 && ($k === 'allow' || $k === 'disallow')) $groups[$cur]['rules'][] = [$k, $v]; }
    }
    return $groups;
}
function bot_status($groups, $bot) {
    $b = strtolower($bot);
    $g = array_filter($groups, function ($x) use ($b) { foreach ($x['agents'] as $u) if ($u !== '*' && strpos($b, $u) !== false) return true; return false; });
    if (!$g) $g = array_filter($groups, function ($x) { return in_array('*', $x['agents'], true); });
    if (!$g) return ['status' => 'allowed'];
    $rules = []; foreach ($g as $x) $rules = array_merge($rules, $x['rules']);
    $dis = array_values(array_filter($rules, function ($r) { return $r[0] === 'disallow' && $r[1] !== ''; }));
    $rootB = false; foreach ($dis as $r) if ($r[1] === '/' || $r[1] === '/*') $rootB = true;
    $rootA = false; foreach ($rules as $r) if ($r[0] === 'allow' && in_array($r[1], ['/', '/*', '/$'], true)) $rootA = true;
    if ($rootB && !$rootA) return ['status' => 'blocked'];
    if ($dis) return ['status' => 'allowed', 'excluded_paths' => array_slice(array_map(function ($r) { return $r[1]; }, $dis), 0, 5), 'excluded_count' => count($dis)];
    return ['status' => 'allowed'];
}
function get_text($url) {
    $ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true, 'follow_location' => 1, 'user_agent' => 'upGrowth-Growth-Toolkit/1.4 (+https://upgrowth.in/growth-toolkit-mcp/)']]);
    $body = @file_get_contents($url, false, $ctx, 0, 200000);
    if ($body === false || !isset($http_response_header)) return [0, '', ''];
    $status = 0; $type = '';
    foreach ($http_response_header as $h) { if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1]; if (stripos($h, 'content-type:') === 0) $type = trim(substr($h, 13)); }
    return [$status, $status >= 200 && $status < 300 ? $body : '', $type];
}
function t_crawler($a) {
    $host = isset($a['domain']) && is_string($a['domain']) ? strtolower(trim($a['domain'])) : '';
    $host = preg_replace('#[/?\#].*$#', '', preg_replace('#^https?://#', '', $host));
    if (!preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) return tool_error('Give a public domain name such as example.com (no IP addresses, ports or paths).');
    [$rs, $rb] = get_text("https://$host/robots.txt"); [$ls, $lb, $lt] = get_text("https://$host/llms.txt");
    if ($rs === 0) return tool_error("Could not reach https://$host/robots.txt. Check the domain is live over HTTPS.");
    if (!in_array($rs, [200, 404, 410], true)) return tool_error("robots.txt on $host answered HTTP $rs to our checker. That usually means a firewall or bot rule blocked the request, so the crawler rules can't be read and this check won't guess. Open https://$host/robots.txt in a browser to see them.");
    $has = $rs === 200 && trim($rb) !== ''; $groups = $has ? parse_robots($rb) : [];
    $bots = []; $blocked = []; $lines = [];
    foreach (ai_bots() as [$n, $o, $p]) {
        $st = bot_status($groups, $n); $bots[] = array_merge(['bot' => $n, 'owner' => $o, 'purpose' => $p], $st);
        if ($st['status'] === 'blocked') $blocked[] = $n;
        $ex = isset($st['excluded_count']) ? sprintf(' (excludes %d path%s, e.g. %s)', $st['excluded_count'], $st['excluded_count'] > 1 ? 's' : '', implode(', ', array_slice($st['excluded_paths'], 0, 3))) : '';
        $lines[] = sprintf('%s (%s): %s%s. Controls %s.', $n, $o, strtoupper($st['status']), $ex, $p);
    }
    $hasL = $ls === 200 && trim($lb) !== '' && stripos($lt, 'text/html') === false;
    $text = sprintf("AI crawler access for %s (robots.txt %s):\n%s\nllms.txt: %s\n%s\nNote: Perplexity-User and other user-triggered fetchers may not follow robots.txt. This check reads robots.txt only, not firewall or CDN bot rules.",
        $host, $has ? 'found' : ($rs === 200 ? 'empty, so all bots are allowed' : 'not found, so all bots are allowed'), implode("\n", $lines), $hasL ? 'present' : 'not found',
        $blocked ? 'Blocked: ' . implode(', ', $blocked) . ". Our reading: assistants that rely on a blocked bot can't read fresh pages from this site, which limits how they can cite it." : 'No AI bot is fully blocked.');
    return tool_result($text, ['domain' => $host, 'robots_txt_found' => $has, 'robots_status' => $rs, 'llms_txt_found' => $hasL, 'bots' => $bots, 'blocked' => $blocked]);
}


// ---------- v1.3 tools ----------
function t_mdr($a) {
    $v = num($a, 'sales_value'); $mdr = num($a, 'mdr_pct'); $n = num($a, 'transactions'); $fee = num($a, 'fixed_fee_per_transaction'); $c = cur($a);
    $gst = num($a, 'gst_pct'); if ($gst === null) $gst = 18.0;
    if ($v === null || $mdr === null) return tool_error('Missing a required number.');
    if ($v <= 0 || $mdr < 0 || $mdr > 100 || $gst < 0 || $gst > 100 || ($n !== null && $n < 1) || ($fee !== null && $fee < 0)) return tool_error('Sales value must be above 0; percentages between 0 and 100.');
    if ($fee !== null && $fee > 0 && $n === null) return tool_error('A fixed fee per transaction needs the number of transactions.');
    $fixed = ($fee && $n) ? $fee * $n : 0; $mdrFee = $v * $mdr / 100 + $fixed; $tax = $mdrFee * $gst / 100; $total = $mdrFee + $tax; $eff = $total / $v * 100; $net = $v - $total;
    $text = sprintf("MDR fee: %s (%s%% of %s%s)\nGST at %s%% on the fee: %s\nTotal cost: %s, an effective rate of %s%%\nNet settlement: %s\nGST-registered businesses can usually claim GST on MDR as input tax credit. UPI, RuPay and card rates differ, so use your provider's current rate card.",
        money($mdrFee, $c), trim_num($mdr, 2), money($v, $c), $fixed ? ' plus ' . money($fixed, $c) . ' in fixed fees' : '', trim_num($gst, 1), money($tax, $c), money($total, $c), number_format($eff, 2), money($net, $c));
    return tool_result($text, ['currency' => $c, 'sales_value' => $v, 'mdr_fee' => round($mdrFee, 2), 'gst' => round($tax, 2), 'total_cost' => round($total, 2), 'effective_rate_pct' => round($eff, 2), 'net_settlement' => round($net, 2)]);
}
function t_robots($a) {
    $groups = ['ai_training' => ['AI model training', ['GPTBot', 'ClaudeBot', 'Google-Extended']], 'ai_search' => ['AI search results', ['OAI-SearchBot', 'Claude-SearchBot', 'PerplexityBot']], 'ai_user_fetch' => ['user-triggered fetches', ['ChatGPT-User', 'Claude-User', 'Perplexity-User']]];
    $raw = isset($a['disallow_paths']) && is_array($a['disallow_paths']) ? $a['disallow_paths'] : [];
    if (count($raw) > 20) return tool_error('Give at most 20 paths.');
    $paths = [];
    foreach ($raw as $p) { if (!is_string($p) || !preg_match('#^/[^\s\#]{0,200}$#', trim($p))) return tool_error('Each path must start with / and contain no spaces, for example /wp-admin/.'); if (!in_array(trim($p), $paths, true)) $paths[] = trim($p); }
    $sitemap = '';
    if (isset($a['sitemap_url']) && $a['sitemap_url'] !== '') { if (!is_string($a['sitemap_url']) || !preg_match('#^https?://\S{3,300}$#', trim($a['sitemap_url']))) return tool_error('The sitemap must be a full URL such as https://example.com/sitemap.xml.'); $sitemap = trim($a['sitemap_url']); }
    $lines = ['# robots.txt generated by the upGrowth Growth Toolkit (https://upgrowth.in/growth-toolkit-mcp/)', '', 'User-agent: *'];
    if ($paths) foreach ($paths as $p) $lines[] = 'Disallow: ' . $p; else $lines[] = 'Allow: /';
    $blocked = []; $summary = [];
    foreach ($groups as $k => [$label, $bots]) {
        $summary[$k] = (isset($a[$k]) && $a[$k] === 'block') ? 'block' : 'allow';
        if ($summary[$k] === 'block') { $blocked = array_merge($blocked, $bots); $lines[] = ''; $lines[] = "# Block $label"; foreach ($bots as $b) $lines[] = 'User-agent: ' . $b; $lines[] = 'Disallow: /'; }
    }
    if ($sitemap) { $lines[] = ''; $lines[] = 'Sitemap: ' . $sitemap; }
    $file = implode("\n", $lines) . "\n";
    $notes = ['Upload this as robots.txt at the root of your domain. It never blocks Googlebot or Google Search.'];
    if ($summary['ai_training'] === 'block') $notes[] = 'Google-Extended controls use of your content for Gemini training and grounding; it does not affect Google Search.';
    if ($summary['ai_search'] === 'block') $notes[] = "Our reading: blocking AI search crawlers keeps your pages out of ChatGPT search, Claude search and Perplexity results, so those assistants can't cite you.";
    if ($summary['ai_user_fetch'] === 'block') $notes[] = 'Perplexity-User and some other user-triggered fetchers may not follow robots.txt.';
    $notes[] = 'robots.txt is a request, not access control. Check the result with ai_crawler_access_check once it is live.';
    return tool_result("```\n" . $file . "```\n" . implode("\n", $notes), ['robots_txt' => $file, 'choices' => $summary, 'blocked_bots' => $blocked, 'disallow_paths' => $paths, 'sitemap_url' => $sitemap ?: null]);
}


// ---------- v1.4 tools ----------
function pctf($v, $d = 2) { return number_format($v, $d) . '%'; }
function sv($v, $max = 2000) { return is_string($v) ? mb_substr(trim($v), 0, $max) : ''; }
function is_url($v) { return is_string($v) && preg_match('#^https?://[^\s<>"]{3,500}$#', trim($v)); }
function is_date($v) { return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}([T ][0-9:.+\-Z]*)?$/', trim($v)); }
function ld_json($o) { $j = preg_replace_callback('/^ +/m', function ($m) { return str_repeat(' ', intdiv(strlen($m[0]), 2)); }, json_encode($o, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); return "<script type=\"application/ld+json\">\n" . str_replace('<', '\\u003c', $j) . "\n</script>"; }
function arr_get($a, $k) { return isset($a[$k]) ? $a[$k] : null; }
function t_schema($a) {
    $t = arr_get($a, 'schema_type'); $d = is_array(arr_get($a, 'data')) ? $a['data'] : [];
    $warn = []; $notes = [];
    $urls = function ($x) { return is_array($x) ? array_slice(array_values(array_map('trim', array_filter($x, 'is_url'))), 0, 10) : []; };
    $addr = function ($x) { if (!is_array($x)) return null; $o = array_filter(['@type' => 'PostalAddress', 'streetAddress' => sv(arr_get($x, 'street')), 'addressLocality' => sv(arr_get($x, 'locality')), 'addressRegion' => sv(arr_get($x, 'region')), 'postalCode' => sv(arr_get($x, 'postal_code')), 'addressCountry' => sv(arr_get($x, 'country'))], function ($v) { return $v !== ''; }); return $o; };
    $o = null;
    if ($t === 'faq') {
        $qs = array_slice(array_values(array_filter(is_array(arr_get($d, 'questions')) ? $d['questions'] : [], function ($q) { return is_array($q) && sv(arr_get($q, 'question')) && sv(arr_get($q, 'answer')); })), 0, 30);
        if (!$qs) return tool_error('Give data.questions as a list of {question, answer}.');
        $o = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($q) { return ['@type' => 'Question', 'name' => sv($q['question'], 500), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => sv($q['answer'], 3000)]]; }, $qs)];
        $notes[] = 'Google stopped showing FAQ rich results in Search on 7 May 2026, so this markup will not produce an FAQ snippet. Our reading: it still describes your questions and answers in a structured way for other systems that read the page.';
    } elseif ($t === 'local_business') {
        $ad = $addr(arr_get($d, 'address'));
        if (!sv(arr_get($d, 'name')) || !$ad || count($ad) < 2) return tool_error('Local business needs data.name and data.address (Google lists both as required).');
        $type = preg_match('/^[A-Za-z]{3,40}$/', sv(arr_get($d, 'business_type'))) ? sv($d['business_type']) : 'LocalBusiness';
        $o = ['@context' => 'https://schema.org', '@type' => $type, 'name' => sv($d['name']), 'address' => $ad];
        if (is_url(arr_get($d, 'url'))) $o['url'] = trim($d['url']); else $warn[] = 'url';
        if (sv(arr_get($d, 'telephone'))) $o['telephone'] = sv($d['telephone'], 40); else $warn[] = 'telephone';
        if (is_url(arr_get($d, 'image'))) $o['image'] = trim($d['image']);
        if (sv(arr_get($d, 'price_range'))) $o['priceRange'] = sv($d['price_range'], 40);
        $g = arr_get($d, 'geo');
        if (is_array($g) && num($g, 'latitude') !== null && num($g, 'longitude') !== null) $o['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => num($g, 'latitude'), 'longitude' => num($g, 'longitude')]; else $warn[] = 'geo';
        $DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']; $oh = [];
        foreach (is_array(arr_get($d, 'opening_hours')) ? $d['opening_hours'] : [] as $h) {
            if (!is_array($h)) continue;
            $days = array_values(array_filter(is_array(arr_get($h, 'days')) ? $h['days'] : [arr_get($h, 'days')], function ($x) use ($DAYS) { return in_array($x, $DAYS, true); }));
            $op = preg_match('/^\d{2}:\d{2}$/', (string)arr_get($h, 'opens')) ? $h['opens'] : ''; $cl = preg_match('/^\d{2}:\d{2}$/', (string)arr_get($h, 'closes')) ? $h['closes'] : '';
            if ($days && $op && $cl) $oh[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days, 'opens' => $op, 'closes' => $cl];
        }
        if ($oh) $o['openingHoursSpecification'] = $oh; else $warn[] = 'opening_hours';
    } elseif ($t === 'organization') {
        if (!sv(arr_get($d, 'name'))) return tool_error('Give data.name.');
        $type = preg_match('/^[A-Za-z]{3,40}$/', sv(arr_get($d, 'organization_type'))) ? sv($d['organization_type']) : 'Organization';
        $o = ['@context' => 'https://schema.org', '@type' => $type, 'name' => sv($d['name'])];
        if (sv(arr_get($d, 'legal_name'))) $o['legalName'] = sv($d['legal_name']);
        if (is_url(arr_get($d, 'url'))) $o['url'] = trim($d['url']); else $warn[] = 'url';
        if (is_url(arr_get($d, 'logo'))) $o['logo'] = trim($d['logo']); else $warn[] = 'logo';
        if (sv(arr_get($d, 'description'))) $o['description'] = sv($d['description']);
        if (is_date(arr_get($d, 'founding_date')) || preg_match('/^\d{4}$/', sv(arr_get($d, 'founding_date')))) $o['foundingDate'] = sv($d['founding_date']);
        if (sv(arr_get($d, 'email'))) $o['email'] = sv($d['email'], 120);
        if (sv(arr_get($d, 'telephone'))) $o['telephone'] = sv($d['telephone'], 40);
        $ad = $addr(arr_get($d, 'address')); if ($ad && count($ad) > 1) $o['address'] = $ad; else $warn[] = 'address';
        $sa = $urls(arr_get($d, 'same_as')); if ($sa) $o['sameAs'] = $sa; else $warn[] = 'same_as';
    } elseif ($t === 'article') {
        if (!sv(arr_get($d, 'headline'))) return tool_error('Give data.headline.');
        $type = in_array(arr_get($d, 'article_type'), ['Article', 'BlogPosting', 'NewsArticle'], true) ? $d['article_type'] : 'Article';
        $o = ['@context' => 'https://schema.org', '@type' => $type, 'headline' => sv($d['headline'], 300)];
        if (is_url(arr_get($d, 'url'))) $o['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => trim($d['url'])];
        $im = $urls(arr_get($d, 'images')); if ($im) $o['image'] = $im; else $warn[] = 'images';
        if (is_date(arr_get($d, 'date_published'))) $o['datePublished'] = trim($d['date_published']); else $warn[] = 'date_published';
        if (is_date(arr_get($d, 'date_modified'))) $o['dateModified'] = trim($d['date_modified']);
        $au = [];
        foreach (array_slice(is_array(arr_get($d, 'authors')) ? $d['authors'] : [], 0, 10) as $x) {
            if (!is_array($x) || !sv(arr_get($x, 'name'))) continue;
            $au[] = array_filter(['@type' => 'Person', 'name' => sv($x['name'], 120), 'jobTitle' => sv(arr_get($x, 'job_title'), 120), 'url' => is_url(arr_get($x, 'url')) ? trim($x['url']) : ''], function ($v) { return $v !== ''; });
        }
        if ($au) $o['author'] = $au; else $warn[] = 'authors';
        $pub = arr_get($d, 'publisher');
        if (is_array($pub) && sv(arr_get($pub, 'name'))) { $o['publisher'] = ['@type' => 'Organization', 'name' => sv($pub['name'])]; if (is_url(arr_get($pub, 'logo'))) $o['publisher']['logo'] = ['@type' => 'ImageObject', 'url' => trim($pub['logo'])]; }
        if (mb_strlen(sv($d['headline'])) > 110) $notes[] = "Long headlines may be cut off on some devices, per Google's Article guidance.";
    } elseif ($t === 'product') {
        if (!sv(arr_get($d, 'name'))) return tool_error('Give data.name.');
        $price = num($d, 'price'); $rv = num($d, 'rating_value'); $rc = num($d, 'review_count');
        if ($price === null && ($rv === null || $rc === null)) return tool_error('Product needs a price or a rating (rating_value and review_count): Google requires offers, review or aggregateRating.');
        $o = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => sv($d['name'], 300)];
        if (sv(arr_get($d, 'description'))) $o['description'] = sv($d['description']);
        $im = $urls(arr_get($d, 'images')); if ($im) $o['image'] = $im; else $warn[] = 'images';
        if (sv(arr_get($d, 'brand'))) $o['brand'] = ['@type' => 'Brand', 'name' => sv($d['brand'], 120)];
        if (sv(arr_get($d, 'sku'))) $o['sku'] = sv($d['sku'], 80);
        if ($price !== null) {
            $o['offers'] = ['@type' => 'Offer', 'price' => $price + 0, 'priceCurrency' => cur($d)];
            $av = ['InStock', 'OutOfStock', 'PreOrder', 'SoldOut', 'BackOrder', 'LimitedAvailability', 'OnlineOnly', 'InStoreOnly', 'Discontinued'];
            if (in_array(arr_get($d, 'availability'), $av, true)) $o['offers']['availability'] = 'https://schema.org/' . $d['availability']; else $warn[] = 'availability';
            if (is_date(arr_get($d, 'price_valid_until'))) $o['offers']['priceValidUntil'] = trim($d['price_valid_until']);
            if ($rv === null) $notes[] = "Google's Rich Results Test may warn about offers without review or aggregateRating.";
        }
        if ($rv !== null && $rc !== null && $rc > 0) $o['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $rv + 0, 'reviewCount' => (int)round($rc)];
        $notes[] = 'Only mark up ratings that real customers left on this page.';
    } elseif ($t === 'person') {
        if (!sv(arr_get($d, 'name'))) return tool_error('Give data.name.');
        $o = ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => sv($d['name'], 120)];
        if (is_url(arr_get($d, 'url'))) $o['url'] = trim($d['url']); else $warn[] = 'url';
        if (sv(arr_get($d, 'job_title'))) $o['jobTitle'] = sv($d['job_title'], 120);
        if (sv(arr_get($d, 'works_for'))) $o['worksFor'] = ['@type' => 'Organization', 'name' => sv($d['works_for'], 120)];
        if (is_url(arr_get($d, 'image'))) $o['image'] = trim($d['image']);
        if (sv(arr_get($d, 'description'))) $o['description'] = sv($d['description']);
        $sa = $urls(arr_get($d, 'same_as')); if ($sa) $o['sameAs'] = $sa; else $warn[] = 'same_as';
    } elseif ($t === 'breadcrumb') {
        $items = array_slice(array_values(array_filter(is_array(arr_get($d, 'items')) ? $d['items'] : [], function ($x) { return is_array($x) && sv(arr_get($x, 'name')) && is_url(arr_get($x, 'url')); })), 0, 20);
        if (count($items) < 2) return tool_error('Give data.items as at least 2 {name, url} entries, home page first.');
        $el = []; foreach ($items as $i => $x) $el[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => sv($x['name'], 120), 'item' => trim($x['url'])];
        $o = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $el];
    } else return tool_error('schema_type must be faq, local_business, organization, article, product, person or breadcrumb.');
    if ($warn) $notes[] = 'Recommended but missing: ' . implode(', ', $warn) . '.';
    $notes[] = "Paste the script tag into the page HTML, then check it with Google's Rich Results Test or validator.schema.org. Markup must match what is visible on the page.";
    return tool_result("```html\n" . ld_json($o) . "\n```\n" . implode("\n", $notes), ['schema_type' => $t, 'json_ld' => $o, 'missing_recommended' => $warn]);
}
function syl_count($w) { $w = preg_replace('/[^a-z]/', '', strtolower($w)); if ($w === '') return 0; if (strlen($w) <= 3) return 1; $w = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $w); $w = preg_replace('/^y/', '', $w); return preg_match_all('/[aeiouy]{1,2}/', $w) ?: 1; }
function word_list($s) { preg_match_all("/[A-Za-z0-9\x{00C0}-\x{024F}'’-]+/u", $s, $m); return $m[0]; }
function t_content($a) {
    $raw = is_string(arr_get($a, 'text')) ? $a['text'] : '';
    if (trim($raw) === '') return tool_error('Give the text to check.');
    if (mb_strlen($raw) > 60000) return tool_error('Text is over 60,000 characters. Check it in parts.');
    $headings = preg_match_all('/<h[1-6][^>]*>|^#{1,6}\s/im', $raw); $h1 = preg_match_all('/<h1[^>]*>|^#\s/im', $raw);
    $text = preg_replace('/<(script|style)[^>]*>[\s\S]*?<\/\1>/i', ' ', $raw);
    $text = preg_replace('/<\/(p|div|h[1-6]|li|br)>|<br\s*\/?>/i', "\n\n", $text);
    $text = preg_replace('/<[^>]+>/', ' ', $text); $text = str_replace(['&nbsp;', '&amp;'], [' ', '&'], $text);
    $text = preg_replace('/^#{1,6}\s+/m', '', $text); $text = preg_replace('/[*_`>]/', ' ', $text);
    $words = word_list($text); $wc = count($words); if (!$wc) return tool_error('No words found in the text.');
    $sentences = array_values(array_filter(array_map('trim', preg_split('/[.!?]+(?:\s|$)/', $text)), function ($s) { return preg_match('/[A-Za-z0-9]/', $s); })); $sc = max(1, count($sentences));
    $paras = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $text)), function ($p) { return preg_match('/[A-Za-z0-9]/', $p); }));
    $longS = count(array_filter($sentences, function ($s) { return count(word_list($s)) > 25; }));
    $longP = count(array_filter($paras, function ($p) { return count(word_list($p)) > 100; }));
    $sy = 0; foreach ($words as $w) $sy += syl_count($w);
    $flesch = 206.835 - 1.015 * ($wc / $sc) - 84.6 * ($sy / $wc);
    $band = $flesch >= 70 ? 'easy' : ($flesch >= 50 ? 'fairly difficult' : ($flesch >= 30 ? 'difficult' : 'very difficult'));
    $lower = ' ' . mb_strtolower(implode(' ', $words)) . ' '; $first100 = ' ' . mb_strtolower(implode(' ', array_slice($words, 0, 100))) . ' ';
    $countKw = function ($k) use ($lower, $first100, $wc) { $kk = mb_strtolower(implode(' ', word_list($k))); if ($kk === '') return null; $needle = ' ' . $kk . ' '; $n = 0; $i = 0; while (($i = mb_strpos($lower, $needle, $i)) !== false) { $n++; $i += mb_strlen($needle) - 1; } return ['keyword' => trim($k), 'count' => $n, 'density_pct' => round($n * count(explode(' ', $kk)) / $wc * 100, 2), 'in_first_100_words' => mb_strpos($first100, $needle) !== false]; };
    $rt = max(1, (int)round($wc / 200));
    $lines = [sprintf('Words: %s | Sentences: %s | Paragraphs: %s | Headings found: %d%s', number_format($wc), number_format($sc), number_format(count($paras)), $headings, $h1 > 1 ? " ($h1 H1s; most pages use 1)" : ''),
        "Reading time: about $rt min at 200 words a minute",
        sprintf('Average sentence length: %s words; sentences over 25 words: %d; paragraphs over 100 words: %d', number_format($wc / $sc, 1), $longS, $longP),
        sprintf('Flesch reading ease (approximate, English): %s, %s', number_format($flesch, 0), $band)];
    $kws = [];
    if (is_string(arr_get($a, 'keyword')) && trim($a['keyword']) !== '') { $k = $countKw($a['keyword']); if ($k) { $kws[] = $k; $lines[] = sprintf('Focus keyword "%s": %d times, %s%% of words, %s the first 100 words', $k['keyword'], $k['count'], $k['density_pct'], $k['in_first_100_words'] ? 'in' : 'NOT in'); } }
    foreach (array_slice(is_array(arr_get($a, 'secondary_keywords')) ? $a['secondary_keywords'] : [], 0, 10) as $s) { if (!is_string($s)) continue; $k = $countKw($s); if ($k) { $kws[] = $k; $lines[] = sprintf('"%s": %d times, %s%%', $k['keyword'], $k['count'], $k['density_pct']); } }
    $lines[] = "There is no official ideal keyword density; Google's spam policies warn against keyword stuffing, so write for the reader first.";
    return tool_result(implode("\n", $lines), ['words' => $wc, 'sentences' => $sc, 'paragraphs' => count($paras), 'headings' => $headings, 'reading_time_minutes' => $rt, 'avg_sentence_words' => round($wc / $sc, 1), 'long_sentences' => $longS, 'long_paragraphs' => $longP, 'flesch_reading_ease' => round($flesch, 1), 'keywords' => $kws]);
}
function h_esc($s) { return htmlspecialchars((string)$s, ENT_COMPAT | ENT_HTML401, 'UTF-8', true); }
function t_meta($a) {
    $title = sv(arr_get($a, 'title'), 300); $desc = sv(arr_get($a, 'description'), 1000);
    if ($title === '' || $desc === '') return tool_error('Give a title and a description.');
    if (!is_url(arr_get($a, 'url'))) return tool_error('Give the full page URL, for example https://example.com/page/.');
    $url = trim($a['url']); $img = is_url(arr_get($a, 'image_url')) ? trim($a['image_url']) : ''; $site = sv(arr_get($a, 'site_name'), 100);
    $tw = preg_match('/^@?[A-Za-z0-9_]{1,15}$/', sv(arr_get($a, 'twitter_handle'))) ? '@' . ltrim(sv($a['twitter_handle']), '@') : '';
    $type = arr_get($a, 'page_type') === 'article' ? 'article' : 'website';
    $tags = ['<title>' . h_esc($title) . '</title>', '<meta name="description" content="' . h_esc($desc) . '">', '<link rel="canonical" href="' . h_esc($url) . '">',
        '<meta property="og:type" content="' . $type . '">', '<meta property="og:title" content="' . h_esc($title) . '">', '<meta property="og:description" content="' . h_esc($desc) . '">', '<meta property="og:url" content="' . h_esc($url) . '">'];
    if ($site) $tags[] = '<meta property="og:site_name" content="' . h_esc($site) . '">';
    if ($img) $tags[] = '<meta property="og:image" content="' . h_esc($img) . '">';
    $tags[] = '<meta name="twitter:card" content="' . ($img ? 'summary_large_image' : 'summary') . '">'; $tags[] = '<meta name="twitter:title" content="' . h_esc($title) . '">'; $tags[] = '<meta name="twitter:description" content="' . h_esc($desc) . '">';
    if ($img) $tags[] = '<meta name="twitter:image" content="' . h_esc($img) . '">';
    if ($tw) $tags[] = '<meta name="twitter:site" content="' . h_esc($tw) . '">';
    $tl = mb_strlen($title); $dl = mb_strlen($desc);
    $notes = [sprintf('Title: %d characters%s. Description: %d characters%s.', $tl, $tl > 60 ? ' (over 60, likely to be cut off in search results)' : '', $dl, $dl > 160 ? ' (over 160, likely to be cut off)' : ($dl < 70 ? ' (short; there may be room to say more)' : '')),
        'The 60 and 160 character marks are our rule of thumb: Google truncates by pixel width, not a fixed count, and may rewrite snippets.'];
    if (!$img) $notes[] = 'No share image given, so links will show a small card without a picture.';
    return tool_result("```html\n" . implode("\n", $tags) . "\n```\n" . implode("\n", $notes), ['tags' => implode("\n", $tags), 'title_length' => $tl, 'description_length' => $dl]);
}
function t_youtube($a) {
    $v = num($a, 'monthly_views'); $rpm = num($a, 'rpm'); $cpm = num($a, 'cpm'); $mp = num($a, 'monetized_playback_pct'); $dur = num($a, 'avg_view_duration_minutes'); $subs = num($a, 'subscribers'); $c = cur($a);
    if ($v === null || $v <= 0) return tool_error('Give monthly_views above 0.');
    if ($rpm !== null) { $m = $v * $rpm / 1000; $how = money($rpm, $c) . ' RPM'; }
    elseif ($cpm !== null && $mp !== null) { $m = $v * ($mp / 100) * $cpm / 1000 * 0.55; $how = money($cpm, $c) . ' CPM on ' . trim_num($mp, 1) . '% monetized playbacks, at the 55% long-form creator share'; }
    else return tool_error('Give rpm (from YouTube Studio), or cpm with monetized_playback_pct.');
    $lines = [sprintf('Estimated earnings: %s a month, %s a year (%s views a month at %s)', money($m, $c), money($m * 12, $c), number_format($v), $how)];
    $out = ['currency' => $c, 'monthly_earnings' => round($m, 2), 'yearly_earnings' => round($m * 12, 2)];
    if ($dur !== null && $dur > 0) {
        $wh = $v * $dur / 60; $yr = $wh * 12; $out['monthly_watch_hours'] = round($wh, 1); $out['yearly_watch_hours'] = round($yr, 1);
        $lines[] = sprintf('Watch hours: about %s a month, %s over 12 months at this pace (the YouTube Partner Program asks for 4,000 public long-form watch hours in 12 months%s)', number_format($wh), number_format($yr), $subs !== null ? ' and 1,000 subscribers; you have ' . number_format($subs) : ' plus 1,000 subscribers');
        $out['meets_watch_hours'] = $yr >= 4000; if ($subs !== null) $out['meets_subscribers'] = $subs >= 1000;
    }
    $lines[] = 'RPM varies by audience country, niche and season. Use the RPM from your own YouTube Studio for a real estimate. Shorts earn from a separate pool at a 45% creator share and are not included here.';
    return tool_result(implode("\n", $lines), $out);
}
function t_insta($a) {
    $f = num($a, 'followers'); $ppm = num($a, 'posts_per_month'); $rpp = num($a, 'rate_per_post'); $r1k = num($a, 'rate_per_1000_followers'); $spm = num($a, 'stories_per_month'); $rps = num($a, 'rate_per_story'); $eng = num($a, 'avg_engagements_per_post'); $c = cur($a);
    if ($f === null || $f < 1 || $ppm === null || $ppm < 0) return tool_error('Give followers and posts_per_month.');
    $fee = $rpp !== null ? $rpp : ($r1k !== null ? $f / 1000 * $r1k : null);
    if ($fee === null) return tool_error('Give rate_per_post or rate_per_1000_followers. This tool models your own rates; it does not guess market rates.');
    $posts = $fee * $ppm; $stories = ($spm !== null && $rps !== null) ? $spm * $rps : 0; $m = $posts + $stories;
    $lines = ['Fee per post: ' . money($fee, $c) . ($rpp === null ? ' (' . money($r1k, $c) . ' per 1,000 of ' . number_format($f) . ' followers)' : ''), sprintf('Monthly income: %s (posts %s%s), %s a year', money($m, $c), money($posts, $c), $stories ? ', stories ' . money($stories, $c) : '', money($m * 12, $c))];
    $out = ['currency' => $c, 'fee_per_post' => round($fee, 2), 'monthly_income' => round($m, 2), 'yearly_income' => round($m * 12, 2)];
    if ($eng !== null && $eng > 0) { $lines[] = 'Cost per engagement for a brand: ' . money($fee / $eng, $c) . '; engagement rate per post: ' . pctf($eng / $f * 100); $out['cost_per_engagement'] = round($fee / $eng, 2); $out['engagement_rate_pct'] = round($eng / $f * 100, 2); }
    $lines[] = 'This models brand-deal income from the rates you enter. It does not estimate payouts from Meta programmes.';
    return tool_result(implode("\n", $lines), $out);
}
function t_engage($a) {
    $parts = array_map(function ($k) use ($a) { return num($a, $k) ?: 0; }, ['likes', 'comments', 'shares', 'saves']); $base = num($a, 'base_value'); $posts = num($a, 'posts') ?: 1;
    $bt = in_array(arr_get($a, 'base_type'), ['followers', 'reach', 'impressions', 'views'], true) ? $a['base_type'] : 'followers';
    if ($base === null || $base <= 0) return tool_error('Give base_value above 0.');
    $tot = array_sum($parts); if (!$tot) return tool_error('Give at least 1 of likes, comments, shares or saves.');
    $er = $tot / $base * 100; $per = $er / $posts;
    return tool_result(sprintf("Engagement rate by %s: %s across %s post%s%s\nInteractions: %s (likes %s, comments %s, shares %s, saves %s)\nFormula: interactions / %s%s x 100. Rates by reach or views run higher than by followers, so compare like with like.",
        $bt, pctf($er), number_format($posts), $posts > 1 ? 's' : '', $posts > 1 ? ', ' . pctf($per) . ' per post' : '', number_format($tot), number_format($parts[0]), number_format($parts[1]), number_format($parts[2]), number_format($parts[3]), $bt, $posts > 1 ? ' / posts' : ''),
        ['base_type' => $bt, 'interactions' => $tot, 'engagement_rate_pct' => round($er, 2), 'per_post_pct' => round($per, 2)]);
}
function t_adrev($a) {
    $pv = num($a, 'monthly_pageviews'); $rpm = num($a, 'page_rpm'); $apg = num($a, 'ads_per_page'); $cpm = num($a, 'cpm'); $fill = num($a, 'fill_rate_pct'); if ($fill === null) $fill = 100; $c = cur($a);
    if ($pv === null || $pv <= 0) return tool_error('Give monthly_pageviews above 0.');
    if ($rpm !== null) { $m = $pv * $rpm / 1000; $how = money($rpm, $c) . ' page RPM'; }
    elseif ($apg !== null && $cpm !== null) { $m = $pv * $apg * ($fill / 100) * $cpm / 1000; $how = trim_num($apg, 1) . ' ads a page, ' . trim_num($fill, 1) . '% fill, ' . money($cpm, $c) . ' CPM'; }
    else return tool_error('Give page_rpm, or ads_per_page with cpm.');
    return tool_result(sprintf("Estimated ad revenue: %s a month, %s a year (%s pageviews at %s)\nEffective page RPM: %s\nRPM varies with audience country, niche, season and ad layout. Use the page RPM from your own ad reports for a real estimate.", money($m, $c), money($m * 12, $c), number_format($pv), $how, money($m / $pv * 1000, $c)),
        ['currency' => $c, 'monthly_revenue' => round($m, 2), 'yearly_revenue' => round($m * 12, 2), 'effective_page_rpm' => round($m / $pv * 1000, 2)]);
}
function t_ytd($a) {
    $tm = arr_get($a, 'this_year_monthly'); $lm = arr_get($a, 'last_year_monthly');
    if (is_array($tm) && count($tm)) {
        foreach ($tm as $x) if (!is_numeric($x)) return tool_error('this_year_monthly must be up to 12 numbers.');
        if (count($tm) > 12) return tool_error('this_year_monthly must be up to 12 numbers.');
        if (!is_array($lm) || count($lm) < count($tm)) return tool_error('last_year_monthly needs at least as many months as this_year_monthly.');
        foreach ($lm as $x) if (!is_numeric($x)) return tool_error('last_year_monthly needs at least as many months as this_year_monthly.');
        $n = count($tm); $ty = array_sum(array_map('floatval', $tm)); $ly = array_sum(array_map('floatval', array_slice($lm, 0, $n)));
    } else { $ty = num($a, 'this_ytd_total'); $ly = num($a, 'last_ytd_total'); $n = num($a, 'months_elapsed'); if ($ty === null || $ly === null) return tool_error('Give monthly arrays, or this_ytd_total and last_ytd_total.'); }
    if ($ly == 0) return tool_error("Last year's YTD total is 0, so growth % is undefined.");
    $g = ($ty - $ly) / abs($ly) * 100;
    $lines = [sprintf('YTD this year: %s vs %s for the same %s last year', trim_num($ty, 2), trim_num($ly, 2), $n ? (int)$n . ' months' : 'period'), sprintf('YTD growth: %s%s (%s%s)', $g >= 0 ? '+' : '', pctf($g), $ty - $ly >= 0 ? '+' : '', trim_num($ty - $ly, 2))];
    $out = ['this_ytd' => round($ty, 2), 'last_ytd' => round($ly, 2), 'ytd_growth_pct' => round($g, 2)];
    if ($n) { $rr = $ty / $n * 12; $lines[] = 'Simple full-year run rate: ' . trim_num($rr, 0) . ' (YTD average x 12; ignores seasonality)'; $out['run_rate'] = round($rr, 2); $out['months'] = $n; }
    return tool_result(implode("\n", $lines), $out);
}
function t_ppc($a) {
    $b = num($a, 'monthly_budget'); $cpc = num($a, 'cost_per_click'); $cr = num($a, 'conversion_rate_pct'); $val = num($a, 'value_per_conversion'); $gm = num($a, 'gross_margin_pct'); $c = cur($a);
    if ($b === null || $cpc === null || $cr === null || $b <= 0 || $cpc <= 0 || $cr <= 0 || $cr > 100) return tool_error('Give budget, CPC and conversion rate above 0.');
    $clicks = $b / $cpc; $conv = $clicks * $cr / 100; $cpa = $b / $conv;
    $lines = [sprintf('Clicks: %s | Conversions: %s | Cost per acquisition: %s', number_format($clicks), number_format($conv, 1), money($cpa, $c))];
    $out = ['currency' => $c, 'clicks' => (int)round($clicks), 'conversions' => round($conv, 1), 'cpa' => round($cpa, 2)];
    if ($val !== null) {
        $rev = $conv * $val; $roas = $rev / $b; $lines[] = sprintf('Revenue: %s | ROAS: %sx', money($rev, $c), number_format($roas, 2)); $out['revenue'] = round($rev, 2); $out['roas'] = round($roas, 2);
        if ($gm !== null) { $gp = $rev * $gm / 100 - $b; $be = $val * $gm / 100; $lines[] = sprintf('Gross profit after ad spend: %s | Break-even CPA: %s (%s)', money($gp, $c), money($be, $c), $cpa <= $be ? 'this forecast is under it' : 'this forecast is over it, so each sale loses money'); $out['profit_after_ads'] = round($gp, 2); $out['break_even_cpa'] = round($be, 2); }
    }
    $lines[] = 'A forecast, not a guarantee: CPC and conversion rate move with competition, quality and landing page.';
    return tool_result(implode("\n", $lines), $out);
}
function t_clv($a) {
    $aov = num($a, 'average_order_value'); $f = num($a, 'purchases_per_year'); $y = num($a, 'customer_lifespan_years'); $gm = num($a, 'gross_margin_pct'); $dr = num($a, 'annual_discount_rate_pct'); $cac = num($a, 'cac'); $c = cur($a);
    if ($aov === null || $f === null || $y === null || $gm === null || $aov <= 0 || $f <= 0 || $y <= 0 || $gm <= 0 || $gm > 100) return tool_error('Give AOV, purchases a year, lifespan and margin above 0.');
    $annual = $aov * $f; $rev = $annual * $y; $gp = $rev * $gm / 100;
    $lines = ['Revenue per customer per year: ' . money($annual, $c), sprintf('Lifetime revenue: %s | Lifetime gross profit (CLV): %s over %s years', money($rev, $c), money($gp, $c), trim_num($y, 1))];
    $out = ['currency' => $c, 'annual_revenue' => round($annual, 2), 'lifetime_revenue' => round($rev, 2), 'clv_gross_profit' => round($gp, 2)]; $base = $gp;
    if ($dr !== null && $dr > 0) { $pv = 0; $ag = $annual * $gm / 100; $full = (int)floor($y); for ($i = 1; $i <= $full; $i++) $pv += $ag / pow(1 + $dr / 100, $i); if ($y > $full) $pv += $ag * ($y - $full) / pow(1 + $dr / 100, $full + 1); $lines[] = 'Discounted CLV at ' . trim_num($dr, 1) . '% a year: ' . money($pv, $c); $out['clv_discounted'] = round($pv, 2); $base = $pv; }
    if ($cac !== null && $cac > 0) { $lines[] = sprintf('LTV:CAC: %s:1 at %s CAC', number_format($base / $cac, 2), money($cac, $c)); $out['ltv_to_cac'] = round($base / $cac, 2); }
    return tool_result(implode("\n", $lines), $out);
}
function t_ecom($a) {
    $rev = num($a, 'revenue'); $o = num($a, 'orders'); $s = num($a, 'sessions'); $cu = num($a, 'customers'); $tgt = num($a, 'target_revenue'); $c = cur($a);
    if ($rev === null || $o === null || $o < 1 || $rev < 0) return tool_error('Give revenue and at least 1 order.');
    $aov = $rev / $o; $lines = ['Average order value: ' . money($aov, $c)]; $out = ['currency' => $c, 'aov' => round($aov, 2)];
    if ($s !== null && $s > 0) {
        $cr = $o / $s * 100; $rpv = $rev / $s; $lines[] = 'Conversion rate: ' . pctf($cr) . ' | Revenue per visitor: ' . money($rpv, $c); $out['conversion_rate_pct'] = round($cr, 2); $out['revenue_per_visitor'] = round($rpv, 2);
        if ($tgt !== null) { $need = $tgt / $rpv; $lines[] = sprintf("To reach %s: about %s sessions at today's conversion rate and AOV (%s more), or lift revenue per visitor to %s on today's traffic", money($tgt, $c), number_format(ceil($need)), number_format(ceil($need - $s)), money($tgt / $s, $c)); $out['sessions_needed'] = (int)ceil($need); }
    } elseif ($tgt !== null) $lines[] = 'Add sessions to work out the traffic needed for the target.';
    if ($cu !== null && $cu > 0) { $lines[] = 'Orders per customer: ' . number_format($o / $cu, 2); $out['orders_per_customer'] = round($o / $cu, 2); }
    return tool_result(implode("\n", $lines), $out);
}
function t_project($a) {
    $s = num($a, 'start_value'); $g = num($a, 'growth_rate_pct'); $n = num($a, 'periods'); $t = num($a, 'target_value');
    $pt = in_array(arr_get($a, 'period_type'), ['month', 'quarter', 'year'], true) ? $a['period_type'] : 'month';
    if ($s === null || $g === null || $n === null || $s <= 0 || $g <= -100 || $n < 1 || $n > 120) return tool_error('Give start_value above 0, growth_rate_pct above -100 and 1 to 120 periods.');
    $r = $g / 100; $nn = (int)round($n); $end = $s * pow(1 + $r, $nn);
    $lines = [sprintf('After %d %ss at %s%s%% a %s: %s (from %s, %s%% total)', $nn, $pt, $g >= 0 ? '+' : '', trim_num($g, 2), $pt, trim_num($end, 2), trim_num($s, 2), number_format(($end / $s - 1) * 100, 1))];
    if ($n <= 24) { $bp = []; for ($i = 1; $i <= $nn; $i++) $bp[] = $i . ': ' . trim_num($s * pow(1 + $r, $i), 0); $lines[] = 'By period: ' . implode(', ', $bp); }
    $out = ['period_type' => $pt, 'end_value' => round($end, 2), 'total_growth_pct' => round(($end / $s - 1) * 100, 2)];
    if ($r > 0) { $dt = log(2) / log(1 + $r); $lines[] = 'Doubling time: ' . number_format($dt, 1) . " {$pt}s"; $out['doubling_periods'] = round($dt, 1); }
    if ($t !== null) { if (($t > $s && $r > 0) || ($t < $s && $r < 0)) { $k = log($t / $s) / log(1 + $r); $lines[] = 'Reaches ' . trim_num($t, 2) . ' after ' . number_format($k, 1) . " {$pt}s"; $out['periods_to_target'] = round($k, 1); } elseif ($t != $s) $lines[] = 'At this rate the value never reaches ' . trim_num($t, 2) . '.'; }
    return tool_result(implode("\n", $lines), $out);
}
function t_acos($a) {
    $sp = num($a, 'ad_spend'); $sa = num($a, 'ad_sales'); $gm = num($a, 'gross_margin_pct'); $ts = num($a, 'total_sales'); $c = cur($a);
    if ($sp === null || $sa === null || $sp <= 0 || $sa <= 0) return tool_error('Give ad_spend and ad_sales above 0.');
    $acos = $sp / $sa * 100; $lines = ['ACoS: ' . pctf($acos) . ' | ROAS: ' . number_format($sa / $sp, 2) . 'x']; $out = ['currency' => $c, 'acos_pct' => round($acos, 2), 'roas' => round($sa / $sp, 2)];
    if ($gm !== null) { $p = $sa * $gm / 100 - $sp; $lines[] = 'Break-even ACoS: ' . pctf($gm) . ' (your margin before ads). ' . ($acos <= $gm ? 'You are under it' : 'You are over it, so ad-driven sales lose money') . '; profit after ads: ' . money($p, $c); $out['break_even_acos_pct'] = $gm; $out['profit_after_ads'] = round($p, 2); }
    if ($ts !== null) { if ($ts < $sa) return tool_error('total_sales should include ad sales, so it cannot be lower.'); $lines[] = 'TACoS: ' . pctf($sp / $ts * 100) . ' of total sales; ads drive ' . pctf($sa / $ts * 100) . ' of sales'; $out['tacos_pct'] = round($sp / $ts * 100, 2); }
    return tool_result(implode("\n", $lines), $out);
}
function t_sov($a) {
    $bn = sv(arr_get($a, 'brand_name'), 80); $bv = num($a, 'brand_value');
    if ($bn === '' || $bv === null || $bv < 0) return tool_error('Give brand_name and brand_value.');
    $comps = [];
    foreach (array_slice(is_array(arr_get($a, 'competitors')) ? $a['competitors'] : [], 0, 40) as $x) { if (is_array($x) && sv(arr_get($x, 'name')) && is_numeric(arr_get($x, 'value')) && $x['value'] >= 0) $comps[] = ['name' => sv($x['name'], 80), 'value' => (float)$x['value']]; if (count($comps) >= 20) break; }
    if (!$comps) return tool_error('Give at least 1 competitor with a name and value.');
    $all = array_merge([['name' => $bn, 'value' => $bv, 'brand' => true]], $comps); $tot = array_sum(array_column($all, 'value'));
    if (!$tot) return tool_error('All values are 0.');
    usort($all, function ($x, $y) { return $y['value'] <=> $x['value']; });
    $rows = []; foreach ($all as $i => $x) $rows[] = ['rank' => $i + 1, 'name' => $x['name'], 'value' => $x['value'], 'share_pct' => round($x['value'] / $tot * 100, 2), 'brand' => !empty($x['brand'])];
    $me = null; foreach ($rows as $r) if ($r['brand']) $me = $r;
    $metric = sv(arr_get($a, 'metric'), 80) ?: 'the metric';
    $text = sprintf("%s share of voice on %s: %s, rank %d of %d\n", $bn, $metric, pctf($me['share_pct']), $me['rank'], count($rows)) . implode("\n", array_map(function ($x) { return sprintf('%d. %s: %s (%s)', $x['rank'], $x['name'], pctf($x['share_pct']), trim_num($x['value'], 2)); }, $rows));
    return tool_result($text, ['metric' => $metric, 'total' => $tot, 'brand_share_pct' => $me['share_pct'], 'brand_rank' => $me['rank'], 'rows' => $rows]);
}
function t_break_even($a) {
    $fc = num($a, 'fixed_costs'); $p = num($a, 'price_per_unit'); $vc = num($a, 'variable_cost_per_unit'); $tp = num($a, 'target_profit'); $c = cur($a);
    if ($fc === null || $p === null || $vc === null || $fc < 0 || $p <= 0 || $vc < 0) return tool_error('Give fixed costs, price and variable cost.');
    $cm = $p - $vc; if ($cm <= 0) return tool_error('Price is at or below variable cost, so the business never breaks even. Raise price or cut unit cost.');
    $u = (int)ceil($fc / $cm); $lines = ['Contribution margin: ' . money($cm, $c) . ' a unit (' . pctf($cm / $p * 100) . ')', 'Break-even: ' . number_format($u) . ' units, ' . money($u * $p, $c) . ' revenue'];
    $out = ['currency' => $c, 'contribution_margin' => round($cm, 2), 'contribution_margin_pct' => round($cm / $p * 100, 2), 'break_even_units' => $u, 'break_even_revenue' => round($u * $p, 2)];
    if ($tp !== null && $tp > 0) { $ut = (int)ceil(($fc + $tp) / $cm); $lines[] = 'For ' . money($tp, $c) . ' profit: ' . number_format($ut) . ' units, ' . money($ut * $p, $c) . ' revenue'; $out['units_for_target'] = $ut; }
    return tool_result(implode("\n", $lines), $out);
}
function t_camp_roi($a) {
    $cost = num($a, 'cost'); $rev = num($a, 'attributed_revenue'); $gm = num($a, 'gross_margin_pct'); $c = cur($a); $nm = sv(arr_get($a, 'campaign_name'), 80);
    if ($cost === null || $rev === null || $cost <= 0 || $rev < 0) return tool_error('Give cost above 0 and attributed_revenue.');
    $roi = ($rev - $cost) / $cost * 100;
    $lines = [sprintf('%sROI on revenue: %s%s (%s revenue on %s cost, %sx)', $nm ? $nm . ': ' : '', $roi >= 0 ? '+' : '', pctf($roi, 1), money($rev, $c), money($cost, $c), number_format($rev / $cost, 2))];
    $out = ['currency' => $c, 'roi_on_revenue_pct' => round($roi, 1), 'revenue_multiple' => round($rev / $cost, 2)];
    if ($gm !== null) { $gp = $rev * $gm / 100; $r = ($gp - $cost) / $cost * 100; $lines[] = sprintf('ROI on gross profit: %s%s (gross profit %s, net %s)', $r >= 0 ? '+' : '', pctf($r, 1), money($gp, $c), money($gp - $cost, $c)); $out['roi_on_profit_pct'] = round($r, 1); $out['net_profit'] = round($gp - $cost, 2); }
    $lines[] = 'ROI is only as good as the attribution behind the revenue figure.';
    return tool_result(implode("\n", $lines), $out);
}
function t_attrition($a) {
    $s = num($a, 'separations'); $h0 = num($a, 'headcount_start'); $h1 = num($a, 'headcount_end'); $m = num($a, 'period_months') ?: 1;
    if ($s === null || $h0 === null || $h1 === null || $s < 0 || $h0 < 0 || $h1 < 0 || $m <= 0 || $m > 12) return tool_error('Give separations, start and end headcount, and a period of up to 12 months.');
    $avg = ($h0 + $h1) / 2; if ($avg <= 0) return tool_error('Average headcount is 0.');
    $r = $s / $avg * 100; $ann = $r * 12 / $m;
    return tool_result(sprintf("Attrition rate: %s over %s month%s (%s left, average headcount %s)\nAnnualised (simple, x %s): %s\nFormula: separations / average of start and end headcount x 100. The same maths works for customer churn.", pctf($r), trim_num($m, 1), $m > 1 ? 's' : '', trim_num($s, 0), trim_num($avg, 1), trim_num(12 / $m, 2), pctf($ann)),
        ['attrition_pct' => round($r, 2), 'annualised_pct' => round($ann, 2), 'average_headcount' => round($avg, 1), 'period_months' => $m]);
}

function call_tool($name, $a) {
    switch ($name) {
        case 'unit_economics': return t_unit_economics($a);
        case 'growth_rate': return t_growth_rate($a);
        case 'roas_check': return t_roas($a);
        case 'ad_budget_planner': return t_budget($a);
        case 'find_growth_moves': return t_moves($a);
        case 'max_affordable_cac': return t_max_cac($a);
        case 'period_change': return t_change($a);
        case 'seo_roi': return t_seo_roi($a);
        case 'ai_crawler_access_check': return t_crawler($a);
        case 'mdr_charges': return t_mdr($a);
        case 'robots_txt_generator': return t_robots($a);
        case 'schema_generator': return t_schema($a);
        case 'content_seo_check': return t_content($a);
        case 'meta_tags_builder': return t_meta($a);
        case 'youtube_earnings': return t_youtube($a);
        case 'instagram_earnings': return t_insta($a);
        case 'engagement_rate': return t_engage($a);
        case 'website_ad_revenue': return t_adrev($a);
        case 'ytd_growth': return t_ytd($a);
        case 'ppc_forecast': return t_ppc($a);
        case 'customer_lifetime_value': return t_clv($a);
        case 'ecommerce_metrics': return t_ecom($a);
        case 'growth_projection': return t_project($a);
        case 'acos_check': return t_acos($a);
        case 'share_of_voice': return t_sov($a);
        case 'break_even': return t_break_even($a);
        case 'campaign_roi': return t_camp_roi($a);
        case 'attrition_rate': return t_attrition($a);
        case 'book_strategy_call':
            return tool_result('Book a free 30-minute growth strategy call with upGrowth: ' . BOOKING_URL, ['booking_url' => BOOKING_URL, 'duration_minutes' => 30]);
        default: return tool_error('Unknown tool: ' . $name);
    }
}

function t_unit_economics($a) {
    $spend = num($a, 'acquisition_spend'); $n = num($a, 'new_customers'); $arpu = num($a, 'monthly_revenue_per_customer');
    $gm = num($a, 'gross_margin_pct'); $churn = num($a, 'monthly_churn_pct'); $c = cur($a);
    if ($spend === null || $n === null || $arpu === null || $gm === null || $churn === null) return tool_error('Missing a required number.');
    if ($n < 1 || $churn <= 0 || $churn > 100 || $gm < 0 || $gm > 100 || $spend < 0 || $arpu < 0) return tool_error('Check the inputs: customers must be at least 1, churn between 0 and 100, margin between 0 and 100.');
    $cac = $spend / $n;
    $monthlyGp = $arpu * $gm / 100;
    $lifetime = 100 / $churn;
    $ltv = $monthlyGp * $lifetime;
    $ratio = $cac > 0 ? $ltv / $cac : null;
    $payback = $monthlyGp > 0 ? $cac / $monthlyGp : null;
    if ($ratio === null) $band = 'CAC is zero, so the ratio is not meaningful.';
    elseif ($ratio >= 3) $band = 'At or above the common 3:1 rule of thumb.';
    elseif ($ratio >= 1) $band = 'Positive but below the common 3:1 rule of thumb.';
    else $band = 'Below 1:1, so each customer costs more to acquire than it returns in gross profit.';
    $text = sprintf("CAC: %s\nLTV (gross margin basis): %s over about %.1f months\nLTV:CAC: %s\nCAC payback: %s\n%s Rules of thumb vary by business model and stage.",
        money($cac, $c), money($ltv, $c), $lifetime,
        $ratio === null ? 'n/a' : number_format($ratio, 2) . ':1',
        $payback === null ? 'never (no gross profit per month)' : number_format($payback, 1) . ' months', $band);
    return tool_result($text, ['currency' => $c, 'cac' => round($cac, 2), 'ltv' => round($ltv, 2), 'customer_lifetime_months' => round($lifetime, 1),
        'ltv_to_cac' => $ratio === null ? null : round($ratio, 2), 'payback_months' => $payback === null ? null : round($payback, 1), 'assessment' => $band]);
}

function t_growth_rate($a) {
    $s = num($a, 'start_value'); $e = num($a, 'end_value'); $p = num($a, 'periods');
    $type = isset($a['period_type']) && in_array($a['period_type'], ['month', 'quarter', 'year'], true) ? $a['period_type'] : 'month';
    if ($s === null || $e === null || $p === null || $s <= 0 || $e < 0 || $p <= 0) return tool_error('Start must be above 0, end 0 or more, periods above 0.');
    $total = ($e / $s - 1) * 100;
    $cg = (pow($e / $s, 1 / $p) - 1) * 100;
    $label = ['month' => 'compound monthly growth rate (CMGR)', 'quarter' => 'compound quarterly growth rate', 'year' => 'compound annual growth rate (CAGR)'][$type];
    $text = sprintf("Total change: %s%%\n%s: %s%% per %s over %s %ss", number_format($total, 2), ucfirst($label), number_format($cg, 2), $type, rtrim(rtrim(number_format($p, 2), '0'), '.'), $type);
    return tool_result($text, ['total_growth_pct' => round($total, 2), 'compound_growth_pct_per_period' => round($cg, 2), 'period_type' => $type]);
}

function t_roas($a) {
    $r = num($a, 'ad_revenue'); $s = num($a, 'ad_spend'); $gm = num($a, 'gross_margin_pct'); $c = cur($a);
    if ($r === null || $s === null || $gm === null || $s <= 0 || $gm <= 0 || $gm > 100 || $r < 0) return tool_error('Spend must be above 0 and margin between 0 and 100.');
    $roas = $r / $s; $be = 100 / $gm;
    $profit = $r * $gm / 100 - $s;
    $verdict = $roas >= $be ? 'Above break-even: gross profit from these sales covers the ad spend.' : 'Below break-even: gross profit from these sales does not cover the ad spend.';
    $text = sprintf("ROAS: %sx\nBreak-even ROAS at %s%% margin: %sx\nGross profit after ad spend: %s\n%s", number_format($roas, 2), rtrim(rtrim(number_format($gm, 1), '0'), '.'), number_format($be, 2), money($profit, $c), $verdict);
    return tool_result($text, ['currency' => $c, 'roas' => round($roas, 2), 'break_even_roas' => round($be, 2), 'gross_profit_after_ad_spend' => round($profit, 2), 'above_break_even' => $roas >= $be]);
}

function t_budget($a) {
    $t = num($a, 'target_customers'); $cpc = num($a, 'cost_per_click'); $cl = num($a, 'click_to_lead_pct'); $lc = num($a, 'lead_to_customer_pct'); $c = cur($a);
    if ($t === null || $cpc === null || $cl === null || $lc === null || $t < 1 || $cpc <= 0 || $cl <= 0 || $cl > 100 || $lc <= 0 || $lc > 100) return tool_error('All inputs must be above 0 and percentages at most 100.');
    $leads = $t / ($lc / 100); $clicks = $leads / ($cl / 100); $budget = $clicks * $cpc;
    $text = sprintf("To win %d customers a month you need about %s leads from %s clicks.\nEstimated monthly ad budget: %s\nImplied cost per lead: %s, cost per customer: %s\nThis is a planning estimate; real CPCs and conversion rates move with competition and season.",
        (int)$t, trim(str_replace(['Rs ', $c . ' '], '', money(ceil($leads), $c))), trim(str_replace(['Rs ', $c . ' '], '', money(ceil($clicks), $c))), money($budget, $c), money($budget / $leads, $c), money($budget / $t, $c));
    return tool_result($text, ['currency' => $c, 'leads_needed' => (int)ceil($leads), 'clicks_needed' => (int)ceil($clicks), 'monthly_budget' => round($budget, 2),
        'cost_per_lead' => round($budget / $leads, 2), 'cost_per_customer' => round($budget / $t, 2)]);
}

function keyword_match($kw, $hay) {
    // Synonyms so "acquisitions" also finds "agreed to buy" and "merged" (mirrors the Worker).
    $syn = [
        ['/\\b(acqui\\w*|m&a|merg\\w*|buy\\w*|bought|takeover)/', '/\\b(acqui\\w*|merg\\w*|buy|bought|takeover)\\b/'],
        ['/\\b(expan\\w*|international|global|overseas|abroad|foreign|new markets?|cross-border)/', '/\\b(first (foreign|international) market|foreign|overseas|uae|saudi|middle east|the us|us market|into the us|australia\\w*|singapore\\w*|london|across \\d+ countries|cross-border)\\b/'],
        ['/\\b(ai|agents?|agentic)\\b/', '/\\b(ai|agents?|agentic|gen-ai|foundation model)\\b/'],
    ];
    if (strpos($hay, $kw) !== false) return true;
    foreach ($syn as $p) { if (preg_match($p[0], $kw)) return (bool)preg_match($p[1], $hay); }
    return false;
}

function t_moves($a) {
    $data = json_decode(@file_get_contents(__DIR__ . '/data/growth-moves.json'), true);
    if (!is_array($data)) return tool_error('Research data is unavailable right now.');
    $sector = isset($a['sector']) && is_string($a['sector']) ? strtolower($a['sector']) : 'any';
    $company = isset($a['company']) && is_string($a['company']) ? strtolower(trim($a['company'])) : '';
    $kw = isset($a['keyword']) && is_string($a['keyword']) ? strtolower(trim($a['keyword'])) : '';
    $limit = isset($a['limit']) && is_numeric($a['limit']) ? max(1, min(14, (int)$a['limit'])) : 5;
    $out = [];
    foreach ($data['editions'] as $sec => $ed) {
        if ($sector !== 'any' && $sector !== $sec) continue;
        foreach ($ed['moves'] as $m) {
            if ($company !== '' && strpos(strtolower($m['company']), $company) === false) continue;
            if ($kw !== '' && !keyword_match($kw, strtolower($m['move'] . ' ' . $m['company']))) continue;
            $m['sector'] = $sec; $m['edition_url'] = $ed['edition_url'];
            $out[] = $m;
        }
    }
    $total = count($out);
    $out = array_slice($out, 0, $limit);
    if ($total === 0) {
        return tool_result('No matching moves in Growth Moves 2026 (fintech, ecommerce, healthtech, saas). Browse all editions: ' . $data['hub_url'],
            ['matches' => 0, 'moves' => [], 'hub_url' => $data['hub_url']]);
    }
    $lines = [];
    foreach ($out as $m) { $lines[] = sprintf('%s (%s, %s): %s Source: %s', $m['company'], $m['leader'], $m['date'], $m['move'], $m['source_url']); }
    $text = sprintf("%d of %d matching moves from upGrowth Growth Moves 2026:\n%s\nFull research: %s", count($out), $total, implode("\n", $lines), $data['hub_url']);
    return tool_result($text, ['matches' => $total, 'moves' => $out, 'hub_url' => $data['hub_url']]);
}
