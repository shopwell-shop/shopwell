---
title: MCP server placement and extensibility
date: 2026-03-17
area: framework
tags: [framework, mcp, ai, extensibility, plugin, app]
---

## Context

Shopwell now contains an experimental MCP server foundation that exposes a native `/api/_mcp` endpoint and integrates with Shopwell authentication, ACL, rate limiting, feature flags, and capability discovery.

The current implementation already supports three different capability sources:

- core-provided MCP capabilities
- in-process extensions from bundles and plugins
- out-of-process app capabilities executed through HMAC-signed webhooks

This raises an architectural question for the current PoC and its future productization:

- Which parts should live in core?
- Which parts should live in a plugin or bundle?
- Which parts should remain external?
- How do we preserve extensibility so plugins and apps can continue to extend the system?

This ADR affects several technical domains:

### Framework and API

The MCP endpoint, transport, authentication bridge, rate limiting, capability discovery, and feature flagging are framework concerns. They should behave consistently for every installation that enables MCP. The current implementation uses `symfony/mcp-bundle` with Streamable HTTP transport at `/api/_mcp`.

### Core business primitives

Generic data access and reference capabilities are platform primitives: reusable building blocks on top of the DAL and Admin API permission system, not opinionated assistant workflows.

### Merchant-facing assistant workflows

Many tools in the current PoC are not low-level primitives. They flatten business workflows into AI-friendly operations: creating products from human-readable parameters, summarizing orders, running reports, managing carts and themes.

These tools are valuable, but they are more opinionated, more likely to change, and more likely to encode business assumptions.

### Storefront and optional bundles

Some capabilities depend on bundle-specific services.
`ThemeConfigTool` lives in `src/Storefront/Mcp/Tool/` for this reason.
Classifying a capability as an assistant workflow is a separate concern from its dependency direction.

### Extensibility

The MCP subsystem must remain extendable.
Plugins, bundles, and apps need to be able to contribute custom tools, prompts, and resources.
Registration and server assignment follow the [container registration ADR](2026-08-31-mcp-capability-registration-via-container.md):

- plugins use the `shopwell.mcp.tool` DI tag, remapped by `McpToolDiscoveryCompilerPass`
- third-party bundles use the `shopwell.mcp.*` tags, and in-tree bundles use the matching `mcp.*` tags
- apps declare capabilities in `Resources/mcp.xml`, loaded at runtime by `AppMcpToolLoader`/`AppMcpPromptLoader`/`AppMcpResourceLoader`

### Security and maintenance

The more tools are moved into core, the more security review, documentation, support, and backward compatibility commitments the platform team takes on. The `allowed_tools` configuration already provides an operational escape hatch to restrict which tools are exposed per installation.

## Decision

We adopt a hybrid model.

### Placement model

- keep the MCP platform foundation in core
- keep most opinionated, workflow-oriented, and fast-changing tools in a plugin or bundle
- use external services only where cross-system orchestration or runtime isolation is the primary concern

In short:

- core = MCP platform primitives
- plugin or bundle = merchant-facing assistant capabilities
- external service = orchestration and isolated integrations

### Core responsibilities

Core owns the MCP platform foundation:

- MCP HTTP endpoint at `/api/_mcp` using Streamable HTTP transport via `symfony/mcp-bundle`
- authentication bridge into the Admin API permission model (`McpContextProvider`)
- ACL enforcement integration
- rate limiting and audit logging (Monolog `mcp` channel)
- feature flagging (`MCP_SERVER`)
- compile-time capability registration and server assignment through the container
- capability registration through `#[McpTool]`, `#[McpPrompt]`, `#[McpResource]` attributes
- `McpToolDiscoveryCompilerPass`, `McpToolAnalysisCompilerPass`, and `McpServerBuilderCompilerPass` for tag mapping, conflict detection, dependency validation, privilege extraction, and builder registration
- `allowed_tools` operational allowlist to restrict which tools are exposed per installation
- generic reference resources
- the `shopwell-context` system prompt
- a small set of generic and stable tool primitives

Core may also own a limited set of low-level, broadly useful tools as long as they behave like platform primitives and not like assistant-specific workflows.

### Plugin or bundle responsibilities

Plugins and bundles should own the merchant-assistant layer:

- opinionated workflow tools
- convenience tools with human-readable parameters
- multi-step business operations
- bundle-specific capabilities
- storefront-specific assistant flows
- analytics and reporting helpers
- content, media, and appearance helpers

