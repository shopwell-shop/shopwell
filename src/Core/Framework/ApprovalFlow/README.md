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
- `Definition` and `Draft` define the DAL records for a flow identity and its current draft.
- `ApprovalDraftService` owns draft admission, caller permissions and confirmed edits; `ApprovalDraftStore` isolates transactions, current row reads and DAL writes.
- `Publication` prepares immutable graph and declaration snapshots for subsequent compilation and executable dependency checks.
- `GraphPlanCompiler` derives graph indexes and a topological order from a freshly checked snapshot.
- `Node/Compilation` dispatches exact-version configuration compilers; `Node/Configuration` owns the concrete built-in values.
- `NodePlanCompiler` freezes the derived configurations with their structural graph-plan binding.
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
Removing a node returns its complete configuration, incident connections and layout entries; the persistence service requires explicit removal confirmation at the same revision.

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

## Controlled draft persistence

`ApprovalDraftService::create()` admits a draft JSON document and creates its definition and revision 1 atomically.
It requires both `approval_flow_definition:create` and `approval_flow_draft:create`.
`read()` requires `approval_flow_draft:read`; each edit requires both draft read and update privileges.
An Admin API source must hold those privileges, a system source may perform internal work, and customer or unrecognized sources are rejected.
The original Context and source reach DAL and its events; the internal write scope is restored on success and failure.

The stored graph is canonical JSON text with a database `JSON_VALID` constraint.
Its DAL field disables HTML sanitization because string content belongs to the graph contract, while the Codec remains responsible for admission.
The existing `CreatedByField` and `UpdatedByField` record Administration users; integration and system sources have no user attribution.
Definition names are separate management labels; graph metadata remains authored graph content.

Each edit supplies an expected integer revision.
The store opens a non-retrying transaction and locks the current draft row before comparing the revision and running the pure operation.
It reads graph bytes and revision from the locking query itself, so an older enclosing transaction's read snapshot cannot overwrite a newer edit.
The graph and incremented revision are written together through DAL.
A stale revision returns `APPROVAL_FLOW__DRAFT_REVISION_CONFLICT` with HTTP 409.
Database failures and synchronous DAL event failures propagate and roll back the transaction; the service does not replay their callbacks.
An enclosing transaction retains commit ownership and the row lock until it finishes.
DAL events can run before that outer commit, so event consumers must not treat them as durable external delivery.

Preview removals with the pure `GraphMutator` and review its complete removed fragments.
Persisting a node or connection removal requires `confirmRemoval: true` and the previewed revision.
An unconfirmed removal returns `APPROVAL_FLOW__REMOVAL_NOT_CONFIRMED` without changing the draft.
No unrestricted graph replacement or arbitrary mutation callback is exposed by the service.
Unavailable exact plugin types remain readable with capability diagnostics; edits are blocked until those declarations return.

`ApprovalDraftWriteGuard` rejects ordinary repository and Sync creates, updates and deletes for both tables, including system-scope calls.
Its internal scope is an in-process coordination mechanism, not a security sandbox for trusted PHP plugins with direct database access.
`Migration1795072461CreateApprovalFlowDraftTables` creates the new tables through the normal migration runner.
It performs no legacy Workflow conversion or compatibility reads.
This increment adds no HTTP action endpoints or Administration pages.

## Publication preflight

`GraphPublicationPreflight::prepare()` accepts a detached `DraftGraph` and returns a `GraphPublicationSnapshot` after the existing diagnostics pass reports no violations.
Incomplete configuration, disconnected or cyclic control flow, malformed structure and unavailable capabilities all block preparation.
Failure returns `APPROVAL_FLOW__PUBLICATION_GRAPH_INVALID` with the original diagnostic code, JSON Pointer and reason.
The diagnostic service still reports the full list for editor presentation.
Preflight never supplies configuration defaults, repairs a graph or writes a draft.

The snapshot retains canonical graph JSON and a separate contract document containing the exact graph schema bytes and the used node declarations.
Each captured declaration includes its exact type and version, complete configuration schema identity, bytes and SHA-256 hash, and declared port identities and directions.
Declarations are deduplicated and sorted by exact identity; ports are sorted by identity.
Unused declarations, deployment paths, snippet labels and draft-only schema rules do not affect the contract.
Node and connection order, authored configuration and layout remain part of the graph.
Objects, arrays, null, false and zero keep the Codec's existing wire semantics.

`graphHash` covers canonical graph bytes; `hash` covers both graph and frozen contract bytes through a fixed JSON envelope.
Changing a schema artifact changes the full hash even if its declared version stays the same.
The readonly snapshot owns only strings, so subsequent edits or catalogue changes cannot change a captured value.
It is an internal candidate value; constructing or retaining one grants no publication or execution authority.

The final publication operation must check caller permissions, lock and reread the draft, compare its expected revision, rerun preflight and compile against the full exact executable dependency set.
Executor, policy, assignee, form, request and runtime dependencies are outside this graph-only contract.
A candidate hash is not a replacement for those checks.
Release persistence and activation remain separate increments; no release rows or executable configuration handles are created here.

## Graph plan compilation

