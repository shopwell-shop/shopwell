# Approval Flow

Approval Flow is an independent approval module in the existing Core host.
Its first integration is payment transfer approval.
The current skeleton includes node declarations, versioned drafts, graph and configuration compilation, immutable compilation releases, verified pure node entry and an internal instance suspension command.
It does not yet activate instances or commit approval tasks and decisions.

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
- `NodePlanCompiler` freezes the derived configurations with their structural graph-plan binding and checks exact executor availability.
- `Execution/ApprovalReleaseLoader` verifies persisted releases; `Execution/ApprovalNodeEntry` restores one typed configuration and enters its exact pure executor.
- `Execution/Instance` owns instance storage and controlled suspension; `Execution/Operation` retains the immutable command response and audit.
- `Execution/Executor` owns pure node-entry intents; `Execution/Effect` carries the controlled operations a future runtime must validate.
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

The YAML loader accepts `type`, `version`, `labelSnippet`, `configurationSchema`, optional `draftConfigurationSchema`, `ports` and required `allowedEffects` only.
`allowedEffects` is a nonempty list without duplicates, using `advance`, `requestApproval` or `complete` from `NodeEffectType`.
It declares the alternative intents permitted for one entry call; the executor still returns exactly one intent.
Programmatic declarations supply the enum values to `NodeTypeSpecification`.
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
Executor, policy, assignee, form, request and runtime dependencies are outside this graph-only contract; the later NodePlanCompiler checks exact executor service availability.
A candidate hash is not a replacement for those checks.
This graph-only stage creates no release rows or executable configuration handles.
The controlled release operation described below adds immutable compilation records; activation remains separate.

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
NodePlanCompiler also requires an exact-version executor registration for each node, without invoking it.
It does not validate request/form services, deployed artifacts, user eligibility, conditional routing or runtime scheduling.
Exact executable dependency admission and immutable release publication remain required before execution.

## Pure node entry

Business modules register `NodeExecutor` implementations with `approval_flow.node_executor` in their own DI.
Each service declares one namespaced type and one exact node version through `getType()` and `getVersion()`.
The internal registry rejects duplicate or invalid identities and never substitutes another version.
`enter(NodeConfiguration)` receives the typed result from the compiler for that same exact node identity.
The executor checks the expected concrete configuration type and returns one controlled intent: `AdvanceEffect`, `RequestApprovalEffect` or `CompleteEffect`.
There is no placeholder resume operation or caller-supplied execution context in this entry-only increment.

The start executor requests advancement, the approval executor requests task planning, and the end executor requests a process outcome.
`ApprovalOutcome` is a string-backed enum for the fixed process outcomes, approved and rejected.
Both the end configuration and completion effect carry that enum; the compiler converts admitted wire strings, and JSON encoding retains the original wire values.
Business-specific reasons belong to the owning business module rather than extending the framework's process outcomes.
`ApprovalNodeConfiguration` and `ApprovalFieldConfiguration` are supported immutable values carried by approval intents.
Their configured users are candidates, not evidence of existence, eligibility or permission.
An empty resolved candidate set must be handled by the future runtime using the configured rule; entry does not auto-approve or create assignments.

Effects do not grant execution authority and contain no instance, execution, task or token identity.
The future runtime must bind each intent to its current execution, resolve live facts, validate all effects together and commit through the authoritative write boundary.
`ApprovalNodeEntry` below connects persisted compilation records to these pure executors; no instance scheduler or authoritative runtime calls them yet.
There is no HTTP route or command that accepts a constructed configuration or plan for execution.
Exact service availability is only one prerequisite: a class name or version label is not an executable artifact pin.
The compilation release operation below verifies retained artifact bytes and stores immutable records.
Request/form dependencies, historical code loading, live runtime admission, task scheduling and commit authorization remain required before activation.

## Artifact verification and immutable release records

`ApprovalReleaseCompiler::compile(DraftGraph)` freshly compiles the node plan, then freezes the deployment artifacts bound to the actual compilation and node services.
Set `APPROVAL_FLOW_ARTIFACT_MANIFEST` to a deployment-owned JSON manifest path before using the publication service.
The required configuration is resolved by Symfony DI; there is no empty default or request-supplied manifest path.
The closed versioned schema is `Resources/schema/publication/1.0.0/artifact-manifest.schema.json`.
Each artifact declares `id`, exact `version`, relative `file`, lowercase SHA-256 and exact `requires` references; each service class binds to one `id@version`.
The deployment must attest the complete transitive code dependencies of these services, including their compilation chain and runtime libraries.
The loader traverses the used closure, reads retained bytes through Filesystem and verifies their digests without caching an earlier result.
Missing bindings, missing bytes, changed bytes, unavailable dependencies, cycles and duplicate identities or service bindings fail explicitly.
Distinct exact versions of an opaque artifact may coexist; no latest-version substitution or package-manager version selection occurs.
The frozen document sorts artifacts, bindings and dependency references, omits local paths and unused artifacts, and contributes to the full release hash alongside the node plan hash.

