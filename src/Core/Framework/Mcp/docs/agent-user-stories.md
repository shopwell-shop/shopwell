# Agent User Stories — Platform Primitives

User stories for what an AI agent can accomplish using core Shopwell MCP tools (`shopwell-*`).

**Merchant workflow stories** (order summaries, customer lookup, product create, analytics, cart/checkout) are in the plugin:
→ [SwagMcpMerchantAssistant — merchant user stories](../../../../custom/plugins/SwagMcpMerchantAssistant/docs/agent-user-stories.md)

**Out of scope here**: Developer tasks such as code generation, testing, linting, cache clearing, and deployment. For developer-facing MCP tools see [shopwellLabs/ai-coding-tools](https://github.com/shopwell-shopLabs/ai-coding-tools).

**Status legend:** COVERED = fully working, PARTIAL = possible but limited, GAP = not yet possible

## Category 1: Data Exploration

- **US-1** [COVERED]: "What products are low on stock (below 5 units)?"
  - Tools: `shopwell-entity-search` with range filter on `stock`
- **US-2** [COVERED]: "Show me the schema of the customer entity"
  - Tools: `shopwell-entity-schema`

## Category 2: State Transitions

- **US-3** [COVERED]: "Ship order #12345"
  - Tools: `shopwell-order-state` with `orderNumber` and `deliveryAction: "ship"`
- **US-4** [COVERED]: "Cancel order X and process the refund"
  - Tools: `shopwell-order-state` with `orderAction: "cancel"`, `transactionAction: "refund"`, `deliveryAction: "cancel"`

## Category 3: Configuration

- **US-5** [COVERED]: "What are the current listing settings and how do I change the default sorting?"
  - Tools: `shopwell-system-config-read` + `shopwell-system-config-write` with dryRun

## Category 4: Flow / Automation Discovery

- **US-6** [GAP]: "Set up an automation: when order status changes to 'shipped', send email to customer"
  - Tools: `shopwell://business-events` resource + `shopwell://flow-actions` resource for discovery
  - Gap: Can advise which event/action to use, but cannot create flows programmatically
- **US-7** [PARTIAL]: "What automations are currently configured?"
  - Tools: `shopwell-entity-search` on `flow` with `sequences` association
  - Note: Works, but `flow_sequence` structure is complex to interpret

## Category 5: Media Management

- **US-8** [COVERED]: "Upload a product image from a URL and assign it to product X"
  - Tools: `shopwell-media-upload` with `url` and `productId`
- **US-9** [COVERED]: "Upload a new shop logo from a URL"
  - Tools: `shopwell-media-upload` with `url`

## Category 6: Theme / Appearance

- **US-10** [COVERED]: "Change the primary brand color of my shop to blue"
  - Tools: `shopwell-theme-config` with `action: "update"` and config `{"sw-color-brand-primary": {"value": "#0000ff"}}`
- **US-11** [COVERED]: "Update the shop logo in the theme"
  - Tools: `shopwell-media-upload` to upload the logo, then `shopwell-theme-config` to set the media ID in `sw-logo-desktop`

## Category 7: Promotions & Marketing

- **US-12** [COVERED]: "What promotions are active right now?"
  - Tools: `shopwell-entity-search` on `promotion` with `active: true` and date range filters
- **US-13** [COVERED]: "How many newsletter subscribers do we have?"
  - Tools: `shopwell-entity-aggregate` on `newsletter_recipient` with `count` and `status: optIn` filter

## Category 8: Product Quality & Content

- **US-14** [COVERED]: "Which products have no images?"
  - Tools: `shopwell-entity-search` on `product` with `media` association count filter
- **US-15** [COVERED]: "Change the price of product SW-001 to 39.99"
  - Tools: `shopwell-entity-upsert` on `product` with nested price array
- **US-16** [COVERED]: "Assign product X to category Y"
  - Tools: `shopwell-entity-upsert` on `product` with `categories` association

## Category 9: Customer Insights (via DAL)

- **US-17** [COVERED]: "Show me all 1-star reviews from last month"
  - Tools: `shopwell-entity-search` on `product_review` with `points` and `createdAt` filters
- **US-18** [COVERED]: "How many customers haven't ordered in 6 months?"
  - Tools: `shopwell-entity-search` on `customer` with `lastOrderDate` range filter
- **US-19** [COVERED]: "What's the average order value this month?"
  - Tools: `shopwell-entity-aggregate` on `order` with `avg` on `amountTotal`

## Category 10: Sales Channel Operations

- **US-20** [COVERED]: "Put my shop in maintenance mode"
  - Tools: `shopwell-entity-upsert` on `sales_channel` with `maintenance: true`

## Postponed Improvements

- **US-6** (flow creation): Postponed until event/action validation and multi-action flow support are implemented together