These capabilities are useful, but they should not automatically become core platform contracts while the feature is still experimental.

### External responsibilities

External services are appropriate when:

- the main use case spans multiple systems such as ERP, PIM, CRM, or BI
- runtime isolation is more important than deep in-process integration
- the workflow is long-running or operationally heavy
- the capability should scale or deploy independently from Shopwell

## Tool placement guidance

Generic DAL capabilities such as entity schema, search, read, aggregate, upsert, and delete belong in core.
Write-capable primitives require particular care around authentication, privileges, and operational allowlists.

Opinionated tools for reporting, product creation, cart orchestration, and merchant workflows belong in plugins or bundles.
Capabilities depending on an optional bundle belong with that bundle, as with `ThemeConfigTool` in Storefront.

## Extensibility

Extensibility is a hard requirement. The core MCP foundation must act as a capability host, not a closed feature set.

### Plugin and bundle extensibility

Plugins and bundles register custom tools, prompts, and resources through DI and use normal Shopwell services. This is the preferred model for domain-specific capabilities, project-specific workflows, vertical features, and capabilities depending on optional bundles.

The current implementation already supports this:

- plugins and third-party bundles tag services with `shopwell.mcp.*`, remapped by `McpToolDiscoveryCompilerPass`
- in-tree bundle capabilities carry the matching `mcp.*` tag and are assigned to a server through its configured namespace prefixes

### App extensibility

Apps declare capabilities in `Resources/mcp.xml` and receive HMAC-signed HTTP calls at runtime via `AppMcpCapabilityExecutor`.
This is the preferred model for SaaS integrations, remote business logic, and partner extensions that should not run inside the PHP runtime.

### What core must provide for this to work

- naming rules (`shopwell-*` for core, `{plugin}-*` for plugins, `{app}-*` for apps)
- `McpToolDiscoveryCompilerPass` for tag mapping, conflict detection, and allowlist enforcement
- `allowed_tools` for operational restriction per installation
- stable registration and discovery contracts
- developer-facing extension documentation

## Decision matrix

| Question | Core | Plugin or bundle | External service |
|---|---|---|---|
| Needed for every Shopwell MCP installation? | Best fit | Maybe | Rarely |
| Deep access to internal services and DAL needed? | Best fit | Best fit | Weak fit |
| Fast iteration expected? | Weak fit | Good fit | Best fit |
| Strong isolation needed? | Weak fit | Medium | Best fit |
| Independent release cadence needed? | No | Yes | Yes |
| Tied to optional bundle or domain? | Weak fit | Best fit | Good fit |
| Cross-system orchestration is the main value? | Weak fit | Medium | Best fit |
| Lowest core maintenance burden? | Worst fit | Better fit | Best fit |
| Lowest latency and fewest moving parts? | Best fit | Best fit | Weak fit |
| Stable official platform contract desired? | Best fit | Medium | Medium |

## Public API and extension model

The platform layer that should be treated as public API:

- the `/api/_mcp` endpoint and `symfony/mcp-bundle` transport
- container registration and server assignment through DI tags and `McpToolDiscoveryCompilerPass`
- the capability contracts (`#[McpTool]`, `#[McpPrompt]`, `#[McpResource]` attributes and response conventions)
- the security model (authentication, ACL, `allowed_tools`, rate limiting, app HMAC)
- the extension mechanisms for plugins, bundles, and apps

The built-in assistant tools should not automatically be treated as equally stable platform APIs. Most of them are product features built on top of the platform layer.

## Consequences

### Positive consequences

- core remains small, stable, and supportable
- Shopwell keeps a native MCP platform instead of outsourcing the whole feature
- plugins and bundles can iterate faster than core
- apps remain a valid extension mechanism
- mature tools can be promoted from plugin to core later
- bundle-specific capabilities can stay close to their owning domain

### Negative consequences

- the feature is intentionally split between foundation and capabilities
- some tools will remain debatable, especially generic write tools
- packaging and ownership must be communicated clearly
- migration of tools between plugin and core may happen over time

## Why this decision was made

The current PoC contains two different kinds of capabilities:

- platform primitives
- assistant workflows

Treating both kinds as if they belong in core would overcommit the platform too early. Treating both kinds as if they belong outside core would throw away the value of Shopwell-native auth, ACL, runtime integration, and discovery.

The hybrid model preserves the strengths of the existing implementation and avoids prematurely turning the full PoC into a long-term core maintenance contract.