The deployment owns the attestation of service origins and dependency completeness.
Verifying retained bytes does not independently prove that loaded PHP came from them, that a build is reproducible or that historical code can be loaded by every worker.
A class name, version label, individual source-file hash or composer.lock is not a substitute for a complete retained deployment artifact.
The test fixtures explicitly use synthetic deployment attestations and provide no production provenance evidence.

`ApprovalReleaseService::publish(definitionId, expectedRevision, expectedHash, Context)` requires draft read and release create privileges before accessing the draft or manifest.
Its store locks the current draft row, checks the revision, recompiles those current bytes and compares the full preview hash.
A changed revision or publication hash returns HTTP 409 without inserting a record.
The same definition and content hash returns the original release ID and source revision; the deduplication lookup uses a locking current read even inside an older outer snapshot.
Insertion through DAL preserves the original Context, including Administration user attribution.
The transaction does not replay callbacks; failures roll back, and an enclosing transaction retains commit ownership and its locks.
DAL events are synchronous and must not be treated as durable external delivery before the outer commit.

The `approval_flow_release` DAL model retains graph, declaration contract, graph plan, node plan, dependency manifest, source revision, full content hash and creation attribution.
`Migration1795081112CreateApprovalFlowReleaseTable` creates it through the normal migration runner, with a unique definition/content hash and a restrictive definition foreign key.
Ordinary DAL and Sync insertion is rejected; the internal insert scope permits only insertion and never update or delete.
This is an in-process coordination boundary, not a sandbox against trusted PHP with direct database access.
`read(releaseId, Context)` requires release read privileges.
Later draft edits cannot rewrite an earlier record.

Publication currently means persisting an admitted compilation record.
There is no activation endpoint or instance execution path.
Request/form/user/policy dependencies, runtime admission, historical artifact loading and retention, worker capability checks and authoritative command commits must be completed before real approval execution.

## Verified configuration restoration and pure entry

`CompiledApprovalRelease` owns the complete frozen release content as readonly strings: `graphJson`, `contractJson`, `graphPlanJson`, `nodePlanJson`, `dependenciesJson` and `hash`.
Its constructor derives those values once from the compiler stages without retaining their object hierarchy.
Storage and execution consumers use this release boundary directly rather than traversing intermediate compiler results.
The stored wire documents and hash formula are unchanged.

`ApprovalReleaseLoader::load(releaseId, Context)` reads through the release service, preserving its privilege-first read boundary.
It recompiles the saved graph against this deployment and compares every frozen document and the full content hash before returning the immutable compiled value.
It does not read the current draft or replace a saved plan with a newly compiled variant.
A content or dependency mismatch fails with `APPROVAL_FLOW__RELEASE_VERIFICATION_FAILED`, HTTP 409 and the affected part, without returning a partially admitted release.
Missing exact declarations, compilers, executors, bindings or retained bytes fail through their existing explicit errors.
The loader neither caches a successful check nor writes storage.
This admits only releases that match the current deployment; historical artifact loading and worker readiness remain separate requirements.

`ApprovalNodeEntry::enter(releaseId, nodeId, Context)` accepts identities rather than a caller-constructed plan or configuration.
After whole-release verification, it finds the selected node in the freshly verified graph and uses the same exact-version `NodeCompiler` as publication to recover its typed configuration.
`NodeConfigurationCompiler` shares business-error normalization and lossless object encoding between this path and `NodePlanCompiler`; plugins keep their existing compiler and executor contracts.
The restored configuration's JSON must exactly match that node's frozen derived configuration before the executor is called.
A stateful or nondeterministic compiler cannot replace the planned configuration after the whole-release check.
Only one typed configuration is restored and used immediately, so a compiler reusing mutable objects cannot mutate a retained collection of runtime configurations.
There is no PHP object deserialization, caller-selected class name or second decoder registration catalogue.
An unknown node fails with `APPROVAL_FLOW__RELEASE_NODE_NOT_FOUND` rather than selecting another configuration.

Before returning an effect, `ApprovalNodeEntry` calls `NodeEffectValidator` against the exact node declaration and restored configuration.
Publication freezes the declaration's `allowedEffects` in sorted order; changing that permission set invalidates an existing release during verification.
An executor cannot return a kind outside that set.
`RequestApprovalEffect` must carry value-equivalent `ApprovalNodeConfiguration` rules, including candidates, aggregation policy, field access/required rules and the empty-assignee policy.
A separately constructed equivalent configuration is accepted; changing a rule is rejected.
The current task intent does not accept rules derived from an unrelated configuration class.
A completion effect from `EndNodeConfiguration` must preserve its frozen outcome.
Business nodes may compute an outcome when their declaration permits completion; this output check does not establish that the process can finish.

The returned advance, approval-request or completion effect is still an uncommitted intent.
Release read privileges permit this internal inspection path; they do not authorize a task, decision, transition or business side effect.
A future instance command must bind effects to locked current execution state, resolve live prerequisites and validate them before its authoritative commit.
There is no HTTP endpoint for this path and no instance, task, scheduler or activation behavior in this increment.

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
- ContentSystem result values and `ViolationSeverity`: narrow result contracts and string-backed enum values.
- Legacy Workflow node executors and adjacent tests: per-node responsibilities only, without importing their runtime or write coupling.
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

