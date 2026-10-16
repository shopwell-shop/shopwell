# MCP Tools

## Purpose
Each file in this directory is a single MCP tool -- an action that AI clients can invoke via the MCP protocol.

## Naming
- Tool names use `shopwell-` prefix with kebab-case: `shopwell-entity-search`, `shopwell-entity-upsert`
- Class names use PascalCase suffix `Tool`: `EntitySearchTool`, `EntityUpsertTool`
- Plugin tools: `{plugin-name}-{tool-name}`
- App tools: `{app-name}-{tool-name}`
- Names must only contain `a-zA-Z0-9_-` (no dots) for consistency across all MCP capability types

## Patterns
- Use constructor injection for dependencies
- Place the `#[McpTool]` attribute on the class with `name` and `description`
- Extend `McpToolResponse` and return via `$this->success()` or `$this->error()` from `__invoke`
- Use `McpContextProvider` to get the authenticated `Context`
- Write operations must accept a `bool $dryRun = true` parameter. The `executeWithDryRun` helper adds `SKIP_TRIGGER_FLOW` to the context to prevent Flow Builder actions from firing during preview, and rolls back the transaction afterward
- Entity tools must validate entity existence with `$this->registry->has($entity)` before ACL checks to provide clear "entity not found" messages
- Entity tools that accept criteria JSON must validate the built `Criteria` with `AclCriteriaValidator` before DAL access — the top-level `requirePrivilege()` check does not cover association reads (see `EntityReadTool`/`EntitySearchTool`/`EntityAggregateTool`)
- Entity tools that return DAL data must inject `JsonEntityEncoder` and use it instead of `jsonSerialize()` to respect `includes`/`excludes`
- Entity tools returning DAL data should use the `McpEntityIncludes` trait and call `applyDefaultIncludes()` to keep responses compact (see below)

## Description quality (LLM routing)

The `description` argument on `#[McpTool]` is what the agent reads to pick a tool. It is a **routing surface**, not a docblock. Lessons captured from the GPT-4o eval suite:

- **Lead with the user's trigger phrases.** A description that opens with "The correct tool for count, sum, average, and other aggregate questions" routes correctly when the user asks "how many products?". A description that opens with "Run aggregations over any Shopwell entity" does not.
- **Use negative phrasing to break ties.** When two tools share keywords, the description must spell out the contrast: `"Use this — NOT shopwell-entity-search — for any 'how many'…"` is more decisive than a positive description alone.
- **Do not reference other tools as prerequisites unless they truly are.** Phrases like "Use shopwell-foo-read to check current values first" train the agent to call the read tool even when the user explicitly asked to write. Declare prerequisites with `#[McpToolDependsOn]`, not in prose.
- **Mention the use cases the user will name.** If a prompt is "upload this image as a product cover", the description should contain the phrase "product cover" and clarify that no extra parameter is needed for that case. Otherwise the agent often returns no tool selection at all.
- **Make required parameters truly required.** A parameter without a PHP default ends up `required: true` in the JSON schema. If users frequently won't supply it (a sales channel UUID, a tax ID), give it a default of `''` or `null` and validate inside `__invoke()`. GPT-4o refuses to call tools when required parameters are missing from the prompt — even when the description says they are optional.

### Parameter descriptions

Parameter contracts belong in `#[Schema(description: ...)]` (`Mcp\Capability\Attribute\Schema`) on the `__invoke()` parameter, not in docblock `@param` tags. The SDK renders them into the tool's public `inputSchema`, so they are part of the routing surface the agent reads when it fills in arguments. Do not add `@param` blocks that only restate the native type; keep docblocks for what static analysis needs (array shapes). The same `cache:clear` caveat as for tool descriptions applies.

### Cache after description changes

Tool descriptions are read at container compile time and baked into the cached DI container. Editing a `#[McpTool(description: ...)]` attribute does **not** take effect until you run `bin/console cache:clear`. If `bin/console debug:mcp` or `tools/list` shows the old text, clear the cache first before debugging further.

## Tool dependencies (`#[McpToolDependsOn]`)

When one tool only makes sense if the AI has already used another tool first, declare that relationship with `#[McpToolDependsOn]`:

```php
use Shopwell\Core\Framework\Mcp\Attribute\McpToolDependsOn;

#[McpTool(name: 'shopwell-entity-delete', description: '...')]
#[McpToolDependsOn('shopwell-entity-search')]
class EntityDeleteTool extends McpToolResponse { ... }
```