`GraphPlanCompiler::compile()` accepts `DraftGraph`, reruns publication preflight and derives a `CompiledGraphPlan` without storage or execution side effects.
It accepts no caller-supplied snapshot as proof of admission.
The versioned plan schema lives in `Resources/schema/plan/1.0.0/graph-plan.schema.json`.
The plan records compiler identity/version, graph and full snapshot hashes, start/end identities, node/connection positions in the canonical graph, incoming/outgoing connection IDs and a topological order.
Node configuration remains in the immutable graph snapshot rather than being copied into an independently editable plan.

Indexes and topological discovery take O(nodes + connections) work after preflight.
Node indexes preserve authored node order; connection indexes and each incoming/outgoing list preserve authored connection order.
The topological pass uses FIFO discovery as prerequisites finish and examines outgoing connections in authored order.
Parallel connections retain separate identities and count separately as graph dependencies.
Numeric-looking, Unicode and special-character IDs remain string references; serialized index maps remain JSON objects.
Node and connection IDs cannot start with U+0000, which PHP cannot represent as a public object key; the authoritative graph schema rejects them before compilation.
Embedded U+0000 remains supported.
Only JSON text and the immutable snapshot leave the compiler, so no mutable decoded graph is exposed.

`CompiledGraphPlan::hash` is SHA-256 over the complete plan JSON, including its graph and declaration snapshot bindings.
Formatting-equivalent drafts compile identically; changing graph content or captured declaration bytes invalidates the corresponding binding and plan hash.
Compiler identity/version describes this plan derivation; it is not a deployment artifact digest.
A topological order does not decide branch activation, joins, task order, approval policy or runtime scheduling.
Those semantics and exact executable dependencies still require their own compilation/admission before an immutable release can be published.
The internal plan value carries no execution authority.

## Node configuration compilation

`NodePlanCompiler::compile()` first compiles the input draft through `GraphPlanCompiler`, then resolves each node through the actual `approval_flow.node_compiler` service collection.
Implement `NodeCompiler` in the owning business module and tag its service there.
`getTypes()` maps node types to lists of exact node versions; these are not compiler artifact versions.
A declaration and its compiler are separate requirements: a declared type without an installed compiler fails explicitly.
Duplicate exact registrations fail the whole catalogue; there is no priority replacement, latest-version fallback or request-time registration.
The tag contract is also registered in static analysis.

`compile(type, version, configurationJson)` must validate the complete configuration shape and its own semantic rules, then return a concrete `NodeConfiguration`.
Use typed node-specific properties in that value; the shared compiler does not interpret arbitrary business fields.
Its `jsonSerialize()` must produce deterministic object JSON, including an explicit object for an empty configuration.
Only frozen JSON text leaves the plan compiler, so no plugin-owned mutable configuration object reaches storage or survives into an earlier plan.
Invalid output containers, unrepresentable object keys and lossy numbers are internal compiler defects; they do not become client success or empty plans.
Configuration validation failures use the owning module's `HttpException` with status 400 or 422.
An optional `path` parameter is local to that configuration; the host prefixes `/nodes/{index}/config`, retains the cause and reports `INVALID_NODE_CONFIGURATION`.
Without a local path the whole node configuration is identified.
Operational failures, permission errors and unexpected exceptions from compilation propagate unchanged; a malformed present path is a compiler fault.
Validation normalization surrounds the compiler call only; a configuration serializer throwing an HTTP exception is a producer fault, even when it uses a 4xx status.
Trusted in-process compilers are not a security sandbox.

`CoreNodeCompiler` validates exact complete schemas through the strict JSON decoder, then derives `StartNodeConfiguration`, `ApprovalNodeConfiguration` or `EndNodeConfiguration`.
Explicit user sources merge in first-appearance order, with one entry per distinct user identity.
Single approval requires exactly one distinct configured user; this does not establish that the user exists or is eligible at runtime.
Field access rules preserve authored order; duplicate rules, required noneditable fields and conditional rules without an expression compiler are rejected.
Unsupported rules remain writable draft content but cannot enter a node configuration plan.
The derived approval value retains the veto rejection policy and explicit empty-assignee behavior; it does not evaluate votes or resolve the empty set.

`CompiledNodePlan` retains the graph plan and freezes the derived configuration JSON; its SHA-256 binds the complete node plan bytes.
The internal envelope schema is `Resources/schema/plan/1.0.0/node-plan.schema.json`.
It validates the envelope shape, not a business compiler's semantics or execution authority.
The graph snapshot remains authoritative; normalized user lists are derived values, not a second editable graph.
This increment neither installs executors nor validates request/form services, deployed artifacts, user eligibility, conditional routing or runtime scheduling.
Exact executable dependency admission and immutable release publication remain required before execution.

## Implementation order

1. Validate declarations and real typed assembly with duplicate, missing and version coexistence cases.
2. Verify the authoritative draft codec, pure mutation and diagnostics with shared schema fixtures and real tagged business nodes.
3. Verify controlled draft persistence, graph preflight and structural plan compilation, then add node semantics, exact executable dependency gates and immutable releases.
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
- `PersistedLayoutMutator` and its tests: permission-first orchestration, locked version checks and caller Context retention.
- `ContentWorkspaceDefinition` and its tests: native actor fields and user associations.
- Flow and NumberRange DAL classes: entity accessors and collection conventions, without mechanical class-level restriction annotations.
- `VisibleMenuTreeBuilder` and its tests: runtime visibility for stored, customized and synthesized menu entries.

The new graph and execution model must not depend on ContentSystem or the legacy workflow implementation.