## Instance command transaction boundary

`ApprovalInstanceService::suspend(instanceId, commandId, expectedVersion, reason, Context)` is an internal administrative command.
It checks `approval_flow_instance:manage` before validating input, loading an instance or replaying a receipt.
Only System sources and Administration sources with that privilege are accepted.
The reason must contain non-whitespace text and fit within 1000 characters; it is preserved verbatim.

The store locks the instance row and reads its current state, then checks `(instanceId, commandId)` using a current locking read.
A matching request hash returns the original status and version, even if later commands have advanced the instance.
When joining an existing transaction, a new command requires the locked instance to be visible to the caller's read snapshot so that the DAL can load and update it.
A snapshot taken before the instance was admitted returns a conflict and the command can be retried in a new transaction.
The hash includes the operation, expected version, exact reason and original source/user/integration identity.
Reuse with another payload or actor fails with HTTP 409.
A new suspension requires the expected aggregate version and `running` status.
It changes the instance to `suspended`, increments the version once and inserts an immutable operation record in the same transaction.
The operation records both versions and statuses, actor, reason and request hash, serving as this command's audit and retry receipt.

`ApprovalInstanceService::enterStart(instanceId, commandId, expectedVersion, Context)` uses the same lock, version and receipt checks for system commands.
It takes the start node from the instance's verified release and validates the exact executor's effect.
Start entry requires one outgoing connection whose successor has no other incoming connection.
It inserts a completed start and a waiting successor into `approval_flow_execution`, advances the instance version, and saves the operation receipt in one transaction.
The waiting record identifies the node and incoming connection for later execution.

`ApprovalInstanceService::enterApproval(instanceId, executionId, commandId, expectedVersion, Context)` enters a waiting execution owned by that running instance.
The command uses the execution's node and instance's verified release to obtain a `RequestApprovalEffect`.
It requires every configured fixed assignee to identify an active Administration account.
The command checks account status with a shared locking read to serialize against concurrent account changes.
The task records retain the assignee's identity without a foreign key to the user record.
The command writes a pending task per assignee, moves the execution to `awaiting_approval`, and commits the new instance version and receipt.
It freezes the group's `ApprovalPolicy` on the execution.
`ApprovalNodeConfiguration` requires at least one candidate and exactly one for single approval, including configurations constructed by custom compilers.
A DAL write or event failure rolls back the command's writes, and the transaction is not automatically retried around callbacks.

`ApprovalInstanceService::decide(instanceId, taskId, commandId, expectedVersion, outcome, reason, Context)` accepts a personal Administration decision.
It requires `approval_flow_task:decide` on a user source; system and integration sources cannot cast personal votes.
Under the instance lock, the task must belong to that instance and its assigned user must still have an active account.
Ownership and current account status are checked before every receipt replay.
The request hash binds the task, outcome, exact reason, version and actor.
New votes require a pending task in an execution awaiting approval and a running instance at the expected version.
They consume the frozen group policy without invoking the node executor again.
Single approval completes on its only vote; all approval waits for every required vote; any approval completes on the first agreement.
Every policy uses the declared rejection veto.
Once a group reaches an outcome, its pending tasks are cancelled and the execution is completed with that outcome.
Each changed task records its `completionCommandId`, linking the decision or cancellation to the same transaction's immutable operation receipt and actor.
Decision notes may be empty and are retained verbatim.

`ApprovalInstanceWriteGuard::write()` scopes each DAL batch to system scope on the original Context and grants one pending write permit.
The guard consumes that permit at the start of `EntityWriteEvent`, before ordinary listeners run, and admits only the exact command objects and payloads from that batch.
`PreWriteValidationEvent` rejects unadmitted or changed commands; their weakly held identities remain valid for the DAL's own retry of the same batch.
A listener or success/error callback cannot use the same Context to obtain another write permit.
The scope and any unused pending permit are restored or removed on success and failure.
This uses object-keyed lifecycle tracking, as existing DAL validators do, without adding a public runtime callback contract.
The guard rejects ordinary DAL and Sync writes to instance, operation, execution and task records, including through associations.
Trusted in-process PHP with database access remains outside this guard's protection.

This increment provides no production instance creation, activation, resume, termination, decision or query endpoint.
Tests seed runtime facts explicitly and use real persisted releases to exercise the commit boundary; they do not simulate an admitted business start.
Suspension does not rewrite execution or task facts and does not require loading code artifacts, so an unhealthy deployment can still be stopped.
Future progression and decision commands must check the instance's running status under the same lock.
The group decision completes only that node execution.
Successor scheduling, instance completion, dynamic assignee sources, the empty-assignee skip policy, task queries and business result delivery remain subsequent increments.
The command does not schedule approval-domain events or external effects; durable Outbox delivery remains a separate requirement when such delivery is introduced.