- The attribute is **repeatable** — add multiple `#[McpToolDependsOn]` lines if the tool depends on several others.
- Dependencies are **tool-only** — tools can only depend on other tools, not on prompts or resources.
- `McpToolAnalysisCompilerPass` resolves dependencies transitively and stores the result in the `shopwell.mcp.tool_dependencies` container parameter, which the allowlist provider uses at runtime.
- **Allowlist auto-expansion:** when a user enables a tool in the Admin integration UI, all its declared dependencies (and their transitive dependencies) are automatically added to the allowlist. Removing a tool does **not** auto-remove its dependencies — they may be intentionally enabled independently.
- `bin/console debug:mcp` shows the resolved dependencies in a **Dependencies** column.

### Current dependency graph (core + merchant plugin)

| Tool | Depends on |
|---|---|
| `shopwell-entity-read` | `shopwell-entity-schema` |
| `shopwell-entity-search` | `shopwell-entity-schema` |
| `shopwell-entity-aggregate` | `shopwell-entity-schema` |
| `shopwell-entity-upsert` | `shopwell-entity-schema` |
| `shopwell-entity-delete` | `shopwell-entity-search` (→ `shopwell-entity-schema`) |
| `shopwell-system-config-write` | `shopwell-system-config-read` |
| `merchant-cart-checkout` | `merchant-cart-manage` |

**Rule:** only add `#[McpToolDependsOn]` when the dependency is genuinely required to use the tool — not just convenient. Unnecessary dependencies inflate every integration's allowlist.

## Declaring required privileges (`#[McpToolRequires]`)

When a tool requires specific ACL privileges to run, annotate it with `#[McpToolRequires]`. This is **declarative only** — it does NOT add a new enforcement layer. Runtime enforcement still depends on `requirePrivilege()` calls inside `__invoke()` and the DAL's own ACL checks.

The attribute serves as operator documentation: `debug:mcp` shows it in the **Privileges** column, the `/_action/mcp/capabilities` API exposes it in `requiredPrivileges`, and the Admin UI uses it to warn when an integration's assigned role is missing privileges.

Two forms:

**Static privilege** (known at compile time):
```php
use Shopwell\Core\Framework\Mcp\Attribute\McpToolRequires;

#[McpTool(name: 'shopwell-system-config-read', description: '...')]
#[McpToolRequires('system_config:read')]
class SystemConfigReadTool extends McpToolResponse { ... }
```

**Dynamic privilege** (entity name comes from a runtime parameter):
```php
#[McpTool(name: 'shopwell-entity-read', description: '...')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
class EntityReadTool extends McpToolResponse { ... }
```

- The attribute is **repeatable** — add multiple `#[McpToolRequires]` lines when a tool needs more than one privilege.
- Use `entityParam` + `operations` for entity tools where the privilege depends on the `$entity` parameter passed at runtime. The Admin UI renders this as `<entity>:read` with a tooltip.
- Still call `$this->requirePrivilege($context, 'system_config:read')` (or the equivalent) inside `__invoke()` for actual runtime enforcement.

### Current privilege declarations (core tools)

| Tool | Required privileges |
|---|---|
| `shopwell-system-config-read` | `system_config:read` |
| `shopwell-system-config-write` | `system_config:update` |
| `shopwell-order-state` | `order:read`, `order:update`, `order_transaction:update`, `order_delivery:update` |
| `shopwell-media-upload` | `media:create`, `product:update` |
| `shopwell-entity-read` | `<entity>:read` (dynamic) |
| `shopwell-entity-search` | `<entity>:read` (dynamic) |
| `shopwell-entity-aggregate` | `<entity>:read` (dynamic) |
| `shopwell-entity-upsert` | `<entity>:create`, `<entity>:update` (dynamic) |
| `shopwell-entity-delete` | `<entity>:delete` (dynamic) |
| `shopwell-theme-config` (Storefront) | `theme:read`, `theme:update` |

## Response format convention
All tools must extend the `McpToolResponse` abstract class. It provides two helpers:

**Success**: `$this->success(array $data, array $meta = [])`
```json
{"success": true, "data": [...], "_meta": {"total": 42, "page": 1}}
```

**Error**: `$this->error(string $message, string $code = McpToolError::TOOL_ERROR)`
```json
{"success": false, "error": "Human-readable message", "code": "tool_error"}
```

