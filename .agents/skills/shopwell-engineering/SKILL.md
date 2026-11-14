---
name: shopwell-engineering
description: >
  Use when designing, implementing, refactoring, or reviewing Shopwell features or
  plugins across backend PHP, Administration TypeScript/Vue, Storefront Twig/JS,
  styling, persistence, APIs, and extension contracts, especially when deciding
  ownership, naming, module structure, readability, or which existing pattern to reuse.
---

# Shopwell engineering

Use this repository's domain language and supported extension mechanisms to produce code another maintainer can follow.
Shopwell descends from Shopware, but this checkout's contracts, dependencies and current guidance govern implementation.
This skill supplies source-backed design judgment; the existing domain skills retain their detailed coding and verification rules.

## Establish the implementation context

Read root and applicable nested `AGENTS.md`, then select the relevant reference below.
Read the referenced implementation and its tests before copying its pattern.
Distinguish current rules, supported public contracts, experimental features and historical implementation.
An existing file is evidence of behavior, not automatic endorsement of every line.
When sources conflict, check applicable instructions and the target version/feature, use a supported nonconflicting path where possible, and make any unresolved constraint explicit before relying on the disputed path.

| Work | Read |
| --- | --- |
| Domain ownership, DAL, APIs, events, async work, search | [Backend](references/backend.md) |
| Admin modules, components, state, layout and editor extensions | [Administration](references/administration.md) |
| Storefront rendering, JS, styles and plugin lifecycle | [Storefront and plugins](references/storefront-and-plugins.md) |
| Algorithms, validation, naming, helpers or hard-to-read control flow | [Readability](references/readability.md) |
| Cross-layer changes, test selection or unfamiliar repository areas | [Repository map and verification](references/repository-map.md) |

For PHP, Administration, PHPUnit, Storefront components, release documentation and PR work, also follow the matching existing `shopwell-*` skill.
Resolve relative source paths in the references from the repository root.

## Make ownership visible

Before implementing a substantial change, establish these facts in a short design note or the current task plan:

- The owning domain and entry point, existing capability to reuse, and representative source/test.
- The authoritative state or persisted model, its readers, and who may change it.
- Where external input is validated, which guarantees internal code receives, and how failure reaches the caller.
- The supported extension seam and its lifecycle when extension is part of the requirement.
- The behavior and integration boundary that verification must prove.

This is implementation preparation, not an additional user-approval gate.
Choose folders and types from those responsibilities; do not prescribe an identical folder tree for every feature.
Add an interface, registry, DTO, strategy or shared service when it expresses a real boundary or variation, not to make a skeleton look extensible.
An internal helper that changes with one caller can remain beside that caller.

## Write for the next maintainer

Use explicit control flow with braced guards and space between distinct steps.
Name the subject and operation so a reader can identify inputs, outputs and side effects without opening every helper.
Keep a short projection short; express multi-stage algorithms as named stages with visible intermediate results.
Make repeated lookup, mutation order, traversal termination and required indexing apparent.
Separate parsing, domain decisions, persistence and presentation where their contracts differ.
Preserve meaningful precision, timezone, null, absence and ordering semantics during cleanup.
Use defaults only for documented optional inputs; surface invalid present values and broken internal guarantees at their responsible boundary.
Comments explain a reason, constraint or contract; annotations and formatter success do not establish readability.

When the repository has no comparable algorithm or interaction, look for a relevant maintained upstream implementation and tests.
Record the source, revision and specific idea being adapted; retain Shopwell ownership and extension contracts.
Do not invent an architecture from a library's screenshot, popularity or directory names.
If no suitable public implementation exists, state the local contract, assumptions and design reasoning, then verify the behavior rather than claiming an external precedent.

## Review the result

Trace one successful path and one failure path through the actual callers.
Check that a plugin can use the promised seam without importing implementation internals, and that the implementation retains authorization and state ownership.
Inspect the changed source for readability as well as the rendered UI when layout or interaction changes.
Run the appropriate checks from the repository map and domain skills; report precisely what ran and what remains unverified.
For a file-by-file audit, keep a coverage ledger and distinguish an inspected file from a filename search.
Use findings from real tasks to refine the relevant reference; do not turn every local exception into a repository-wide rule.
