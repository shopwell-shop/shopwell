# 6.7.0.0 (upcoming)

## Core

### Moving a category re-indexes its products

Changing the `parentId` of a category now queues the product indexer for all products assigned to that category or one of its descendants.
Before, `product.categoryTree` and `product.categoriesRo` kept the old parents, so listings and "line item in category" rules were wrong until the products were re-indexed.
Integrations that called `POST /api/_action/index-products` after moving a category no longer need to.
The Administration category tree no longer sends these requests after a drag and drop.
Its `syncProducts()` and `indexProducts()` methods and its `productRepository` computed property are deprecated and will be removed in 6.8.

### Environment-specific bundle and plugin configuration

Inline `when@prod` and `when@test` blocks in bundle and plugin configuration now apply in the matching environment.
PHP configuration files also receive the current environment through `$container->env()`.
Check existing environment-specific configuration that was previously ignored, including Monolog handler levels.

### Regenerating a document keeps its deep link and its stored files

`DocumentGenerator::generate()` with the id of an existing document now keeps the document's deep link code and its stored PDF and HTML files, and renders only the file type that is missing.
While a stored file is kept, the document also keeps its order version and config.
The missing file is rendered from the current order.
To get fresh files for an existing document, generate a new document instead of regenerating it.

### Add a preliminary approval reviewer

The approval task's `add-sign` Admin API action opens a preliminary review and holds the original task.
Approval restores the original task, and rejection vetoes the group.
Request detail exposes the additional reviewer and reason in `addSigns`.

### Transfer a pending approval task

An assigned approver can search for eligible reviewers with the `transfer-candidates` Admin API action and hand over the task with `transfer`.
The current review keeps its recorded opinions, and the approval detail lists the transfer alongside its reason.

### Return an approval task to an earlier review

An assigned approver can use the task's `return-targets` and `return` Admin API actions to reopen a previously approved step on the current branch.
The approval detail includes return reasons and preserves the decisions from earlier reviews.
Extensions can narrow the eligible targets using `ApprovalReturnTargetsEvent`.

### Approval Flow skeleton

The independent Approval Flow module provides exact-version node declarations through `NodeTypeLoader` and the `approval_flow.node_type_loader` service tag.
Business modules register their loaders in their own DI; a declaration alone does not make a node executable.
Approval business types can be contributed through `ApprovalBusinessTypeLoader` and `approval_flow.business_type_loader`, then inspected with `approval-flow:business-types`.
`ApprovalBusinessTypeSpecification` retains the business identity, exact version and form schema bytes.
The business type catalogue grants no business submission or result execution authority; those implementation contracts are not exposed yet.
Business modules can reject incompatible bindings through `ApprovalBindingValidationEvent`, dispatched after generic release validation on both save and activation.
Listeners receive the exact business declaration, verified graph JSON and original context, and reject by throwing a business exception without writing business state.
Payment now contributes `payment:transfer@1.0.0` with a minor-unit integer form and rules requiring mandatory approval and read-only transfer data.
This declaration does not switch the existing transfer interface or authorize payout execution.
Business modules can also register `NodeCompiler` services with `approval_flow.node_compiler` to compile exact node versions into typed `NodeConfiguration` values.
Configuration compilation rejects missing or conflicting compilers and invalid semantics.
Register exact-version `NodeExecutor` entry services with `approval_flow.node_executor`.
Node-plan compilation rejects missing executors without calling them.
Executors return `NodeEffect` and may use the concrete effect class as their return type.
`ApprovalOutcome` represents approved/rejected outcomes as an enum.
Node declarations require `allowedEffects`.
Programmatic declarations pass `NodeEffectType` values.
The internal entry path rejects undeclared effects and changes to compiled approval rules or end outcomes; these checks do not authorize runtime writes.
These entry services do not authorize task creation or process execution.
Approval strategies implement `ApprovalPolicyHandler` and register `approval_flow.approval_policy` in their owning module.
Publication pins the selected strategy version and deployment artifact; voting verifies the frozen release and rejects a result that contradicts the published veto rule.
Node executors receive an optional immutable `NodeExecutionContext` with the submitted form snapshot and can select published output ports through `AdvanceEffect`.
This supports extension-owned conditional routing along one active path.
Executors may also declare and return `OpenScopeEffect` and `WaitEffect` for scoped branches and persistent join waits.
The runtime supplies an immutable actual-member `ApprovalScopeSnapshot` through `NodeExecutionContext::scope`; the registered executor decides when to return to the enclosing branch.
Nested scopes, selected subsets and early-return cancellation use the same instance transaction, generation and dispatch markers.
Task `completionReason` values are namespaced strings, with built-in constants in `ApprovalTaskCompletionReason`, separate from personal opinions.
These runtime contracts do not add a stock parallel node catalog or Administration designer.
Internal compilation publication now stores immutable `approval_flow_release` DAL records with expected-revision and full-preview-hash checks.
Using publication requires a deployment-owned manifest configured through `APPROVAL_FLOW_ARTIFACT_MANIFEST`, binding actual service classes to retained artifact bytes and exact transitive dependencies.
The deployment attests code origins and dependency completeness; byte verification does not prove loaded-code provenance, historical code loading or worker readiness.
Ordinary repository and Sync writes cannot create or change release records, and publication grants no activation authority.
Internal node entry now verifies persisted release content against this deployment and restores typed configurations through the existing exact-version compilers before requesting a pure executor intent.
Mismatched frozen content or restored configuration is rejected before entry.
The `approval_flow_instance`, `approval_flow_operation` and `approval_flow_execution` DAL models retain instance state, immutable command receipts and node execution records.
Generic repository and Sync writes to these models are rejected.
An internal command can enter the start of an existing running instance, record its completion and queue a single successor as waiting in the same transaction as the version and receipt.
Approval entry verifies the waiting execution against its bound release and records one pending `approval_flow_task` per configured active Administration account.
An internal personal decision command requires the active assignee and `approval_flow_task:decide`.
It aggregates frozen single, all or any approval rules with rejection veto, records the node outcome and cancels remaining pending tasks atomically with the receipt.
System and integration identities cannot cast personal votes.
Internal progression can queue a single unconditional successor after approval or record a completed rejection after veto.
End entry verifies the published end boundary and requires all other execution and task work to be settled before committing the instance outcome.
Instance and receipt DAL models expose the optional outcome separately from lifecycle status; retry receipts retain their original result.
Terminal commands also persist one immutable `approval_flow_result` and pending result delivery work in the same transaction.
The internal delivery adapter provides committed system-only leases, attempt fencing and bounded retries without changing the completed instance.
Business modules can implement `ApprovalResultHandler` and register `approval_flow.result_handler` for an exact business type/version.
The handler receives the immutable result and original request; it must commit its business update and result-ID deduplication atomically before returning.
The scheduled task `approval_flow.deliver_results` retries failed applications and acknowledges only after a handler completes.
Payment saves the decision for the original transfer request without executing a payout.
Transfers submitted through Approval Flow can use the existing payout entry point only after their applied approval and unchanged business inputs are checked again inside the atomic attempt claim.
Approval result delivery records the decision even if an otherwise unchanged payment account has been disabled.
A payout still requires an eligible account at execution time.
Pending, rejected and not-yet-delivered decisions cannot authorize a payout.
The scheduled task `approval_flow.progress` now advances committed approval outcomes across serial and scoped branches to the next wait or published end, preserving saved decisions when progression must be retried.
Register scheduled tasks and run the normal scheduled-task runner and Messenger worker to enable this continuation.
Internal progression preserves the caller Context; authorization belongs to its actual entry point, while personal decisions retain their assignee checks.
Payment transfer submission starts the initial approval tasks through the internal request path.
Personal request queries are available at `GET /api/_action/approval-flow/requests` (`view=todo|done|started`) and `GET /api/_action/approval-flow/requests/{requestId}` with `approval_flow:access`.
Lists apply ownership before pagination; details restrict form fields to the participant's assigned nodes and include personal decision history.
Assigned Administration reviewers can submit `approved` or `rejected` opinions at `POST /api/_action/approval-flow/instances/{instanceId}/tasks/{taskId}/decide` with `approval_flow_task:decide`.
Provide a command ID and expected instance version; retry an uncertain response with the identical payload to retrieve the original receipt.
The Administration `approval_flow.editor` mapping adds this decision privilege with a viewer dependency and exposes confirmed approve/reject actions for personal pending tasks.
A committed opinion does not imply final approval or payment execution; lifecycle commands remain internal.
The Administration now provides a Flow definitions read surface under Approvals.
Approval Flow publishers can preview the saved draft and publish its immutable release from the Administration.
Activators can select a published release for a business type, which affects only future submissions and preserves the release of applications already underway.
New Administration transfer bookings use Approval Flow after a transfer release is activated.
It lists definition identities and draft revisions, shows the current draft with diagnostics and exact node versions, and lists immutable publication history.
The definition viewer mapping includes definition, draft and release read privileges, separately from personal approval access, and grants no editing, publication or activation authority.
Reads use standard DAL repositories and the definition’s one-to-one `draft` association; catalog discovery and graph diagnostics remain special design operations.
The node catalog is sourced from registered exact-version declarations; missing extension versions remain visible as unavailable capabilities.
Declarations may supply an explicit `draftConfigurationSchema`; otherwise drafts retain the complete schema's rules.
Use `approval-flow:graph:diagnose <file>` to inspect draft structure, incomplete configuration and unavailable capabilities without saving or executing the graph.
Approval definitions and drafts now have DAL models; writes require the controlled draft path so graph admission, expected revisions and explicit removal confirmation cannot be skipped through ordinary repository or Sync calls.
An unavailable exact node declaration keeps an existing draft readable and blocks edits until the declaration returns.
The Administration now groups My pending approvals, My completed reviews and My requests under the top-level Approvals menu, with a shared request detail page.
Assign `approval_flow.viewer` to grant personal query access without generic approval or user entity read privileges.
Query summaries include `title` and nullable `initiatorName`; details include `formLabels` for visible fields and `participantNames` for the initiator and assigned reviewers.
Business form schemas may provide `title` and `x-shopwell-label-snippet` annotations for display labels.

### User organization assignments

