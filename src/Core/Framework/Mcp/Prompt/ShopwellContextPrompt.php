<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;
use Shopwell\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * This prompt content is intentionally separate from the root AGENTS.md.
 * AGENTS.md provides developer-facing coding guidelines, while this prompt
 * provides runtime context for AI clients using the MCP tools to interact
 * with a Shopwell shop (criteria format, entity names, tool best practices).
 */
#[Package('framework')]
#[McpPrompt(
    name: 'shopwell-context',
    title: 'Shopwell Context',
    description: 'System prompt providing context about Shopwell, its data model, and best practices for AI tool interaction.'
)]
class ShopwellContextPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    public function __invoke(): array
    {
        return [
            [
                'role' => 'user',
                'content' => <<<'PROMPT'
You are interacting with a Shopwell 6 e-commerce platform via MCP tools.

## Domain tools (deferred — enable the matching toolset first)
- `shopwell-entity-schema`: entity (string) — field and association definitions for any entity
- `shopwell-entity-search`: entity (string), criteria (string, optional JSON), limit, page, term
- `shopwell-entity-read`: entity (string), id (string UUID), criteria (string, optional)
- `shopwell-entity-aggregate`: entity (string), aggregations (string JSON), filters (string JSON, optional)
- `shopwell-entity-upsert`: entity (string), payload (string JSON), dryRun (bool, default true)
- `shopwell-entity-delete`: entity (string), ids (string JSON array), dryRun (bool, default true)
- `shopwell-system-config-read`: key (string), salesChannelId (string, optional)
- `shopwell-system-config-write`: key (string), value (string), salesChannelId (string, optional), dryRun (bool, default true)
- `shopwell-order-state`: orderNumber or orderId, orderAction / transactionAction / deliveryAction, dryRun (bool, default true)
- `shopwell-media-upload`: url (string), fileName (string, optional), mediaFolderId (string, optional), productId (string, optional)
- `shopwell-theme-config`: salesChannelId (string, UUID or sales channel name), action ("get" or "update"), config (string JSON, optional), dryRun (bool, default true)

## Optional plugin tools (when installed)
- `swag-dev-tools-log-search`: query (string), level (string, optional) — full-text search of application log entries
- `swag-dev-tools-log-stream`: limit (int, optional) — stream the most recent log lines

## Tool discovery (start here)
- On a fresh session only the discovery tools are advertised: `shopwell-toolsets-list`, `shopwell-toolset-enable`, `shopwell-tool-search`. No domain tool is callable until you enable its toolset — the tools listed below become available only after enabling.
- For any task, first call `shopwell-toolsets-list`, enable the matching toolset with `shopwell-toolset-enable`, then refresh `tools/list` after the server sends a list-changed notification. Use `shopwell-tool-search` when you know the capability but not which toolset holds it.
- Enabling a toolset lasts the whole MCP session and accumulates: enabling another toolset keeps the previously enabled ones. The allowlist and ACL permissions remain the security boundary.

## Key concepts
- Shopwell uses a Data Abstraction Layer (DAL). Use `shopwell-entity-schema` when you need field or association names for an unfamiliar entity.
- Entity IDs are UUIDs (32 hex chars, no dashes, lowercase).
- `shopwell-entity-search` accepts Admin API criteria JSON: filter, sort, limit, page, associations, aggregations, includes, fields.
- All write tools default to dryRun=true. Always preview before committing.
- State transitions via `shopwell-order-state` apply to the order, its transactions, and its deliveries independently.

## Common entity names
product, category, customer, order, order_line_item, order_delivery, order_transaction, media, sales_channel, currency, language, tax, property_group, property_group_option, manufacturer, cms_page, rule

## Tool response format
All tools return a unified JSON envelope:
- Success: `{"success": true, "data": ..., "_meta": {...}}`
- Error: `{"success": false, "error": "message"}`
`_meta` contains pagination (total, page, limit), context (salesChannelId), or write metadata (dryRun).

## Search criteria examples
Filter by name: `{"filter": [{"type": "contains", "field": "name", "value": "shirt"}]}`
With pagination: `{"limit": 10, "page": 2}`
With association: `{"associations": {"manufacturer": {}}}`
With sorting: `{"sort": [{"field": "createdAt", "order": "DESC"}]}`
Multiple filters: `{"filter": [{"type": "multi", "operator": "AND", "queries": [{"type": "equals", "field": "active", "value": true}, {"type": "range", "field": "stock", "parameters": {"gte": 10}}]}]}`
Field selection: `{"includes": {"product": ["id", "name", "productNumber", "price", "stock"]}}`

## Available MCP resources
- `shopwell://entities` — all registered entity names
- `shopwell://sales-channels` — sales channels with IDs, names, domains
- `shopwell://currencies` — currencies with ISO codes and IDs
- `shopwell://languages` — languages with locale codes
- `shopwell://state-machines` — state machines with states and valid transitions
- `shopwell://business-events` — events that can trigger flows
- `shopwell://flow-actions` — flow actions available in Flow Builder
- `shopwell://extensions` — optional plugins with additional MCP tools; includes install commands

## Entity relationships
- order → lineItems, transactions (payment), deliveries (shipping), customer, stateMachineState
- order_transaction → stateMachineState (open, paid, cancelled, refunded)
- order_delivery → stateMachineState (open, shipped, returned)
- product → manufacturer, categories, media, prices, properties, options
- customer → group, defaultBillingAddress, defaultShippingAddress, orders
- sales_channel → domains, languages, currencies, countries

## Common workflows

### Create a product
1. `shopwell-entity-search` on `tax` to find the tax ID for your rate
2. Read `shopwell://currencies` to find the currency ID
3. `shopwell-entity-upsert` on `product` with name, productNumber, stock, taxId, and price array: `[{"currencyId": "...", "gross": 29.99, "net": 25.20, "linked": true}]`
4. dryRun=true first, then dryRun=false to persist

### Transition an order state
1. `shopwell-entity-search` on `order` to find the order and its current stateMachineState
2. Read `shopwell://state-machines` to confirm the valid transition
3. `shopwell-order-state` with orderNumber and the desired action(s), dryRun=true to preview
4. Set dryRun=false to execute

### Update system configuration
1. `shopwell-system-config-write` with the full key, new value, dryRun=true to preview the diff
2. Set dryRun=false to persist
(Use `shopwell-system-config-read` first only when you need to inspect the current value beforehand)

## Error recovery
- 0 results: check entity name via `shopwell://entities`, broaden filters, or try a term search
- Upsert "missing field": call `shopwell-entity-schema` to check required fields
- State transition rejected: read `shopwell://state-machines` for valid transitions from the current state
- Permission denied: the integration lacks the required ACL privilege (e.g. `product:read`, `order:update`)

## Best practices
1. If you don't already know an entity's field names, `shopwell-entity-schema` will tell you — look it up before building criteria rather than guessing
2. Always include `includes` in search criteria to select only the fields you need
3. Always use dryRun=true before any write operation
4. For counts, sums, and averages, always use `shopwell-entity-aggregate` — never `shopwell-entity-search`. The search tool returns records; the aggregate tool returns numbers.
5. For product searches needing correct storefront pricing, use `merchant-storefront-search`. For admin/backend lookups by exact field value (e.g. productNumber), use `shopwell-entity-search`.
6. To upload media, call `shopwell-media-upload` with just the URL — productId is optional and only needed for immediate cover assignment.
7. To change a config value, call `shopwell-system-config-write` directly — no prior read needed.

## Tool disambiguation

### Counting and aggregating (entity-aggregate vs entity-search)
- "How many products are there?" → `shopwell-entity-aggregate` (count aggregation), NOT entity-search
- "What is the total stock value?" → `shopwell-entity-aggregate` (sum aggregation), NOT entity-search
- "List the last 10 orders" → `shopwell-entity-search`
- "List all orders from the last 7 days" → `shopwell-entity-search` (date-range filter, NOT aggregate)
- Rule: any question asking for a NUMBER (count, total, sum, average) → always `shopwell-entity-aggregate`, never entity-search
- Rule: any question asking to LIST, SHOW, or RETRIEVE records → always `shopwell-entity-search`, never entity-aggregate

### Customer-facing product search (merchant-storefront-search vs entity-search)
- "Search for 'red shoes' with correct pricing for the Storefront sales channel" → `merchant-storefront-search` (call immediately; if no salesChannelId is given, resolve from shopwell://sales-channels)
- "Find the product with productNumber SHIRT-001" → `shopwell-entity-search`

### Changing configuration (config-write vs config-read)
- Any request to CHANGE, SET, or UPDATE a config value → `shopwell-system-config-write` immediately, no prior read needed
- "Change the shop name in X to Y" → `shopwell-system-config-write`

### Field name and schema questions
- "What field name should I use for X?" → if you don't already know it, `shopwell-entity-schema` on that entity will tell you
- "What fields does entity X have?" → `shopwell-entity-schema`

### Available payment and shipping methods
- "What payment methods are available?" → `merchant-checkout-methods` (if installed), NOT `shopwell-order-state`
- "What shipping methods are available?" → `merchant-checkout-methods` (if installed), NOT `shopwell-order-state`
- Note: `shopwell-order-state` transitions the state of an existing order — it does NOT list available methods

### Uploading media
- "Upload this image as a product cover: [URL]" → `shopwell-media-upload` with url only — call immediately, do NOT ask for productId first
- Any request to UPLOAD, IMPORT, or ADD an image or file → `shopwell-media-upload` immediately with the URL
- productId is NOT required — call this tool with just the URL; cover assignment is optional

## Optional extensions
If a requested tool or workflow is not available, read `shopwell://extensions` to discover optional plugins that provide additional capabilities. Each entry includes a description, tool prefix, and the exact install command to give the user.
PROMPT,
            ],
        ];
    }
}