Rules:
- `success` (bool) is always present at root
- `data` holds the actual result (structure is tool-specific)
- `_meta` is optional, used for pagination (`total`, `page`, `limit`), context (`salesChannelId`), and write metadata (`dryRun`)
- `error` (string) and `code` only appear when `success` is false. Use a `McpToolError` constant for `code` (`missing_privilege`, `invalid_arguments`, `not_found`, `tool_error`), so clients can match on it instead of the message
- Responses up to 100 KB are returned inline. Responses at or above 20 KB include `_meta.responseSize` so the LLM can see the cost of the current call and learn to use tighter `includes`/`limit` next time
- Responses larger than 100 KB are stored in `mcp_tool_result_cache` and returned as a `shopwell://tool-result/{pointer}` URI in `_meta.resourceUri`, with `_meta.expiresAt`. The renderer adds the same URI as a `resource_link` content block (protocol 2025-06-18 and later). The pointer is signed for the caller (Admin: integration and user; Store: sales channel and `sw-context-token`) and expires after an hour, so it works on a later request and without an MCP session, but only for the same caller. The full content is fetched via `resources/read` (`ToolResultResource` on the Admin API, `StoreApiToolResultResource` on the Store API). `_meta.query` echoes the originating tool name and arguments so the LLM can disambiguate which call produced which URI. Rows are wiped on session DELETE by `McpSessionCleanupSubscriber` when the call had a session. This applies to every tool extending `McpToolResponse`, including plugin and bundle tools (`McpToolResultCacheCompilerPass` wires the cache for them). `McpToolResultCacheCleanupTask` removes rows older than `shopwell.mcp.tool_result_cache_ttl`. Without an authenticated caller (CLI/test), the response falls back to inline delivery
- A PHPStan rule (`McpToolResponseRule`) enforces that all `#[McpTool]` classes extend the abstract class

### What the client receives

The envelope above is the text block. `McpToolResultReferenceHandler` wraps the SDK reference handler of both servers (`McpToolResultRendererCompilerPass`), parses the envelope into a `McpToolResult` and renders it with `McpToolResultRenderer` into a `CallToolResult`:
- `structuredContent` holds `data`. On the handshake era it must be an object, so a list or scalar is wrapped as `{"result": ...}`. A failure becomes `{"error": {"code": ..., "message": ..., "details": ...}}` and sets `isError: true`. Clients that negotiated a protocol revision before 2025-06-18 get no `structuredContent`, because it doesn't exist there; they read the text block
- `structuredContent` and the text copy are both sent, as the spec asks. Size is limited only by the 100 KB offload in `McpToolResponse::success()`. Moving that decision into the renderer is phase 2 of the result format ADR
- `_meta["shopwell/generatedAt"]` is always set
- With `v6.8.0.0` active, the envelope is replaced by separate text blocks: the data as plain JSON, then the summary, then `{"_meta": …}`. A failure sends its message, then `{"details": …}` and `{"_meta": …}` when there are any. The metadata also goes into the result `_meta` with a `shopwell/` prefix. Keys next to the envelope fields, such as `dryRun`, count as metadata

A tool may also return a `McpToolResult` directly; it is rendered the same way. Until 6.8.0, the text block of such a result is a rebuilt envelope, which carries the data, the metadata and the error message and code, but not a summary or error details. Tools that return a `CallToolResult` themselves bypass the renderer.

## Pagination with shopwell-entity-search

`EntitySearchTool` exposes `limit` (default 25) and `page` (default 1) as top-level parameters alongside the criteria JSON. Every response includes a `_meta` block:

```json
{ "success": true, "data": [...], "_meta": { "total": 1482, "page": 1, "limit": 25 } }
```

To iterate through all results, increment `page` until `page * limit >= total`:

- Page 1: `page=1` → records 1–25
- Page 2: `page=2` → records 26–50
- Total pages: `ceil(_meta.total / _meta.limit)`

You can also set `limit` inside the criteria JSON string directly — the parameter only applies when the criteria does not already contain a `limit` key (`??=`).

**Count mode:** The tool defaults to `total-count-mode: exact`, which runs a separate `COUNT(*)` query so `_meta.total` always reflects the real dataset size. You can override this in the criteria JSON to `next-pages` (faster — fetches `limit * 6 + 1` rows to detect if a next page exists, but total is not meaningful) or `none` (no count query at all — fastest, but `_meta.total` only reflects the current page size).

## Search vs. aggregate: why they are separate tools

`EntitySearchTool` and `EntityAggregateTool` look similar but serve different purposes and have different output sizes:

| | `shopwell-entity-search` | `shopwell-entity-aggregate` |
|---|---|---|
| Returns | Entity records | Aggregation results only |
| Entity rows | Up to `limit` (default 25) | Always 0 (`limit: 0` internally) |
| Aggregations in response | Never | Always |
| Response risk | Large on wide entities | Bounded — no row data |
| Typical use | "Show me the last 10 orders" | "How many opt-in newsletter recipients?" |

**Why not combine them?** Entity rows can already fill most of the inline 100 KB budget for typical searches. Bucket aggregations (like `terms` on product names or `date-histogram` by day) can produce hundreds or thousands of result entries on their own. Mixing both in one response means a single oversized result is much more likely to spill out of the inline budget into the resource cache, hiding the actual data behind a `resourceUri` round-trip. Separate tools remove the ambiguity: `entity-search` is always bounded by `limit`, `entity-aggregate` is always bounded by the aggregation definitions.

**Rule:** Never add aggregation output to `EntitySearchTool`. If you need both records and metrics for the same entity, make two sequential tool calls.

## Smart default includes (McpEntityIncludes trait)

Entity tools (`EntitySearchTool`, `EntityReadTool`, and the plugin's `StorefrontSearchTool`) auto-apply `includes` when the caller hasn't specified them. This dramatically reduces response size by only serializing the fields AI clients actually need.

**How it works:**
1. The tool introspects the `EntityDefinition` to find all scalar fields (id, name, price, etc.)
2. Only associations explicitly requested in the criteria are included
3. Unrequested auto-loaded associations (thumbnails, extensions, translated duplicates) are stripped
4. The `translated` pseudo-field is always injected for entities with `TranslatedField` instances, ensuring inherited/resolved values (e.g. variant product names) are never lost
5. The caller can always override by passing their own `includes` in the criteria -- `translated` is still auto-injected

**Usage in tools:**
```php
use McpEntityIncludes;

// After building the criteria, before searching -- single call handles everything
$this->applyDefaultIncludes($definition, $criteriaObj);
```

`applyDefaultIncludes()` handles two cases:
- **No includes provided**: builds smart defaults from the definition (scalar fields + requested associations + `translated`)
- **User-provided includes**: injects `translated` into the includes for any entity with translated fields, recursing into loaded associations

All entity read tools use `JsonEntityEncoder` for serialization (not the Store API serializer), so `includes`/`excludes` filtering works consistently regardless of whether the data comes from a regular or sales channel repository.

## Read tools
- `EntitySchemaTool` (`shopwell-entity-schema`) -- entity field/association introspection
- `EntitySearchTool` (`shopwell-entity-search`) -- criteria-based search; returns records only, **never aggregations** (see below)
- `EntityAggregateTool` (`shopwell-entity-aggregate`) -- aggregation-only queries (`limit: 0` internally, no entity rows in response)
- `EntityReadTool` (`shopwell-entity-read`) -- single entity read by ID
- `SystemConfigReadTool` (`shopwell-system-config-read`) -- read shop configuration

## Write tools
- `EntityUpsertTool` (`shopwell-entity-upsert`) -- create/update entities (dryRun wraps in transaction + rollback)
- `EntityDeleteTool` (`shopwell-entity-delete`) -- delete entities (dryRun shows cascade impact). Mapping entities with a composite primary key (`product_category`, `product_property`, ...) take objects naming every key field; this is how a many-to-many link is removed, because `shopwell-entity-upsert` can only add links
- `SystemConfigWriteTool` (`shopwell-system-config-write`) -- update configuration values
- `OrderStateTool` (`shopwell-order-state`) -- change the state of an order, its transactions, and/or deliveries in one call
- `MediaUploadTool` (`shopwell-media-upload`) -- upload media from URL, optionally assign to product as cover image

### Removing many-to-many links

A many-to-many link (a product's categories, properties, tags, ...) is a row of a mapping entity such as `product_category`. MCP follows the Admin API sync endpoint here: `shopwell-entity-upsert` adds links, and `shopwell-entity-delete` on the mapping entity with composite key objects removes them (`[{"productId": "...", "categoryId": "..."}]`). `shopwell-entity-schema` names the `mappingEntity` of every many-to-many association so the model can find it.

Version fields of the composite key (`productVersionId`, `categoryVersionId`) are optional in the key objects and default to the live version. In a versioned context (a non-live `sw-version-id`) the tool refuses to guess and asks for them: the Admin API unlinks with the context version for the parent and the live version for the referenced side, a mapping row does not say which side is which, and a wrong guess matches no row and removes nothing without an error.

We deliberately don't add a dedicated "unlink" tool for now. The planned Sync tool (#20520) removes links the same way, so one model covers delete, sync and the Admin API. A new tool would also stay invisible to existing integrations until operators add it to their allowlist. Revisit this if evaluations show that models don't find the delete path, and build such a tool on the same key handling.

Don't add a "remove" flag to upsert payloads: the DAL writes every field sent on an associated record to that record.

## Merchant workflow tools (plugin)
Higher-level workflow tools for merchant operations live in the `SwagMcpMerchantAssistant` plugin (`custom/plugins/SwagMcpMerchantAssistant`), not in core. This separation keeps core tools focused on platform primitives while allowing merchant-specific tools to evolve independently.

Plugin tools are registered via `shopwell.mcp.tool` DI tag and use the `merchant-*` name prefix.

## Error handling for extension developers
Tools extending `McpToolResponse` benefit from built-in error handling:
- `executeWithDryRun()` catches any `\Throwable` and returns it as a structured `$this->error()` response
- Unhandled exceptions from `__invoke()` produce a generic MCP error (`-32603`). Prefer catching known exceptions and returning `$this->error($message)` instead.
- Write tools should validate inputs before the operation (e.g., `SystemConfigWriteTool` rejects null values, entity tools validate entity existence)
- `invalidCriteriaError()` renders a `RequestCriteriaBuilder::fromArray()` failure as an actionable message. Wrap **only** the `fromArray()` call and catch `ShopwellHttpException`: the builder reports bad payloads through several unrelated classes (`SearchRequestException` with one pointer per rejected element, `DataAbstractionLayerException` directly, `FrameworkException::associationNotFound()`, and `ApiProtectionException` / `RuntimeFieldInCriteriaException` from `ApiCriteriaValidator`). Keep the following read/search/aggregate call outside the `try`, so a real DAL failure still reaches the log instead of being reported as bad input.

## Adding a new tool
1. Create a class in this directory
2. Add `#[McpTool(name: 'shopwell-{tool-name}', description: '...')]` on the class
3. If the tool only works after using another tool first, add `#[McpToolDependsOn('other-tool-name')]` (repeatable)
4. If the tool requires specific ACL privileges, add `#[McpToolRequires('privilege:operation')]` (repeatable); for entity tools use `#[McpToolRequires(entityParam: 'entity', operations: ['read'])]`. Still call `$this->requirePrivilege()` inside `__invoke()` for actual runtime enforcement.
5. Extend `McpToolResponse` and return via `$this->success()` / `$this->error()`
6. For entity tools: validate with `$this->registry->has($entity)` before ACL checks
7. Register in `src/Core/Framework/DependencyInjection/mcp.php` with the `mcp.tool` tag
8. Add unit test in `tests/unit/Core/Framework/Mcp/Tool/`
9. Add the tool name to `expectedTools()` in `McpCapabilityDiscoveryTest` (see below)

## Validating that a tool is actually reachable

How registration works differs between core tools and plugin tools:

| Tool location | Registration mechanism | What can go wrong |
|---|---|---|
| Core (`src/Core/Framework/Mcp/Tool/`) | `mcp.tool` DI tag; the namespace is covered by the Admin API server's `registry` prefixes in `packages/mcp.php` | Missing tag; a class moved outside the configured namespaces is assigned to no server |
| Plugin (`shopwell.mcp.tool` DI tag) | `McpToolDiscoveryCompilerPass` re-tags it and assigns it to the Admin API server | Missing tag; wrong tag name; attribute on method instead of class |

**For core tools**, `McpCapabilityDiscoveryTest` (`tests/integration/Core/Framework/Mcp/McpCapabilityDiscoveryTest.php`) is the authoritative check. It boots the full kernel, calls the live `/api/_mcp` endpoint, and asserts every expected capability name is present. Add new core tool names to its `expectedTools()` list.

**For plugin tools**, `McpToolDiscoveryCompilerPass` handles registration automatically from the DI tag. A missing `#[McpTool]` attribute (or attribute placed on `__invoke()` instead of the class) means the tool name cannot be extracted and the tool is silently skipped.

Quick manual check: `bin/console debug:mcp` uses the same registry as the HTTP endpoint and shows core tools, plugin tools, and app tools in one view.