The installation migration seeds the editable Shopwell organization, Headquarters department and Employee position.
New users receive their initial department and position from these organization records.
Write department assignments through `user.organizationUnitMappings` and scoped positions through `user.positionMappings` or their Admin API repositories; `user.positions` is a read-only projection.
Each normal user requires exactly one primary department and primary position; use the Sync API when replacing related assignments together.
Department memberships expose `isLeader`, `leaderPosition` and `supervisorMembershipId` to the Admin API.
A supervisor references a specific membership in the same organization, so reporting lines remain explicit for users with multiple departments.
The user editor configures department leaders and department-specific positions.
Core now directly requires `symfony/expression-language` with a minimum version of 7.4.21 within the 7.4 series.

### After-sales refund destination

Administration refund confirmations default to the original payment sources and can instead credit a registered customer's wallet.
The approve, receive and refund actions accept optional `refundDestination` (`original` or `wallet`); the first payout attempt fixes this value on the return.
Wallet payouts also reduce the collected payment's refundable amount and use the return number for idempotency.
Guest returns support the original payment sources only.

### Free growth alongside paid membership

Customers continue earning tier points during paid membership.
The effective tier is the higher of the earned tier and the valid paid tier; expiry falls back to the earned tier immediately when read.
Paid tier qualification is available as `loyalty_account.paidTierId`, independently of the effective `tierId`.
Existing running subscriptions are migrated automatically.

### SMS provider extension contract

SMS providers extend `Shopwell\Core\System\Sms\Provider\AbstractSmsProvider` and register with `shopwell.sms.provider`.
Credential and connection fields use `SmsConfigField`; the Administration renders them and enabled accounts are validated when written.
The provider metadata endpoint includes `optionDefinitions`.
Template sending and template approval are independent capabilities under `Provider\Audit`.
The development baseline removes the former gateway factory registration and uses `Routing\SmsGatewayResolverInterface` for routing.
The `SelectSmsGatewayExtension` extension can reorder or remove eligible accounts.


### PayPal and Stripe integrations moved to SwagPayments

PayPal and Stripe are provided by the separate `swag/payments` plugin and are no longer bundled with Core.
Install and activate `SwagPayments` and configure your merchant accounts to use their checkout, refund, payout and notification capabilities.
The SDK dependencies, payment method initialization, icons and Administration translations are owned by the plugin.
Integration classes now use `Swag\Payments\Integration` instead of `Shopwell\Core\System\Payment\Integration`.
The Core installation baseline seeds only Alipay and WeChat Pay, and the first-run wizard no longer installs the legacy `SwagPayPal` plugin.
The generic recipient readiness method is named `PaymentTransferRecipientService::markProviderAccountReady()`.


### Blog publishing and content layouts

New Blog entries default to drafts (`active=false`).
Publish an entry by setting `active=true`; a future `publishedAt` schedules its publication.
Blog entries reuse system tags and support native `blog_content_layout` assignments with `rootSource=blog`.
Tags expose their related entries directly through the native `blogs` association.
The Blog installation baseline provisions shared article, video, and listing layouts automatically.
The repair command `bin/console content:blog:provision-layouts` remains available.
The type-specific configuration keys are `core.content_system.default_blog_post_content_layout` and `core.content_system.default_blog_video_content_layout`, with `core.content_system.default_blog_content_layout` as the shared fallback.
The shared homepage/category listing default is configured through `core.content_system.default_blog_listing_content_layout` and uses `rootSource=none`.
Blog layout associations extend `content_layout` through `extensions.blogContentLayouts`.
Explicit Blog assignments take precedence over legacy standalone body layouts, which remain supported.
Register custom types through `BlogTypeRegistry::addType($type, $template, $schemaType)` instead of type handlers.
`GET /api/_action/blog/types` lists registered type names.


### MCP servers run on `mcp/sdk` 0.9 and `symfony/mcp-bundle` 0.15

Shopwell now requires `mcp/sdk` 0.9 and `symfony/mcp-bundle` 0.15.
Plugins and bundles that register MCP tools are affected in these points:

* A tool that declares an `outputSchema` has its `structuredContent` validated against it.
  A result that does not match is sent as an error result with `isError: true`.
  Return `new \stdClass()` for an empty object, since `[]` is sent as an array.
* A tool whose `inputSchema` has a `properties` member that is not an object, or a `required` member that is not a list, is rejected when it is registered.
* `#[CompletionProvider]` no longer takes `providerClass`; pass the class to `provider` instead.

