# Approval Flow

Approval Flow is an independent approval module in the existing Core host.
Its first integration is payment transfer approval.
The current skeleton includes node declarations, exact version lookup, versioned draft JSON, pure graph mutations and read-only diagnostics.
It does not yet publish or execute workflows.

## Skeleton responsibilities

The skeleton defines where a capability belongs, how it is declared and discovered, how the host calls it and which boundary validates its output.
A list of approval features or empty interfaces does not establish that structure.
Use ContentSystem's responsibility boundaries, naming and typed assembly as the backend reference; use Experience Studio's module, page, store and lifecycle boundaries for the future Administration client.
Keep business implementations and their DI in the owning business module.

- `Graph/Type/CoreNodeType` centralizes built-in identifiers without closing the catalogue to plugin types.
- `Graph/Type/Specification` owns validated node identity, ports and the exact configuration schema artifact.
- `Graph/Type/Loader` translates declarations into specifications and retains their source.
- `Graph/Type/Registry` combines loaders, rejects duplicate exact versions and never substitutes a newer version.
- `Graph/Codec` owns strict JSON decoding and deterministic draft encoding against the versioned wire contract.
- `Diagnostics` reports structure defects, incomplete drafts and missing capabilities without rewriting input.
- `Mutation` edits detached drafts and returns the complete result with diagnostics and explicit removed fragments.
- `Framework/DependencyInjection/approval-flow.php` assembles framework services through the existing host.

A loaded declaration does not establish executor availability, publication validity or authorization.
The schemas in `Resources/node-types` describe the initial configuration subset; executable gates and the full capability manifest remain separate implementation increments.
There are no published releases using these schemas yet.

## Registering another node source

Implement `NodeTypeLoader`, or register a separate `YamlNodeTypeLoader` with the business or plugin's source directories.
Tag that service with `approval_flow.node_type_loader` in its own DI configuration.
The core Registry discovers it through the tagged iterator without importing the business service.
Run `bin/console approval-flow:node-types` to load and inspect the actual assembled catalogue; duplicate or malformed declarations fail the command.
The wiring test compiles the actual framework configuration with an independently registered finance declaration.

The YAML loader accepts `type`, `version`, `labelSnippet`, `configurationSchema`, optional `draftConfigurationSchema` and `ports` only.
`configurationSchema` names a JSON file beside the YAML declaration; the schema must declare an object and a nonempty `$id`.
`draftConfigurationSchema` follows the same adjacent-file and object-schema rules.
When absent, the complete configuration schema also governs drafts.
Declare draft rules explicitly: core permits missing configuration fields while retaining types, unknown-field rejection and meaningful discriminators.
No recursive removal of `required` constraints occurs.
The loader reads declarations at the directory's top level in filename order.
Automatic module directory discovery is not implemented by this increment.
The public and internal boundaries are listed in [public-surface](docs/public-surface.md).

## Draft graph boundary

`Resources/schema/graph/1.0.0/approval-graph.schema.json` defines the closed wire structure.
It admits an empty or disconnected draft; configuration completeness and control flow are separate diagnostics.
`GraphCodec::decodeDraft()` rejects malformed structure and invalid present configuration, while retaining an unavailable exact node type for read-only inspection.
`encodeDraft()` additionally rejects missing capabilities.
`DraftGraph` retains immutable wire JSON rather than exposing nested mutable objects or pretending to be an executable graph.
Each mutation validates its raw fragment before serialization, works on a detached copy and admits the whole result before returning it.
Removing a node returns its complete configuration, incident connections and layout entries; the eventual persistence caller must handle loss confirmation.

JSON objects and lists retain their shapes, including `{}`, `[]`, `null`, `false` and `0`.
Object keys are ordered for deterministic encoding; list order remains authored.
Numbers must be finite, within ±9007199254740991 and retain their decimal value when encoded by the host.
Use decimal strings for monetary precision beyond that contract.
Duplicate JSON keys, unsafe numeric lexemes and unknown wire fields are rejected before they can be silently discarded or rounded.
Schema references are local to each artifact; remote retrieval is not enabled.
Validation disables schema defaults and data-transforming extensions so diagnostics cannot change inspected values.
A broken plugin schema fails with its declaration source rather than masquerading as an incomplete client draft.
The `extensions` maps are reserved: no namespace schemas are installed yet, so a nonempty map is preserved read-only and blocks writes.
Conditional routing remains a missing capability until its declaration and semantics are implemented.

Run `bin/console approval-flow:graph:diagnose <file>` to inspect a draft without saving it.
The command reports category, stable code and JSON Pointer; any violation returns a failing exit status.
A clean graph report only describes the implemented graph checks and never grants publication or execution.

## Implementation order

1. Validate declarations and real typed assembly with duplicate, missing and version coexistence cases.
2. Verify the authoritative draft codec, pure mutation and diagnostics with shared schema fixtures and real tagged business nodes.
3. Add controlled persistence and publication gates, then a minimal start → approval → end execution path.
4. Prove new nodes, operations and transfer integration through the public seams before expanding complex execution semantics.

Read the closest implementation and adjacent tests before each increment, and review naming, responsibility, failures and dependency direction alongside tests and lint.
Introduce Approval navigation when the corresponding capabilities and user paths need an entry point.
The legacy workflow menu is hidden through the existing menu visibility event; removing its code belongs to the completed replacement stage.

## Source calibration

- ContentSystem `README.md`, `NAMING.md` and role suffix rules: responsibility and ownership.
- `ContentSystemElementTypeRegistry` and its tests: loader aggregation and duplicate source reporting.
- `YamlTypeLoader`: declaration loading and source tracking, without introducing its decorator hierarchy.
- ContentSystem DI and Registry tests: actual tagged consumers and business-owned registrations.
- `StoredElementCodec` and its adjacent tests: paired wire conversion, strict shape admission and preservation of unknown capabilities.
- `MutationPipeline` and its tests: returned diagnostics describe the returned value, with explicit losses.
- ContentSystem draft/write-gate principles: draft incompleteness does not become malformed structure.
- `VisibleMenuTreeBuilder` and its tests: runtime visibility for stored, customized and synthesized menu entries.

The new graph and execution model must not depend on ContentSystem or the legacy workflow implementation.