The SDK classes for an OAuth proxy, client registration and OIDC discovery were removed, see the [mcp/sdk 0.9.0 changelog](https://github.com/modelcontextprotocol/php-sdk/blob/v0.9.0/CHANGELOG.md).
The MCP endpoints match the `Origin` header of browser clients exactly, including scheme and port.
They accept the origins of `APP_URL` and of every sales channel domain.
A loopback origin, for example the MCP Inspector on `http://localhost:6274`, is accepted only for a shop on a loopback host such as `localhost` or `*.localhost`.
Clients without an `Origin` header, such as CLI clients and desktop apps, are not affected.
Allow further origins with `shopwell.mcp.allowed_origins`.
An entry is an exact origin, an origin with `:*` for any port, or `*` for any origin:

```yaml
shopwell:
    mcp:
        allowed_origins:
            - 'https://chat.example.com'
            - 'http://localhost:*'
```

The list can also come from an environment variable, for example `allowed_origins: '%env(csv:MCP_ALLOWED_ORIGINS)%'`.
A rejected origin is logged as a warning in the `mcp` channel.

### Collection class for import/export files

`import_export_file` entities are now loaded into an `ImportExportFileCollection` instead of a generic `EntityCollection`.
Replace `EntityRepository<EntityCollection<ImportExportFileEntity>>` annotations with `EntityRepository<ImportExportFileCollection>`.

### Promotion line items store the discount value in the cart currency

`payload.value` of promotion line items with an absolute, fixed or fixed unit discount now contains the value in the cart currency, using the advanced price of the currency or the currency factor.
Orders placed before this change still contain the default currency value.
Extensions and headless frontends that convert `payload.value` into the cart currency themselves must remove that conversion.

### Cart deliveries and transactions carry a payload into the order

Cart deliveries and transactions have a `payload`, which the order stores in the new `payload` field of `order_delivery` and `order_transaction` and keeps through order edits.
Their payload methods work like the ones of line items.
`setPayloadValue($key, $value, protected: true)` keeps a key out of the Store API cart response, but Store API order responses still contain it.
Unlike a line item payload, it does not survive the next cart calculation.
Keep your state on line items or in a cart extension, and write the delivery payload in a cart processor after the `DeliveryProcessor`.
At checkout, a payment handler can set the transaction payload in its `validate()` method, which receives the calculated cart right before the order is written.
Any other extension sets it on the converted order data in the `CartConvertedEvent`.

### Mails and documents list every shipment

`OrderDeliveryCollection::getShipments()` returns the deliveries that are not shipping discounts, sorted by their `position`.
Deliveries with the same position, like every delivery of an order placed before 6.7.17.0, list the primary delivery first when its id is passed, then by shipping costs.
The cart's `DeliveryCollection::getShipments()` returns the cart deliveries that are not shipping discounts in cart order, which becomes their `position` when the order is placed.
The storefront, the mails, the documents and, with `v6.8.0.0`, the Administration list shipments in this order, so a shipment has the same number everywhere.
When an order has more than one shipment, the default order confirmation, payment received and payment reminder mails list each shipment with its shipping method, address, shipping costs and delivery period.
The delivery status mails name the shipment whose state changed when the flow provides `orderDelivery`, and list every shipment with its state otherwise.
A migration updates these mail templates unless they were changed in the Administration.
Documents keep one shipping costs row with the shipping total of the order; this row and the shipping method line name each shipping method of the shipments once.
The invoice shows the shipping address of the primary delivery.
Orders with one shipment render as before.

### Customer language check on sales channel change

The customer language check from [#14836](https://github.com/shopwell-shop/shopwell/pull/14836) now also runs when only the sales channel changes.

### MCP sessions on `/api/_mcp` are bound to the principal that opened them

A session on the Admin API MCP endpoint now belongs to the user or integration that sent `initialize`.
A request with the `Mcp-Session-Id` of a session opened by someone else gets the same `404` as an unknown session.
The session stays valid when the client gets a new access token for the same user or integration.

Sessions opened before the update have no owner and are answered with `404` once.
MCP clients start a new session on `404`, so no action is needed.
The stateless protocol era and `/store-api/_mcp` are not affected.

### The `/api/_mcp` rate limit counts per user or integration

The `mcp_admin_api` rate limit now counts the requests of a user or integration, no longer of a single access token.
A new or refreshed access token continues in the same bucket, and all MCP clients of one user share it.
An integration has one bucket, whichever user it acts for with `sw-app-user-id`.
Clients that requested a new token to get a fresh limit are throttled sooner.
Requests without a known user or integration are still counted per client IP.
The stateless protocol era is not affected.
On `/store-api/_mcp`, a `DELETE` of an unknown session now gets `404` instead of `200`.

### MCP tool results follow the negotiated protocol revision

Tool results of `/api/_mcp` and `/store-api/_mcp` now take their format from the protocol revision the client negotiated, not from the `v6.8.0.0` feature flag.

* **Clients on 2025-06-18 and later** get the MCP format: the first text block is the result data as plain JSON (or the error message), followed by the summary and the metadata (`{"_meta": …}`) as blocks of their own.
  The text is no longer the `{"success": …}` envelope.
  The data is also in `structuredContent`, a failure sets `isError: true` with a stable code in `structuredContent.error.code`, and the metadata is also in the result's `_meta` with the prefix `shopwell/`.
  Integrations that parse `success` out of `content[0].text` have to read `isError` and `structuredContent` instead, or negotiate an older revision.
  When a result has metadata for the model, such as `dryRun`, `total` or a summary, `structuredContent` carries it under the reserved key `_meta`: next to the keys of an object, or as `{"result": …, "_meta": …}` for a list or scalar, on 2026-07-28 too.
  Tools that declare an `outputSchema` get exactly their data in `structuredContent`.
* **Clients on 2024-11-05 and 2025-03-26** keep the `{"success": …}` envelope as the only text block, unchanged.
  This legacy envelope is deprecated as a wire format.
  It is kept for these clients and is not removed in 6.8.0.
  Tools that still return the `{"success": …}` string keep working; see "MCP tools return `McpToolResult`; the legacy string is deprecated" for the result object.

### Large MCP results in the result envelope are stored and linked for every tool

The MCP result renderer now decides whether a tool result is too large to return inline, on `/api/_mcp` and `/store-api/_mcp`.
It renders every result in the `{"success": …}` envelope, meaning a JSON string with a boolean `success`, no matter whether it comes from `McpToolResponse::success()`, an app tool, or a plugin tool that builds the string by hand.
Such results, which were always returned inline unless they came from `McpToolResponse::success()`, are now stored when they are too large and sent as a `resource_link` (2025-06-18 and later) or with `_meta.resourceUri` in the envelope (before 2025-06-18).
A tool result that is not in this envelope, such as plain text, is passed through unchanged and is never stored.
A PHP tool that returns plain text triggers a deprecation; it returns `McpToolResult::text('…')` instead, which sends the same text block to every client.
On 2025-06-18 and later, results of a tool that declares an `outputSchema` are never stored, because a link carries no `structuredContent` that could match the schema; such a tool has to keep its results small itself.
The model reads stored results with `resources/read`.
`McpToolResponse::success()` itself no longer stores anything and always returns the full data, so a tool called outside of an MCP request gets its complete result.
The limit applies to the rendered result, the text plus `structuredContent`, and is set so that the offload point stays at about 100 KB of data.
The linked response keeps the tool's summary, its own links, and its metadata up to 10 KB; larger metadata values are named in `_meta.omittedMeta`.
On 2025-06-18 and later these values cannot be fetched, because the stored result holds only the data, so tools should put large values into the data instead.
For clients on protocol revision 2025-06-18 or later, `resources/read` of a stored result returns the plain data instead of the `{"success": true, "data": …}` envelope.
Its metadata, such as `total`, comes with the link in the tool result.
Clients on 2024-11-05 and 2025-03-26 keep reading the envelope.

### Opt-out attribute for MCP tools without privileges

An Admin API MCP tool that reads no shop data can now declare `#[McpToolRequiresNoPrivilege(reason: '...')]` instead of `#[McpToolRequires]`.
The new PHPStan rule `shopwell.mcpAdminToolMissingRequires` reports an Admin API tool that declares neither attribute,
and `shopwell.mcpStoreApiToolAdminAcl` reports a Store API tool that uses Admin ACL privileges.
Both rules are part of the rule set that extensions include.
They only report when `parameters.symfony.containerXmlPath` points to a compiled container that registers the extension's tools.

### Media uploads can set a title and an alt text

`MediaUploadParameters` has the new optional properties `title` and `alt`.
`MediaUploadService` writes them when it creates the media, so a separate media update for them is no longer needed.

### MCP toolsets can carry a description

`#[McpToolGroup]` takes an optional `description` that describes the whole toolset.
`shopwell-toolsets-list` and `shopwell-toolset-enable` return it instead of the generated `Tools explicitly assigned to the "<group>" MCP tool group.`, so a model can tell which toolset fits a task.
All core toolsets now have one.
Plugins that add their own toolset should set it on one tool of the group:

```php
#[McpToolGroup('swag-report', description: 'Revenue, bestseller and customer reports for a date range. Enable it for questions about sales figures.')]
```

Existing `#[McpToolGroup('<group>')]` declarations keep working and keep the generated description.
Plugins can add tools to a core toolset such as `entity`, but cannot change its description.

### MCP tools return `McpToolResult`; the legacy string is deprecated

All core MCP tools now return a `Shopwell\Core\Framework\Mcp\Result\McpToolResult` instead of the `{"success": …}` JSON string.
What clients receive is unchanged on every protocol revision.

`McpToolResponse` has new helpers that return the result object: `successResult()`, which also takes an optional summary for the model, and `errorResult()`, which also takes error details.
The other helpers have a counterpart as well: `requirePrivilegeResult()`, `missingPrivilegesResult()`, `invalidCriteriaResult()`, `decodeJsonOrErrorResult()` and `executeWithDryRunResult()`.

Returning the legacy string from a plugin or bundle tool, built with `success()`, `error()` and the other string helpers or by hand, is deprecated.
It keeps working in 6.7, including for tools that declare `__invoke(): string`, but it can't carry a summary or error details.
The string helpers `success()`, `error()`, `requirePrivilege()`, `missingPrivilegesError()`, `invalidCriteriaError()`, `decodeJsonOrError()` and `executeWithDryRun()` are marked `@deprecated tag:v6.8.0`.
They trigger a deprecation when called and throw once the `v6.8.0.0` feature flag is active, so run your tests with the flag to find the calls that are left.
A PHP tool that returns the string, built by hand or with the helpers, triggers the same deprecation.
Switch a tool by declaring `__invoke(): McpToolResult` and using the `…Result` helpers:

```php
public function __invoke(string $id): McpToolResult
{
    if ($error = $this->requirePrivilegeResult($this->contextProvider->getContext(), 'product:read')) {
        return $error;
    }

    return $this->successResult(['id' => $id], ['total' => 1]);
}
```

App tools keep the `{"success": …}` envelope as their response format.

The new PHPStan rule `McpToolLegacyStringResultRule` (identifier `shopwell.mcpToolLegacyStringResult`) reports a `#[McpTool]` method whose native return type is `string`, `?string`, a union containing `string`, `mixed`, or missing.
For now it is registered in `core-rules.neon` only, which the Shopwell PHPStan extension doesn't load for extensions by default, so it does not fail your build in 6.7.
It moves to `rules.neon` with 6.8.0.
To check your tools today, add it to the `rules` of your `phpstan.neon`:

```neon
rules:
    - Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\McpToolLegacyStringResultRule
```

### MCP endpoints can serve the stateless 2026-07-28 protocol revision

Both MCP endpoints can now serve the stateless 2026-07-28 revision next to the handshake revisions, from the same URL.
It is off by default.
Switch it on per endpoint:

```yaml
shopwell:
    mcp:
        protocol_era:
            admin: auto
            store_api: auto
```

`legacy`, the default, serves the handshake revisions up to 2025-11-25 only.
With `auto`, the client picks the revision, and clients on the handshake revisions are not affected.
`bin/console debug:mcp` shows the era of each endpoint.

On the stateless revision there is no MCP session:

* Choose toolsets in the endpoint URL, for example `/api/_mcp?toolsets=order,media`.
  `shopwell-toolset-enable` and `shopwell-toolsets-list` are not listed in `tools/list`; called directly, they answer with an error that points to `?toolsets=`.
  The server instructions and `shopwell-tool-search` point to `?toolsets=` as well, and each search match names its toolset.
* List changes on the Admin API endpoint reach clients through `subscriptions/listen` within about a second.
  They are raised by app installs, updates, activations and removals, by edits of the MCP allowlist of an integration or a user, and by a change of a user's `admin` flag, through any API.
  The stream reads the list versions from the database, so changes made on the CLI, in queue workers or on another host arrive as well.
  Each host reads them at most once a second, through a `cache.app` entry.
  The Store API endpoint does not announce list changes on the stateless revision, because its lists only change per session there.
  Clients on the session-based revisions receive the same changes as `list_changed` notifications in their session, so an allowlist edit now reaches them as well.
* Each open `subscriptions/listen` stream holds one PHP-FPM worker for up to 25 seconds, then the server ends it and the client reconnects.
  A client that keeps listening therefore occupies one worker almost all the time.
  Account for it in `pm.max_children`, and keep `max_execution_time` and the timeouts of proxies in front of PHP above 25 seconds.
* List changes on the Admin API endpoint, for example after an app install, reach clients through `subscriptions/listen`.
  The notifications go through `cache.app`, so setups with several hosts need a shared `cache.app`, for example Redis.
* Each open `subscriptions/listen` stream occupies a PHP worker for up to 25 seconds before the client reconnects.
  Account for it in `pm.max_children`.
  The Store API endpoint publishes no notifications, so it answers `subscriptions/listen` with the JSON-RPC error `-32601` instead of opening a stream.

### Rules match the address of a visitor without a customer

A visitor who is neither logged in nor registered can now send an address, which is stored on the context as `SalesChannelContext::getProspectiveCustomer()`.
No customer or address is written, and `SalesChannelContext::getCustomer()` stays `null`.
The addresses are available as `ProspectiveAddress` structs with readonly properties.
The shipping location of such a context contains the address, so zip code, city and street rules and zip code tax rules match before registration.

Rule matching sees a customer that only exists for the rules: a guest of the default customer group without order history, with the sent shipping and billing address.
This affects `CheckoutRuleScope::getCustomer()` and therefore guest, active, order history and billing address rules.
Company, newsletter recipient and "created by admin" rules set to "no", and customer custom field rules with "not equal", match as well.
Rules that need contact or account data, such as e-mail, tags or birthday, do not match before registration.
Custom rules that load the customer by its id find no customer.

Shops with such rules see them apply earlier in the checkout once their frontend sends an address.
Contexts without an address behave as before.
The prospective customer is removed when the visitor logs in or registers.

### Manifest validation runs on every app install and update

Manifest validation now also runs when an app is installed or updated from the store, the Administration or as a service, not only through the CLI.
Errors make the install or update fail, and all of them are reported at once.
Notices no longer stop `app:install` and `app:refresh`, run `app:validate` to see them.
`app:validate` now fails only on errors and exits with 0 when an app has notices only.

### Deprecated `PluginZipImportCommand::$cacheClearer`

The property is unused and will be removed in 6.8.
`--no-validate` on `app:install` and `app:refresh` is deprecated and will be removed in 6.8.

### System config cache is invalidated per sales channel

Saving the system config of one sales channel now only drops the cached config of that sales channel.
A change of the global config still drops the cached config of all sales channels.
`CachedSystemConfigLoader` tags each cached sales channel config additionally with `CachedSystemConfigLoader::buildSalesChannelTag($salesChannelId)`.
Invalidating `CachedSystemConfigLoader::CACHE_TAG` still drops the whole cached config.

### MCP answers carry cache hints on the 2026-07-28 revision

On the stateless 2026-07-28 revision, both MCP endpoints now tell clients how long they may reuse an answer (`ttlMs` and `cacheScope`, SEP-2549).
Every hint is `private`, because the allowlist and `?toolsets=` make the answers differ per caller.
`server/discover`, `tools/list`, `prompts/list`, `resources/list` and `resources/templates/list` stay fresh for 60 seconds, so an allowlist change reaches a client within a minute, even if it does not listen for `list_changed`.
The reference resources stay fresh for one hour (`shopwell://state-machines`) or 60 seconds (`shopwell://entities`, `shopwell://business-events`, `shopwell://flow-actions`, `shopwell://currencies`, `shopwell://languages`, `shopwell://sales-channels`).
Entities, business events and flow actions can change when an app is installed, so they get the shorter time.
Everything else, including stored tool results and app resources, is not cached.
Answers on the handshake revisions carry no hints and are unchanged.

The core reference resources now return a `Mcp\Schema\Result\ReadResourceResult` instead of an array.
A plugin resource can set its own hint by returning a `ReadResourceResult` with `ttlMs` and `cacheScope`; only do so on the 2026-07-28 revision, as `RequestContext::getProtocolVersion()->isModern()` tells.

### Deprecated `ImitateCustomerTokenGenerator::HMAC_HASH_ALGORITHM`

The constant is unused and will be removed in 6.8.
The imitation token is signed by the internal `HmacSigner`.

### Order deliveries keep the order of the cart deliveries

Order deliveries have a new `position` field with the order of the cart deliveries when the order was placed, starting at 1.
Extensions that build several cart deliveries in a deliberate order, for example by priority or release date, no longer need to persist that order themselves.
Sort order deliveries by `position` to show them in that order; the primary delivery is still the one with the highest shipping costs.
Recalculating an order numbers the deliveries from 1 again in the order of their stored positions, like line items, with deliveries added by the recalculation, such as shipping discounts, last.
Deliveries of orders placed before this release have position `0` until the order is recalculated.

### Event before a media file is replaced

`Shopwell\Core\Content\Media\Event\BeforeMediaFileReplacedEvent` is dispatched before an upload replaces the file of a media.
It carries the media ID and the extension, MIME type and size of the new file.
A listener can throw an exception to stop the replacement, and the media keeps its old file.
Presigned replacements dispatch it in `prepare()` with the file the client declares, before the upload, and again in `finalize()` with the stored file.
The new optional `fileSize` of `POST /api/_action/media/presign-upload` fills the size in `prepare()`; without it, the size is `null`.
With `shopwell.cdn.path_cache_buster` disabled, a presigned upload of the same file name and extension has already overwritten the old file when `finalize()` stops it.

### Default tax rules use the current VAT rates

The update changes the default tax rules of Czechia, Estonia, Finland, Lithuania, Romania and Slovakia to the current VAT rates, and adds a 5% rule for "Reduced rate 2" in Slovakia and Slovenia.
Only rules of the default tax groups that still have their original rate and were never edited are changed.
Shops that adjusted these rules themselves keep their rules, and should compare them with the current rates.

### Address-format snippets use Twig whitespace control

The shared address-format renderer uses Twig whitespace control instead of the deprecated `spaceless` filter.
Whitespace between HTML tags within custom snippets is now preserved; use Twig whitespace control in those snippets when the tags must be adjacent.

### JSON keys named extensions in DAL criteria

DAL filters, sorting and aggregations preserve JSON keys named `extensions`, including paths such as `customFields.extensions.foo`.
Previously, these paths could silently read a different JSON key.

### Elasticsearch indexing uses bounded memory

`es:index` and automatic reindexing after updates now dispatch batches while scanning the catalog, reducing PHP memory usage for large catalogs.
This also applies when running `es:index --no-queue`.
Queued indexing still requires sufficient message transport capacity.

### Webhooks are authorized against their owner

Webhooks created through the Admin API without an app now belong to the user or integration that created them, and only receive the events their owner is allowed to read. Nothing changes for webhooks an app registers in its manifest.

* **Owner:** The owner is recorded from the session and cannot be set through the API. An admin owner receives every event it subscribes to; a non-admin owner only receives events it has the read privilege for. A user or integration that still owns webhooks can't be deleted (`409`, `FRAMEWORK__DELETE_RESTRICTED`): delete its webhooks first.
* **Existing webhooks:** Existing webhooks without an app have no recorded creator, so the update creates an admin integration labelled "Legacy webhooks", assigns them to it and prefixes their names with `[Legacy] `. They keep receiving every event they subscribe to. Audit them after updating and recreate any that should run with narrower privileges as the user or integration they belong to. The "Legacy webhooks" integration can only be deleted after its webhooks. To find them, search by the name prefix through the Admin API:

  ```http
  POST /api/search/webhook
  { "filter": [{ "type": "prefix", "field": "name", "value": "[Legacy] " }] }
  ```

  or in the database:

  ```sql
  SELECT LOWER(HEX(w.id)) AS id, w.name, w.event_name, w.url
  FROM webhook w
  JOIN integration i ON i.id = w.owner_integration_id
  WHERE i.label = 'Legacy webhooks';
  ```
* **Webhooks an app created through the API:** They belong to the app's integration, so they are no longer delivered while the app is inactive, and they are deleted when the app is uninstalled.
* **Subscriptions are validated:** `POST /api/webhook` and `PATCH /api/webhook/{id}` now check the event a webhook subscribes to, the same check webhooks in app manifests already get. Requests are rejected with a `400` and `FRAMEWORK__WEBHOOK_EVENT_NOT_PERMITTED` when webhooks cannot receive the event, or `FRAMEWORK__WEBHOOK_EVENT_PRIVILEGES_MISSING` when the owner lacks a privilege the event requires. A non-admin owner needs the read privilege of the event in addition to `webhook:create`, e.g. `product:read` for `product.written`. Previously any event name was accepted and the webhook simply never fired.
* **Only the owner may change a webhook:** A webhook without an app can only be updated or deleted through the API by its owner or an administrator. Anyone else gets a `400` and `FRAMEWORK__WEBHOOK_NOT_OWNED`, regardless of their `webhook:update` and `webhook:delete` privileges.
* **App webhooks are managed by their manifest:** Webhooks with an `app_id` can no longer be updated or deleted through the Admin API or Sync API (`400`, `FRAMEWORK__APP_WEBHOOK_NOT_MODIFIABLE`). The only exception: an administrator may change their `active` flag, e.g. to switch a webhook back on after it was switched off for failing deliveries. Webhooks can also no longer be cloned through `POST /api/_action/clone/webhook/{id}` (`403`, `FRAMEWORK__CLONE_PROTECTED`).
* **Limiting a webhook to some of its owner's roles:** A webhook without an app accepts an optional `aclRoleIds` list. It then only receives events whose required permissions are covered by these roles. Leave it out to use all of the owner's roles.

  ```json
  { "name": "Product sync", "eventName": "product.written", "url": "https://example.com", "aclRoleIds": ["0193b1c2d3e47f5a8b9c0d1e2f3a4b5c"] }
  ```

  The list must not be empty or contain empty entries, every role must exist, and every role must be held by the owner unless the owner is an administrator. Otherwise the write is rejected with `FRAMEWORK__WRITE_CONSTRAINT_VIOLATION` on `aclRoleIds`. If the event is not covered by the listed roles, `FRAMEWORK__WEBHOOK_EVENT_PRIVILEGES_MISSING` is returned, also when an administrator changes somebody else's webhook.
* **Deleting a role:** A role that is listed in the `aclRoleIds` of a webhook cannot be deleted.
  The deletion is rejected with `FRAMEWORK__WEBHOOK_ACL_ROLE_USED_BY_WEBHOOK`; remove the role from the webhook first.
* **Event log:** `webhook_event_log` entries are only returned to administrators and to the owner of the webhook that wrote them. An app's integration sees the entries of its app's webhooks. Entries written before the update, and entries of deleted webhooks, are only returned to administrators.
* **Refused deliveries are logged:** When a webhook's roles do not permit an event it subscribes to, the event is not sent and a `webhook_event_log` entry with the delivery status `not_permitted` and no request content is written instead.
  These entries are removed after the same lifetime as successful and failed ones.

### New `ProductListingLoader::STATE_INCLUDE_INACTIVE` criteria state

`ProductListingLoader` has a new criteria state `STATE_INCLUDE_INACTIVE`.
Listings without this state are unchanged.
With the state, `ProductListingLoader::load()` no longer hides sold-out closeout products and checks the products and the configured variants with the `ProductAvailableFilter` of the criteria.
To include inactive products, add a `ProductAvailableFilter` without the active condition to the criteria.
Each group then shows its configured variant or parent, even if the variant is inactive or a sold-out closeout product.

The product stream preview in the Administration uses this state when "Keep matching variants grouped" is active, so each group shows the variant configured in the product's storefront presentation.

### Product documents

Products can have documents, such as manuals, datasheets or certificates, in `product.documents`.
A document can be uploaded per language with a translatable title and fall back to the default language.
A variant can have its own documents or inherit those of its parent.
The settings `shopwell.product_documents.allowed_extensions` and `shopwell.product_documents.max_file_size` limit the files, by default to PDF, DOCX, JPG and PNG up to 20 MB.
The scheduled task `product_document.media.cleanup` runs about once a month and deletes files from the "Product Document Media" folder that no product document or other entity uses and that were uploaded more than a day ago.
Keep files you link elsewhere, for example from a product description, in another folder.
To turn the cleanup off, run `bin/console scheduled-task:deactivate product_document.media.cleanup`.

### Replacing a media file invalidates every cached page showing it

Changing a media file used to invalidate only the product detail pages of products that use the media as product media.
It now also invalidates the listings and dynamic product groups containing those products,
categories using the media as category image,
layouts using it as section or block background or in an image, image slider, image gallery, video or manufacturer logo element,
product pages of manufacturers using it as logo,
and categories, products and landing pages using it in their layout overrides.
Replaced images show up on these pages without clearing the HTTP cache.

The media of layouts are mapped in the new table `cms_page_media_usage`,
the media of layout overrides in `category_translation_media_usage`, `product_translation_media_usage` and `landing_page_translation_media_usage`,
so replacing a file does not have to search the JSON of the content.
After the update, the indexers of the same names with the suffix `.indexer` fill the tables for the existing content.

The media of an element are read by its media ids provider, a service implementing the new `CmsElementMediaIdsProviderInterface`.
Services implementing it are tagged with `shopwell.cms.element_media_ids_provider` automatically.
The core registers providers for the image, image slider, image gallery, video, YouTube, Vimeo and manufacturer logo elements.
Elements showing media should register a provider as well, so replacing those media invalidates the pages showing them.
The first provider supporting the element type is used, so a provider with a higher tag priority replaces the one of the core.
Elements whose media ids can be found by paths in their config can register the `CmsElementMediaIdsProvider`.
It takes the resolver of the element, which names the element type, and the paths to the media in the config.
A path starts with the config field, `*` stands for every entry of a list, so `sliderItems.*.mediaId` reads the `mediaId` of every slider item:

```php
$services->set('my_plugin.cms.element_media_ids_provider.my_gallery', CmsElementMediaIdsProvider::class)
    ->args([service(MyGalleryCmsElementResolver::class), ['slides.*.picture.media.*.id', 'background.mediaId']])
    ->tag('shopwell.cms.element_media_ids_provider');
```

### Elasticsearch index updates schedule a reindex when analysis settings change

When updating an Elasticsearch/OpenSearch mapping references an analyzer/normalizer that the live index's analysis settings do not define (for example after an update introduced a new analyzer), `putMapping` fails with `analyzer [...] has not been configured in mappings`. Analysis settings are fixed at index creation and cannot be added to a live index, so this is now handled like the other unrecoverable mapping errors: the affected entity is scheduled for a reindex into a freshly created index, which rebuilds it with the current analysis settings instead of leaving the outdated mapping in place.


## Administration

### Extension points for document creation and preview

Extensions can add fields through the `sw_order_create_document_modal_type_specific_fields_additional` Twig block and override `createDocumentV2()` and `previewDocumentV2()` on the document card.

### Removed the empty Shopwell Payments navigation module

The `sw-payments` placeholder module, `sw.payments.index` route and `sw-payments.*` privilege mapping were removed.
Extensions must register their own functional payment pages instead of attaching navigation to this placeholder.
The `sw-payment` payment orders and refunds module remains available.

### Payment route filters

Payment order filters and columns read `activeAttempt.channelTechnicalName` and `activeAttempt.providerTechnicalName`.
Refund channel filters read `paymentAttempt.channelTechnicalName`, so they retain the route used by the refunded payment attempt.
The `payment_order.viewer` and `payment_refund.viewer` mappings include `payment_attempt:read`.

### Administration navigation layouts

Administrators can choose sidebar, top, or combined navigation under My profile > User interface and apply the choice by saving their profile.
The preference is stored per user under `core.adminNavigationLayout`; smaller screens continue using the navigation drawer.
All layouts use the configured menu order, role restrictions and registered module navigation.
Top and combined layouts place the existing page search and toolbar actions in one compact header.
Sales channels use their own navigation area instead of appearing in every module sidebar.
Top navigation is the default for accounts without a saved layout preference.
Transfer requests expose only the finance-enabled release to users with transfer creation permission.
Articles, categories, authors and comments are grouped under Content > Articles.
Payment orders and refund orders are direct entries under Finance, alongside transfer requests and wallet transactions.

### Return settings per sales channel

Return rules support sales channel overrides and inheritance from the global configuration.
Return reasons have a separate settings entry and remain shared across sales channels.
The return rules page uses the standard `system.system_config` permission; reason management continues to use the return permissions.

### Role-based menu assignments and separate user management

User management now uses the `sw-user` module and `user.*` privileges.
Roles, business permissions and menu configuration use `sw-permissions`, `permissions.*` and `admin_menu.*` respectively.
Role details combine explicit menu selection with the existing permission matrix and dependency handling.
Role details now display special operations alongside their owning page and show the pages sharing a capability.
Modules can declare an optional `resource` on an operation privilege role to associate it with a business permission key; this metadata does not grant access.
The verified role write endpoint accepts a complete `menuIds` selection, saved atomically with role privileges.
Menu selections do not grant page or API access, and an empty selection hides navigation.
Role menu selections combine across roles; a role without selected menus contributes no navigation.
The visible menu response includes `allowUnmanaged` to control client declaration fallbacks.
Menu-side role assignment and the `/_action/admin-menu/assign-roles` endpoint were removed.

### Configurable administration menus

Core and plugins declare nested menu defaults in `Resources/config/admin-menu.xml`, using readable stable IDs and literal localized titles.
The container validates and compiles XML; plugin files are discovered automatically for active plugins.
Translated `customFields` are available on `admin_menu_item` and `admin_menu_item_translation`, and XML can provide per-language values.
Unmodified stored defaults follow declarations; administrator customizations remain preserved.
Frontend navigation bindings supply routes and privileges, while XML owns labels, grouping and order.
The visible and route-whitelist endpoints accept `locale` for interface-language titles.
Plugins can subscribe to `AdminMenuVisibilityEvent` to hide declared entries and their subtrees based on runtime conditions; role selections and route/API permissions still apply.
See [Administration menus](technical-docs/03-extensibility/10-admin-menu.md) for declarations and extension examples.

### Organization workspace

Organizations and positions are under System, using the `sw-organization` and `sw-position` modules and the `sw.organization.*` and `sw.position.*` routes.
The organization workspace displays organizations as independent roots and units nested below them.
The index, create and detail routes reuse this workspace; the detail route accepts `unitId` and `parentId` query parameters to restore unit selection and creation context.
Extensions can use the sidebar and node action slots of `sw-organization-unit-tree`, which receives organizations and units.
The detail blocks use the `sw_organization_detail_` prefix.
New organizations open `sw.organization.create`; new units open an unsaved entity in the detail form with the selected parent prefilled.
Changes are persisted through Save and reverted through Cancel using the entity repository.

### Blog editor and extension points

The Blog editor provides a wide content canvas, a compact settings sidebar, and separate Content, Layout and SEO tabs.
Register custom editors through `registerBlogType($type, { component, label, icon })`; existing lazy editor loaders remain supported.
Named editors use the Administration component factory, so component extensions apply.
Plugins can extend `sw-blog-detail-content`, `sw-blog-detail-settings`, and `sw-blog-detail-tabs` through the standard component section and tab extension APIs.
Tags use the system tag selector within the editor and do not add a navigation item.
Blog navigation now groups content, categories, authors, and comments under a localized menu folder.
Category management uses one workspace with a persistent category tree and an inline editor with General, Content, Layout, and SEO tabs.
The category tree follows the system menu tree with drag handles, positional drop previews, sibling and child creation, and expand/collapse actions.
Category search uses the standard page search bar, retains matching ancestor paths, and restores expanded branches when cleared.
Root creation uses the page actions; sibling and child creation use the node action menu.
New categories open an unsaved full form in the same workspace and are persisted only through Save.
New authors open the `sw.blog.author.create` route and use the full detail form; records are persisted only through Save.
Categories support parent and child relationships, drag and drop ordering, and menu actions for moving categories.
Tree changes persist immediately after resolving unsaved editor changes.
Moving a category into itself or its descendants is rejected, and categories with children must be emptied before deletion.
The Layout tab displays the effective global layout and supports copying it or restoring the shared default.

### Today dashboard statistics

The Administration homepage now shows today's product sales, successful refunds, wallet top-ups, membership activations, and hourly sales in the operator's timezone.
`GET /api/_admin/dashboard/today` provides these statistics in the system currency, with comparisons to the same local time yesterday for all four metric cards.
The endpoint derives its timezone from the authenticated user profile.
Product sales exclude wallet top-ups and membership purchases, and duplicate successful payment attempts do not create additional sales.
Posted wallet top-ups are reported separately with amount and count, subject to wallet transaction read permission.
Membership activations count distinct customers by successful activation time, including renewals, subject to membership purchase read permission.
Wallet amounts use current exchange factors and are accumulated in system currency minor units.
The former order-amount endpoint has been replaced by the today endpoint.


### Deprecated the sales channel lists of `sw-settings-search-live-search` and `sw-newsletter-recipient-list`

The `salesChannels` data property, the `salesChannelRepository` computed property and the `fetchSalesChannels()` method of `sw-settings-search-live-search` are deprecated and will be removed without replacement.
The `salesChannelFilters` data property of `sw-newsletter-recipient-list` is deprecated and will be removed without replacement.
Their sales channel selects load their options themselves, so with the `V6_8_0_0` feature flag both components no longer load sales channels when they are created.

### Meteor apps get their own path in the Administration watcher

An extension that ships an Administration entry and a `meteor-app` used to get both dev servers under `/_internal_ext/<technical-name>/`.
Both servers shared one proxy path, so the Administration only reached one of them.
The watcher now serves meteor-apps under `/_internal_app/<technical-name>/`, also for extensions without an Administration entry.
The Administration reads the new URL from `sw-plugin-dev.json`, so you only need to adjust tooling that calls the old URL of a meteor-app directly.

### The Administration watcher applies template edits without a page reload

While `composer watch:admin` runs, saving the Twig template of a component or of a component override updates that component on the page and keeps its state.
Components that extend it update as well.
The watcher still reloads the page when it cannot apply an edit in place:
for a Twig override of a component without a Twig template, for a template whose content another template file has as well, or for a component that has not been rendered yet.

With the `ADMIN_VITE_8` feature flag, the watcher also applies script edits of native `.vue` components and `.override.vue` files of extensions without a page reload.
This includes edits of the files such a component imports.
The watcher remounts the edited component and the components an edited override changes.
It reloads the page for edits it cannot apply in place:
edits that change the blocks of a component, edits of a component built with `Component.extend()` or `Component.override()` or of a component that is not on the page, and updates that fail to render.

### Experimental: Vite 8 and HTTP/2 for the Administration behind the `ADMIN_VITE_8` feature flag

The new `ADMIN_VITE_8` feature flag builds the Administration and the Administration code of extensions with Vite 8.
Vite 8 becomes the default with Shopwell 6.8, so you can use the flag to test your extensions now:

```bash
ADMIN_VITE_8=1 composer watch:admin
ADMIN_VITE_8=1 composer build:js:admin
```

With the flag, building the Administration requires Node.js `^20.19.0 || >=22.12.0`.
The `postinstall` script of the Administration installs Vite 8 into `build/vite-8`.
If your CI restores the Administration dependencies from a cache without running that script, also run `npm ci` in `build/vite-8`.

With the flag, the watcher serves all files over HTTP/2.
Browsers only use HTTP/2 over HTTPS, so put HTTPS in front of the watcher:

* Run `shopwell-cli project proxy up` and open `https://admin-watch.<project>.shopwell.local`.
* Or set `SHOPWELL_ADMIN_HTTPS_CERTIFICATE_FILE` and `SHOPWELL_ADMIN_HTTPS_KEY_FILE` to a certificate and its key, for example created with [mkcert](https://github.com/FiloSottile/mkcert).
  Relative paths are resolved from the project root.

With the flag, the watcher serves the Administration code and the meteor-apps of extensions through its own port.
The separate `Extensions` process and the port per extension are gone.
The `vite.config` of an extension can no longer change the address of its dev server.
The watcher ignores `server.port`, `server.host`, `server.ws` and the connection options of `server.hmr` (`protocol`, `host`, `port`, `clientPort`, `path` and `server`) there.

To try the experimental [bundled dev mode](https://vite.dev/blog/announcing-vite8-1#experimental-bundled-dev-mode) of Vite 8, also set `SHOPWELL_ADMIN_BUNDLED_DEV=1`.
It bundles the Administration and its plugins, but not apps.
Changes to plugin files reload the page instead of using HMR.

Vite 8 does not match extglob patterns like `!(*.spec)` in `import.meta.glob`.
Replace them with negated patterns like `['./**/*.{j,t}s', '!./**/*.spec.{j,t}s']`.
`administration:check-extensions` warns about them, and `administration:upgrade-extensions` rewrites them.
Until 6.8, Shopwell rewrites them when the flag is active and logs a warning for each one.
If your Jest setup uses `babel-plugin-shopwell-vite-meta-glob`, update it to 0.0.6 or newer, because older versions ignore negated patterns.

For the other changes, see "Administration build updated to Vite 8" in `UPGRADE-6.8.md`.

### `sw-chart` loads only the chart locale it needs

`sw-chart` now loads the ApexCharts locale of the current Administration language on demand instead of importing all 33 locales up front.
English uses the locale built into ApexCharts and loads nothing.
The chart renders once the locale has loaded.
`createdComponent()` and `loadLocaleConfig()` of `sw-chart` now return a promise.
If your override reads `localeConfig` after calling the base method, await it:

```js
async loadLocaleConfig() {
    await this.$super('loadLocaleConfig');
    // this.localeConfig is set here
}
```

### The smart bar back button names its target

The back button of `sw-page` shows the title of the module its parent route belongs to, e.g. "Products" on a product detail page.
Without a resolvable title it shows only the icon, labelled "Go back".
Pages whose route hierarchy does not lead back to where users expect can pass the new optional `backRoute` and `backLabel` props of `sw-page`.

### Custom fields on the customer create page

The customer create page now shows the custom field sets assigned to customers, so custom fields can be filled in when a customer is created.
They are rendered in the new block `sw_customer_create_custom_field_sets` in `sw-customer-create.html.twig`.

### `blocks-list.json` is replaced by the public API snapshot

The Administration no longer ships `blocks-list.json`.
The block names are now in `public-api/index.json`, listed per component.
A component that extends another one also lists the blocks it inherits.
All `blocks` arrays of that file together hold the names of the old list, except eight blocks of extend templates that the Administration never rendered.
Beside it, one `.api.md` report per component shows its blocks with their nesting, for the current release and for the next major.

Tools that read `blocks-list.json` have to read `public-api/index.json` instead.
The format of the files in `public-api/` can still change while the snapshot is being built.
Core contributors run `composer admin:generate-public-api` instead of `composer admin:generate-blocks-list` after changing a block.

### Experimental: `Feature.silence()` and `sw-silence-deprecations`

Both silence the deprecations of a major on the pre-major side of a feature-flag branch, so an extension can support both sides of a major without warnings:

```js
if (!Shopwell.Feature.isActive('V6_8_0_0')) {
    Shopwell.Feature.silence('V6_8_0_0', () => this.legacyLoad());
}
```

```html
<sw-silence-deprecations v-if="!feature.isActive('V6_8_0_0')" flag="V6_8_0_0">
    <sw-deprecated-component />
</sw-silence-deprecations>
```

The component silences the first level of components inside of it, ignoring plain HTML, and everything they render themselves.
Event handlers, watchers and code after an `await` need `Feature.silence()`.
Once the major is active, both silence nothing.

### Administration HTTP client runs on a single Axios 1.x transport

The Administration `httpClient` is now one Axios 1.x instance (1.20.0).
The legacy Axios 0.x dependency and the `axios-v1` package alias were removed, which drops the second Axios copy from the Administration bundle.
The opt-out survives the removal: `useAxiosV1: false` now selects a compatibility mode on the single client that keeps `[` and `]` literal in query strings, including `httpClient.getUri()`, and returns `response.headers` as a plain object.
The defaults are unchanged, legacy behaviour on 6.7 and Axios 1.x once `V6_8_0_0` is active.
Request interceptors now run on Axios 1.x on 6.7 too, and no opt-out restores how they see headers.
`config.headers` is one flat `AxiosHeaders` instance, so an interceptor that writes `config.headers.common[...]` or `config.headers.post[...]` fails with a `TypeError`.
Set such headers on `config.headers` directly.
Repository requests return plain response headers again on 6.7, as they did before 6.7.14.
This applies to every response a `Repository` method returns, for example from `save()`, `delete()`, `assign()`, `mergeVersion()` and `deleteVersion()`, and to `error.response.headers` of every failed repository request.
`useAxiosV1`, `httpClient.CancelToken`, the `cancelToken` option, passing a `CancelToken` to `messageQueueService.consume()`, and the `axiosV0`, `axiosV1`, `interceptorsV0`, `interceptorsV1`, `defaultsV0` and `defaultsV1` aliases are deprecated and removed in 6.8.
They log a development-mode warning on first use.
Use `AbortController` with the `signal` request option to cancel requests and `httpClient.isCancel(error)` to detect cancellations.
Details are in `UPGRADE-6.8.md` and the migration guide at `src/Administration/Resources/app/administration/technical-docs/09-security/axios-migration-guide.md`.

### Extension Jest configs can compose the Administration test harness (experimental)

The Administration extension tooling now ships a Jest config factory, `extension-tooling/jest.mjs`, next to its TypeScript and ESLint presets.
It carries the harness the Administration's own specs run on: the `shopwell:*` virtual modules, the feature flag registry behind `Shopwell.Feature.triggerDeprecationOrThrow()`, `it.deprecated()` and `it.activeFeatureFlags()`, the global `Shopwell` setup and the transforms.
An extension Jest config that composes it picks up every later change to the harness without editing its own config:

```js
// jest.config.mjs, beside the generated .shopwell/ bridge
import { shopwellAdminJestConfig } from './.shopwell/jest.mjs';

export default shopwellAdminJestConfig({
    moduleNameMapper: { '^MyPlugin/(.*)$': '<rootDir>/src/$1' },
});
```

`composer admin:setup-extension-tooling` (or `bin/console administration:setup-extension-tooling` in a Composer install) generates the `.shopwell/jest.mjs` bridge for every extension.
The harness works in a platform checkout and in a Composer install: the `shopwell/administration` package now ships the Administration's Jest setup files (without spec files).
Before the first run, generate the entity schema with `bin/console administration:generate-entity-schema-types`.
Run the specs with the Administration's own Jest through `composer admin:test-extensions -- --only=MyPlugin`, or `bin/console administration:test-extensions -- --only=MyPlugin` in a Composer install, so the extension needs no Jest installation of its own.
`--all` tests every extension with a Jest config, vendor-installed ones included.
As in `admin:check-extensions`, a failing vendor-installed extension fails the run only with `--strict-vendor`.
A Jest config that does not build on the harness runs on the Jest the extension installs itself;
when its `package.json` declares Jest that is not installed, the command skips the config and names the `npm ci` or `npm install` that installs it.
`admin:test-extensions` generates the component import map when it is missing;
when you call Jest directly, generate it with `npm run unit-setup` in the Administration directory.
Before the first run, generate the entity schema with `bin/console administration:generate-entity-schema-types` and the component import map with `npm run unit-setup` in the Administration directory.
The factory is part of the experimental extension tooling, so the terms of [Opt-in TypeScript and ESLint configuration for Administration extensions](#opt-in-typescript-and-eslint-configuration-for-administration-extensions-experimental) apply to it as well.

### Administration deprecation notices go through the feature lifecycle

The hand-rolled deprecation warnings in the Administration now go through `Shopwell.Feature.triggerDeprecationOrThrow()`, so they throw once their major flag is active instead of staying a console message forever.
This covers `sw-tabs`, `sw-loader`, `sw-popover`, `sw-skeleton-bar`, the `items` prop of `sw-entity-listing`, the Options API compatibility shim and the legacy theme config labels and help texts in `sw-theme-manager-detail`.

Deprecation notices for the legacy Twig override shim and for deprecated extension SDK position identifiers and data sets still only warn and do not throw, because no removal version exists for them yet.

### Deprecated Administration APIs warn when they are used

Deprecated Administration JavaScript APIs now call `Shopwell.Feature.triggerDeprecationOrThrow()` when they are used: deprecated `Shopwell.*` globals including `Shopwell.State`, services, exported functions, component `methods` and `computed` members, also through `this.$super()`, as well as deprecated components and props.
In development builds, a use logs a warning naming the API and its replacement.
With the next major flag active, it throws unless baselined.

### Order detail shows every shipment in 6.8

With the major feature flag `v6.8.0.0`, the order detail tells shipments and shipping discounts apart by their shipping costs.
A delivery with negative shipping costs is a discount, every other delivery is a shipment.
Until now, every delivery besides the primary one was shown as a shipping discount.
The general tab lists the shipping costs of each shipment with its number and shipping method, and the details tab shows one delivery card per shipment, sorted like `OrderDeliveryCollection::getShipments()`.
Overrides of the Twig blocks inside `sw_order_detail_details_shipping` of `sw-order-detail-details` render once per shipment, see `UPGRADE-6.8.md`.

### Product list filters by product documents

The product list has a new "Documents" filter, registered as `product-documents-filter`.
It finds the products with or without product documents; variants count the documents they inherit from their parent.
With `ENABLE_OPENSEARCH_FOR_ADMIN_API`, run `bin/console es:admin:index` after the update.

### Webhooks in the settings

The new page Settings > System > Webhooks lists all webhooks with their owner and the roles they are limited to, and shows the recent deliveries of each webhook.
Users with the new "Webhooks" permissions can switch webhooks on and off and delete webhooks that do not belong to an app.
The ownership rules of the Admin API still apply, so only the owner or an administrator can change a webhook.

### Dynamic product groups filter by product documents

Dynamic product groups have a new "Has documents" condition with the values "Yes" and "No".
It stores a filter on `documents.id` being null or not null; variants count the documents they inherit from their parent.
With Elasticsearch, run `bin/console es:index` after the update; until then the condition gives wrong results.

### Product documents in the Administration

You can add documents on the product detail page, and they are saved in the public folder "Product Document Media" by default.

### TypeScript Administration scaffolding

`bin/console plugin:create --create-admin-module` now generates `main.ts` and TypeScript module and page implementations.
Generated modules and components are checked against the installed Administration types.
After installing and activating the plugin, run `bin/console bundle:dump`, `composer admin:setup-extension-tooling` and `composer admin:check-extensions -- --only=YourPlugin --fail-on-skipped` in a platform checkout to typecheck and lint it.
Existing plugin files are unchanged.

### Block renaming

Due to misleading block names, the following blocks have been deprecated and will be removed in v6.8.0. Use the respective replacements instead:

* `sw_settings_listing_option_base_smart_content` -> `sw_settings_listing_option_base_content`
* `sw_settings_listing_option_base_smart_content_general_info` -> `sw_settings_listing_option_base_content_general_info`
* `sw_settings_listing_option_base_smart_bar_actions_grid` -> `sw_settings_listing_option_base_content_criteria_grid`
* `sw_settings_listing_option_base_smart_bar_actions_grid_delete_modal` -> `sw_settings_listing_option_base_content_delete_modal`


## Storefront

### Production Vite builds generate JavaScript SBOMs

Production Vite builds now generate CycloneDX JSON inventories at `Resources/sbom/storefront/shopwell.cdx.json` for the shared Shopwell module and `Resources/sbom/storefront/components.cdx.json` for each bundle's components.
The reports are kept outside public assets and are not published by `assets:install`.
Extension component builds include bundled npm packages regardless of their development-dependency classification, including when using custom Vite configurations.
The reports also include dependencies bundled into Web Workers, including shared and inline workers.
Retain these reports when packaging extensions after source cleanup.

### Skip link for listing filters

Category, landing and search pages with a filtered product listing now offer a "Skip to products" link in the skip-to-content bar, so keyboard users can jump past the filters to the products.
The link targets the new `id="product-listing"` on the product listing wrapper, including the `Sw:Product:Listing` component, and is only shown from the `lg` breakpoint, where the filters are not collapsed into the off-canvas button.
Themes that override the `product_listing` block in `storefront/component/product/listing.html.twig` without `parent()` must keep `id="product-listing"` on their wrapper, otherwise the link has no target.
`storefront/component/skip-to-content.html.twig` has a new `productListing` parameter (default `null`) and the new block `component_skip_to_content_product_listing`.

### Checkout and account pages list every shipment

When a cart or order has more than one shipment, the confirm, finish and edit order pages and the account order history list each shipment with its shipping method, address, shipping costs and delivery period, and the account order list shows the state of every shipment.
Deliveries with negative shipping costs are shipping discounts and are not listed as shipments.
`AccountOrderPageLoader` and `AccountOverviewPageLoader` now load the shipping address and the state of every delivery, independent of the `v6.8.0.0` feature flag.
`CheckoutFinishPageLoader` and `AccountEditOrderPageLoader` load the shipping method and the shipping address of every delivery, also with the flag active.
Carts and orders with one shipment render as before.
The new component `@Storefront/storefront/component/checkout/shipment.html.twig` renders a single shipment.
The lists sit in the new blocks `page_checkout_confirm_address_shipping_shipments`, `page_checkout_finish_address_shipping_shipments`, `page_checkout_finish_order_dispatch_method_shipments` and `page_account_order_item_detail_shipments`.

### Guest customers can create an account from the account menu

The header account menu now shows a "Create account" button for guest customers in the new block `layout_header_actions_account_widget_dropdown_guest_convert` in `storefront/layout/header/account-menu.html.twig`.
It leads to the guest conversion page (`frontend.account.convert.page`), which was previously only linked from the checkout finish page.

### Static theme compilation refreshes the theme runtime configuration

With `StaticFileConfigLoader`, compiling a theme now refreshes the whole theme runtime configuration, including the script files and the import map, like the `DatabaseConfigLoader` does.
Activating, updating or removing an extension with storefront JavaScript, or running `theme:compile`, updates the scripts the storefront loads, so running `theme:refresh` afterwards is no longer necessary.
Without a reachable database, theme compilation still works and skips the runtime configuration refresh.

### New documents tab on the Storefront product detail page

The product detail page has a new "Documents" tab that lists the documents of the product, which customers can click to download.

### Paginated category listings are crawlable

The default storefront `robots.txt` now contains `Allow: /*?p=` and `Disallow: /*?p=*&` alongside the existing rule `Disallow: /*?`, so search engines can crawl paginated category listings like `/category/?p=2`.
The default rules still block sorted, filtered, and search pagination URLs such as `/category/?p=2&order=price-asc`, `/category/?p=2&properties=…`, and `/search?p=2&search=…`.
Shops that want to keep pagination out of crawling can override the `robots_txt_content_default_rules` block.

A category page only gets a paginated canonical like `/category/?p=2` when it shows a product listing on that page, other pages keep their clean canonical.
From the second listing page on, the meta title ends with the page number, for example `Seasonal decoration – Page 2`.
The format comes from the new snippet `general.pagination.metaPageSuffix`, set it to `%content%` to keep the title unchanged.

### Text alternative for the legal guarantee notice

The notice SVGs are now `aria-hidden`, and the notice link is described by the notice's text, visually hidden and rendered by the new Twig filter `sw_legal_guarantee_notice_text`.
The link's `aria-label` uses the new snippet `checkout.confirmLegalGuaranteeNoticeYourEuropeLink`, which says that it opens a new tab.
Theme overrides of the notice templates should keep the link's `aria-label` and `aria-describedby` and the hidden text.


## API

### Transfer approval uses the active Approval Flow release

In Administration, publish and activate a transfer process under Approval → Process definitions.
New transfers require an active release before booking.
The experimental Workflow endpoints and finance approval preview and configuration endpoints have been removed.

### Withdraw and reapply transfer approvals

The transfer approval endpoints now accept withdrawal by the initiator while an Approval Flow transfer is pending.
Reapplying after withdrawal or rejection creates a linked transfer under the currently active release and preserves the original approval history.

### Installed extensions tell whether they can be removed

Extensions returned by `GET /api/_action/extension/installed` have a new `allowRemove` flag, also available as `ExtensionStruct::isAllowRemove()`.
It is `false` when Shopwell can't delete the extension's files, for example for extensions that Composer installed outside `custom/plugins`, and for extensions that are only available in the store.
Check it before calling `POST /api/_action/extension/remove/{type}/{technicalName}`.

### Checkout draft route

`GET /store-api/checkout/draft` returns the calculated cart together with the available shipping and payment methods and the stored address of a visitor without a customer.
`PATCH /store-api/checkout/draft` accepts every field of `PATCH /store-api/context`, applies the change through that route and returns the same response.
Unlike `PATCH /store-api/context`, unknown fields are rejected with HTTP 400.

The flags `methods`, `shippingCosts`, `checkoutGateway` and `taxed` control the optional, more expensive parts; `contextGateway` with `appName` runs an app Context Gateway on `PATCH`.
Changes of an address or a method are rate limited per context token, configurable as `shopwell.api.rate_limiter.checkout_draft`.

### Address of a visitor without a customer on `PATCH /store-api/context`

`PATCH /store-api/context` accepts `shippingAddress` and `billingAddress` for visitors who are not logged in.
Only `countryId` is required, the country of the shipping address also becomes the context country, and `null` removes the address.
Logged-in customers keep using `shippingAddressId` and `billingAddressId`; sending an address for them fails with HTTP 400 and code `SYSTEM__PROSPECTIVE_ADDRESS_WITH_CUSTOMER`.
A `shippingAddress` together with `countryId` or `countryStateId` fails with HTTP 400 and code `SYSTEM__PROSPECTIVE_ADDRESS_WITH_COUNTRY`.
A later `countryId` or `countryStateId` that differs from the stored shipping address removes the stored addresses.
`GET /store-api/context` returns the stored address as `prospectiveCustomer`.

Headless clients should note that `shippingLocation.address` and `cart.deliveries[].location.address` now contain an address for such a visitor, while `customer` is `null`.
Its ids stay the same for a context token, but it is not a stored customer address and can not be sent as `shippingAddressId`.
Check `customer` or `prospectiveCustomer` before treating it as a customer address.

### Rate limit responses contain `Retry-After`

Admin API and Store API responses with HTTP 429 now contain a `Retry-After` header with the seconds to wait.
It is listed in `Access-Control-Expose-Headers`, so browser clients on another origin can read it.

### MCP clients can log in with OAuth

MCP clients can now discover how to log in to `/api/_mcp` and get a token that only works there.
Access keys, integration tokens and all existing OAuth tokens keep working unchanged.

* **Discovery:** A `401` from `/api/_mcp` now carries `WWW-Authenticate: Bearer resource_metadata="..."`, with `error="invalid_token"` when credentials were sent.
  The protected resource metadata (RFC 9728) is served at `/.well-known/oauth-protected-resource/api/_mcp` and the authorization server metadata (RFC 8414) at `/.well-known/oauth-authorization-server/api`.
  The issuer is `<shop URL>/api`, and the authorization response now contains `iss` (RFC 9207).
  The URLs use the request host when it is an allowed MCP host, otherwise `APP_URL`.
  When the MCP URL has a query, e.g. `/api/_mcp?toolsets=orders`, the metadata URL and the `resource` of the metadata keep it unchanged, as RFC 9728 requires; the token audience ignores it.
* **Installations in a subdirectory:** The well-known URIs sit at the host root, e.g. `/.well-known/oauth-protected-resource/shop/api/_mcp`.
  Rewrite them in the web server to `/shop/.well-known/oauth-protected-resource/api/_mcp` and `/shop/.well-known/oauth-authorization-server/api`.
* **Audience-bound tokens:** `/api/oauth/authorize` and `/api/oauth/token` accept the `resource` parameter (RFC 8707).
  The only accepted value is the shop's MCP endpoint; any other value is answered with `invalid_target`.
  A token issued with `resource` is accepted on `/api/_mcp` only and gets `401` on every other Admin API route, including `/api/_info/system-health-check`.
  A refresh keeps the resource.
  The JWT keeps the client id as the first `aud` entry and lists the resource after it.
  Requests without `resource` behave as before.
* **OAuth error members:** Every OAuth error of the Admin API, e.g. from `/api/oauth/token`, now also carries `error`, `error_description` and, when known, `hint` at the top level of the body, as RFC 6749 (section 5.2) defines them.
  The `errors` array stays unchanged, so existing clients keep working.
* **Consent page:** The Administration consent page forwards `resource` and says that access is limited to the MCP endpoint.
  Rebuild the Administration when you build it yourself.
  A build from before this change drops `resource`, and the token request of the MCP client then fails with `invalid_target`.
* **Clients:** There is no dynamic client registration.
  To let an MCP client with a configurable client id log in, add it to `shopwell.api.oauth_clients`:

  ```yaml
  shopwell:
      api:
          oauth_clients:
              claude-code:
                  name: 'Claude Code'
                  redirect_uris:
                      - 'http://localhost:53682/callback'
  ```

  Redirect URIs on `http://127.0.0.1` and `http://[::1]` accept any port; all others, including `http://localhost:<port>`, must match exactly.
  Redirect URIs must now use `https`, or a private-use scheme of a native app such as `cursor://`.
  Plain `http` is only accepted for `localhost`, `127.0.0.1` and `[::1]`, or for every host when the kernel runs in the `dev` environment.
  An authorization request to another `http` redirect URI is rejected with `FRAMEWORK__OAUTH_INSECURE_REDIRECT_URI`, so change registered `http` redirect URIs on other hosts to `https`.
  Claude Code calls back on `http://localhost:<port>/callback`, so register a fixed port and add the server with `claude mcp add --transport http --client-id claude-code --callback-port 53682 shopwell <shop URL>/api/_mcp`.

### Order deliveries and order transactions have a `payload` field

Order deliveries and order transactions in the Admin API and Store API have a new `payload` field, and so do the deliveries and transactions of the Store API cart.
It is an object, or an empty array when nothing is set.

### Error responses no longer expose database connection errors

With `APP_DEBUG=0`, a failed database connection, for example invalid credentials or an unreachable database server, is no longer rendered with its raw exception message.
Requests that prefer HTML, like a storefront request while the database is not reachable, get Symfony's generic error page.
Other requests get a JSON error with `Internal Server Error` as detail.
The original exception is still logged.

### Reset a SEO URL to its template

`PATCH /api/_action/seo-url/canonical` accepts an empty or missing `seoPathInfo`, unless the request sets `isModified` to `true`.
It replaces the canonical SEO URL of that entity, sales channel and language with the URL generated from the SEO URL template, and removes its write protection (`isModified`).
Before, the request was rejected with `400`.
When the template generates no URL there, for example for an inactive sales channel or a language without a domain, the request is rejected with `400` and the error code `CONTENT__SEO_URL_RESET_NOT_POSSIBLE`, and the SEO URL stays unchanged.
In the Administration, clearing the SEO URL field of a product or category and saving does this.

### Media upload endpoints accept a title and an alt text

`POST /api/_action/media/upload`, `POST /api/_action/media/upload_by_url` and `POST /api/_action/media/external-link` accept the optional fields `title` and `alt`.
The new media is created with them, so a separate `PATCH /api/media/{id}` is no longer needed.

### Order deliveries have a `position` field

Order deliveries in the Admin API and Store API have a new integer `position` field with the order of the deliveries in the cart the order was placed from, starting at 1.
A delivery created without a `position` gets the next free position of its order, so a delivery added to a placed order is listed last.
Deliveries of orders placed before this release have position `0` until the order is recalculated.

### Store API media schema allows `null` where responses send it

The `Media` and `MediaThumbnail` schemas of the Store API now allow `null` for the fields that the responses already send as `null`: `alt`, `title`, `config`, `customFields` and `updatedAt`, and for media without an uploaded file also `mimeType`, `fileExtension`, `fileName`, `path`, `uploadedAt`, `fileSize` and `metaData`.
`MediaThumbnail` also allows `null` for `mediaThumbnailSizeId` and `path`.
The responses are unchanged.
If you generate types from the schema, regenerate them and handle `null` for these fields.

### Webhooks created through the Admin API can be signed

Webhooks created through the Admin API accept an optional, write-only `secret`.
Deliveries are then signed in the `shopwell-shop-signature` header, like app webhooks.
Deliveries still queued for a deleted webhook without an app are no longer sent.

Webhooks created by an app's integration are now signed with the app secret, like the app's manifest webhooks,
so they can't have their own `secret`.

```http
PATCH /api/webhook/{id}
{ "secret": "430eb48125a7e51ccf48371cc4a61fd2eeb6a888ec959a94854380c68d46a0ce" }
```

### Product documents in the API

The new `GET|POST /store-api/product/{productId}/documents` route returns the documents of a product, each with its file in the requested language, or in the default language if it has none.
CMS product pages return the same documents in the `documents` data of the description and reviews element (`product-description-reviews`).

### Legal guarantee notice text in the Store API

`GET /store-api/legal-guarantee-notice` also returns `text`, the notice as HTML in the language of `svg`.
The SVG is now `aria-hidden`, so headless frontends should render `text` as its text alternative.


## App System

### App MCP prompt and resource responses

App MCP prompt responses are now decoded as JSON arrays of messages with a `user` or `assistant` role and `content`, for example `[{"role":"user","content":"Hello"}]`.
App MCP resource responses must be JSON objects containing a `text` string or a base64-encoded `blob` string, for example `{"uri":"my-app://status","mimeType":"application/json","text":"{\"ok\":true}"}`.
Plain-text resource responses are no longer accepted; wrap the content in a JSON object.
Resources use the URI declared in `mcp.xml`; a non-empty `mimeType` in the response takes precedence over the configured MIME type.
Invalid JSON, malformed messages or resource contents, and `{"success":false,"error":"..."}` responses produce MCP errors with details for the client.

### Deprecated the old shop ID format

The system config key `core.app.shopId` is deprecated, read the shop ID from `core.app.shopIdV2` instead.

### App manifests can contain sections from newer Shopwell versions

A top-level manifest section the shop's Shopwell version doesn't know is now skipped and reported as a notice, instead of failing the install.
Apps that need such a section should require that version in `<compatibility>`.


## Critical Fixes

### A failed theme save keeps the saved theme config

Saving a theme in the Administration (`PATCH /api/_action/theme/{themeId}?reset=1`) now resets the theme config and writes the new values in one transaction.
Before, a failure between the two writes left the theme without any of its saved settings.
The theme is compiled after the commit.
For that, `ThemeService::updateTheme()` skips the compile when the context has the new state `ThemeService::STATE_SKIP_COMPILE`.
Classes overriding `updateTheme()` without calling the parent method should handle that state.

### Shopwell can be installed with Composer again

Twig 3.31.1 fixes security issues in the Twig sandbox, and Composer now refuses to install older Twig versions. Together with the Twig version cap in `shopwell/conflicts` 0.6.3, no installable Twig version was left, so installing or updating Shopwell 6.7.15.0 to 6.7.16.1 with Composer failed.

Shopwell now requires `shopwell/conflicts` 0.6.4 and `twig/twig` ^3.31.1, so it can be installed and updated again.


## Features

### MCP tools declare whether they change shop data

The core MCP tools on `/api/_mcp` and `/store-api/_mcp` now send the tool annotations of the MCP specification in `tools/list` and in `shopwell-tool-search` results.
Read tools such as `shopwell-entity-search` are marked with `readOnlyHint`, and tools that change or remove data, such as `shopwell-entity-upsert`, `shopwell-entity-delete`, `shopwell-order-state`, `shopwell-system-config-write`, `shopwell-theme-config` and `shopwell-media-upload`, with `destructiveHint`.
MCP clients use these hints to decide when to ask the user before a call: clients that honour them may now run the read tools without asking, and ask before the destructive ones where they did not before.
Plugin and bundle tools can declare the same hints with the `annotations` argument of `#[McpTool]`.

### Open a product in the Storefront from the Administration

The product detail page has a new "Open in Storefront" action in the context menu of its smart bar.
It opens a modal to select one of the Storefront sales channels the product is assigned to.
For the selected sales channel it shows the product URLs, or the reason why the sales channel cannot show the product, for example an inactive sales channel or product, a missing domain, or a sold-out clearance sale product.
Sales channels in maintenance mode get a hint.
If the IP address of the Administration user is not on the maintenance allowlist, the modal explains that it cannot load the product URLs.
The `product.viewer` role now includes the `sales_channel_domain:read` privilege, which the modal needs to load the domains; a migration adds it to existing roles.

## Hosting & Configuration

### Password recovery is rate limited per IP

Customer and admin password recovery requests are now also limited per client IP (`reset_password_client`, `user_recovery_client`), so setups behind a proxy or a headless frontend must configure trusted proxies to keep the real client IP

### Translations can be downloaded as one archive

`bin/console translation:download` and `bin/console translation:install --all` download all locales as one archive when `shopwell.translation.archive_manifest_url` is set.
The translations repository publishes the manifest of its latest archive at `https://github.com/shopwell-shop/translations/releases/latest/download/manifest.json`.

```yaml
shopwell:
    translation:
        archive_manifest_url: 'https://github.com/shopwell-shop/translations/releases/latest/download/manifest.json'
```

When the archive cannot be used, both commands download every file of each locale as before.
`translation:install --all` also downloads every file when the archive is older than the translation metadata.
Decorators of `AbstractTranslationLoader::download()` are not called for the locales that come from the archive.

### Elasticsearch searches use at most 10 words of a search term

Searches over Elasticsearch or OpenSearch now use only the first 10 distinct words of a search term, because longer terms made OpenSearch reject the query with `too_many_nested_clauses`.
Punctuation and one-letter words do not count.
Change the limit with `elasticsearch.search.term_max_words`:

```yaml
elasticsearch:
    search:
        term_max_words: 10
```

Raise it only when your cluster allows more clauses through `indices.query.bool.max_clause_count`, and lower it when searches with fewer words still fail.

### Admin search reindexes products after queued inheritance updates

With `ENABLE_OPENSEARCH_FOR_ADMIN_API`, the admin search index also reindexes the products whose inherited data the product indexer updates in the message queue.
This covers the variants of product families with 50 or more products.
A full `bin/console dal:refresh:index` leaves the products to the admin search index rebuild that runs after it.
`ProductIndexerEvent::$isFullIndexing` tells whether the event comes from such a full indexing run.
