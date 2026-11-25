# Approval Flow

Approval Flow is an independent approval module in the existing Core host.
Its first integration is payment transfer approval.
The current skeleton includes node and approval business type declarations, versioned drafts, immutable compilation releases, verified node entry, internal serial approval commands and terminal result delivery work.
Payment transfer submission now starts the initial approval tasks; committed group outcomes are advanced by the host scheduler.
Committed approval results are delivered to the owning business module; an applied approval can authorize the existing payout entry point after its inputs are rechecked.

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
- `ApprovalDraftService` owns draft admission and confirmed edits; `ApprovalDraftStore` isolates transactions, current row reads and DAL writes.
- `Publication` prepares immutable graph and declaration snapshots for subsequent compilation and executable dependency checks.
- `GraphPlanCompiler` derives graph indexes and a topological order from a freshly checked snapshot.
- `Node/Compilation` dispatches exact-version configuration compilers; `Node/Configuration` owns the concrete built-in values.
- `NodePlanCompiler` freezes the derived configurations with their structural graph-plan binding and checks exact executor availability.
- `Execution/ApprovalReleaseLoader` verifies persisted releases; `Execution/ApprovalNodeEntry` restores one typed configuration and enters its exact pure executor.
- `Execution/Instance` owns instance storage and controlled suspension; `Execution/Operation` retains the immutable command response and audit.
- `Execution/Executor` owns pure node-entry intents; `Execution/Effect` carries the controlled operations a future runtime must validate.
- `Binding` owns approval business type identities and their form schema declarations.
- `Result` retains terminal facts and independently leased delivery work, and invokes the exact-version business handler before acknowledging delivery.
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

## Approval business types

Business modules implement `ApprovalBusinessTypeLoader` and tag the service with `approval_flow.business_type_loader` in their own DI.
The framework assembles `ApprovalBusinessTypeRegistry` through the tagged iterator and installs no business types itself.
The supported loader returns immutable `ApprovalBusinessTypeSpecification` values; it need not import the internal YAML adapter.

Each specification declares `type`, an exact `version`, `labelSnippet`, `source` and raw object `formSchema`.
The source and label snippet carry diagnostic and display metadata; blank values do not prevent catalogue loading.
Start-node configuration references the declared identity through `businessType`.
The schema must declare an object and a nonempty `$id`; its exact original bytes determine `formSchemaHash` and retain empty JSON objects and lists.
This is declaration shape validation; full form schema compilation, data validation and access rules belong to business admission.

The Registry resolves only `type@version`, rejects a duplicate with both sources and caches only a complete successful catalogue.
Declarations remain stable for the compiled container lifetime; there is no request-time registration or fallback to a newer version.
Run `bin/console approval-flow:business-types` to inspect business type identities, form schema IDs and sources without reading business records.
Invalid catalogues fail before displaying a partial list.

The internal `YamlApprovalBusinessTypeLoader` reads explicitly supplied `ApprovalBusinessTypeSourceDirectory` entries in top-level filename order.
Its declarations accept only `type`, `version`, `labelSnippet` and `formSchema`.
`formSchema` names an adjacent JSON file.
Missing optional directories contribute nothing; malformed declarations, unknown fields and schema paths outside the declaration directory fail with source-aware errors.
Schema confinement compares resolved physical paths, so an adjacent symlink cannot read a schema outside that directory; a symlinked declaration directory is allowed.
File read failures retain the declaration source and original filesystem cause.
Automatic business directory discovery is not installed yet.

A catalogue entry grants no submission, binding activation, artifact availability, field access or result execution authority.
Business adapter and access policy interfaces remain subsequent increments.
The supported result handler and its transaction contract are described under terminal results below.
The container test contributes two expense business type versions through only the supported loader and value types; no framework configuration change or transfer-specific dependency is needed.

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
Draft, release and binding services are internal orchestration services; they do not repeat transport ACL or classify caller sources.
DAL writes retain the existing `AclWriteValidator` checks and the supplied Context.
Future HTTP actions must declare their route ACL before calling these services; no new public action is exposed here.
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

The final publication operation must lock and reread the draft, compare its expected revision, rerun preflight and compile against the full exact executable dependency set.
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

`ApprovalReleaseService::publish(definitionId, expectedRevision, expectedHash, Context)` performs controlled publication without repeating transport ACL.
The DAL retains release create authorization for Administration sources.
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
`read(releaseId, Context)` is an internal repository read; API callers require authorization at the transport boundary.
Later draft edits cannot rewrite an earlier record.

Publication currently means persisting an admitted compilation record.
There is no public activation or instance-execution endpoint.
Request/form/user/policy dependencies, runtime admission, historical artifact loading and retention, worker capability checks and authoritative command commits must be completed before real approval execution.

## Verified configuration restoration and pure entry

`CompiledApprovalRelease` owns the complete frozen release content as readonly strings: `graphJson`, `contractJson`, `graphPlanJson`, `nodePlanJson`, `dependenciesJson` and `hash`.
Its constructor derives those values once from the compiler stages without retaining their object hierarchy.
Storage and execution consumers use this release boundary directly rather than traversing intermediate compiler results.
The stored wire documents and hash formula are unchanged.

`ApprovalReleaseLoader::load(releaseId)` reads frozen compilation content through the release store using a shared locking read.
This current read sees releases published after an enclosing repeatable-read snapshot.
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
This internal inspection path does not authorize a task, decision, transition or business side effect.
A future instance command must bind effects to locked current execution state, resolve live prerequisites and validate them before its authoritative commit.
There is no HTTP endpoint for this path and no instance, task, scheduler or activation behavior in this increment.

## Implementation order

1. Validate declarations and real typed assembly with duplicate, missing and version coexistence cases.
2. Verify the authoritative draft codec, pure mutation and diagnostics with shared schema fixtures and real tagged business nodes.
3. Verify controlled draft persistence, graph preflight and structural plan compilation, then add node semantics, exact executable dependency gates and immutable releases.
4. Prove new nodes, operations and transfer integration through the public seams before expanding complex execution semantics.

Read the closest implementation and adjacent tests before each increment, and review naming, responsibility, failures and dependency direction alongside tests and lint.
The top-level Approvals navigation now groups My pending approvals, My completed reviews and My requests.
These pages and their shared request detail use the personal query API and `approval_flow.viewer`; they do not expose internal command services.
The query response carries readable publication and participant names plus labels for visible form fields from the frozen binding schema.
Public approve and reject actions remain the next increment.
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
A matching request hash returns the original status, version and optional process outcome, even if later commands have advanced the instance.
When joining an existing transaction, a new command requires the locked instance to be visible to the caller's read snapshot so that the DAL can load and update it.
A snapshot taken before the instance was admitted returns a conflict and the command can be retried in a new transaction.
The hash includes the operation, expected version, exact reason and original source/user/integration identity.
Reuse with another payload or actor fails with HTTP 409.
A new suspension requires the expected aggregate version and `running` status.
It changes the instance to `suspended`, increments the version once and inserts an immutable operation record in the same transaction.
The operation records both versions and statuses, actor, reason and request hash, serving as this command's audit and retry receipt.

`ApprovalInstanceService::enterStart(instanceId, commandId, expectedVersion, Context)` uses the same lock, version and receipt checks for internal progression.
Internal start, approval entry, progression and end commands retain the supplied Context and leave authorization to their actual entry point, like OrderTransactionStateHandler.
They do not require a SystemSource or repeat ID validation already performed by UUID conversion.
Their audit and request hash retain the caller source and Administration identity.
Start entry takes the start node from the instance's verified release and validates the exact executor's effect.
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

`ApprovalInstanceService::advanceApproval(instanceId, executionId, commandId, expectedVersion, Context)` is an internal progression command.
It requires a completed approval group with a frozen policy and outcome at the current serial execution tail.
All other executions must be completed and no task may remain pending.
An approved group queues the verified release's single unconditional successor, whose node must have no other incoming connection.
The approval executor is not entered again.
The committed successor becomes the new tail, preventing a fresh command from expanding the same approval twice.
A rejected group completes the instance as `completed` with `outcome=rejected`, without entering the approved path or requiring deployment artifacts again.

`ApprovalInstanceService::enterEnd(instanceId, executionId, commandId, expectedVersion, Context)` is an internal command for a waiting execution at the serial tail.
Only the verified release's end boundary may complete through this command.
The runtime verifies its frozen `CompleteEffect` and checks that no other execution or pending task remains active.
It completes the end execution and commits the instance's `completed` status, outcome, version and immutable receipt together.
The instance and receipt store `ApprovalOutcome` separately from lifecycle status.
Retries return that receipt's original outcome, including a null outcome from an earlier nonterminal command.
These commands remain available for individual internal steps; the scheduled progression path below advances a completed group to its next approval or end boundary.
Branch selection, fan-out and joins remain unsupported and cannot be collapsed into an arbitrary single successor.

`ApprovalInstanceWriteGuard::write()` scopes each DAL batch to system scope on the original Context and grants one pending write permit.
The guard consumes that permit at the start of `EntityWriteEvent`, before ordinary listeners run, and admits only the exact command objects and payloads from that batch.
`PreWriteValidationEvent` rejects unadmitted or changed commands; their weakly held identities remain valid for the DAL's own retry of the same batch.
A listener or success/error callback cannot use the same Context to obtain another write permit.
The scope and any unused pending permit are restored or removed on success and failure.
This uses object-keyed lifecycle tracking, as existing DAL validators do, without adding a public runtime callback contract.
The guard rejects ordinary DAL and Sync writes to instance, operation, execution and task records, including through associations.
Trusted in-process PHP with database access remains outside this guard's protection.

Business admission now creates a draft instance through the internal request service described below.
No activation, resume, termination, decision or query endpoint is installed.
Runtime command tests also seed execution facts explicitly to exercise their commit boundaries independently.
Suspension does not rewrite execution or task facts and does not require loading code artifacts, so an unhealthy deployment can still be stopped.
Progression and decision commands check the instance's running status under the same lock.
The personal group decision completes only that node execution; scheduled progression separately enters its successors or commits the rejection outcome.
Dynamic assignee sources and the empty-assignee skip policy remain subsequent increments.

## Personal approval queries

`GET /api/_action/approval-flow/requests` lists the current Administration user's approvals.
It requires `approval_flow:access`, which does not grant generic DAL read privileges.
The `view` parameter is `todo` (default), `done` or `started`.
Todo requires a pending task assigned to this user on a running instance; suspended instances do not advertise actionable work.
Done requires this user's approved or rejected task; a task cancelled by another reviewer's decision is excluded.
Started selects the original initiator.
Lists contain one summary per request, with lifecycle status, optional outcome, aggregate version and exact total count; forms are omitted.
Filtering by lifecycle `status` is optional; `limit` defaults to 25 and accepts 1–100, while `offset` defaults to zero and must be nonnegative.
Ownership and status filters run before pagination and counting, with stable `createdAt` and ID ordering.

`GET /api/_action/approval-flow/requests/{requestId}` is available to the original initiator and users who have been assigned a task on that instance, including completed and cancelled tasks.
Unknown and unrelated requests both return 404.
Personal queries require a real Administration user identity; system and integration callers do not acquire a personal inbox.
The detail contains the request summary, executed nodes, tasks and personal decision history ordered by aggregate version.
Opinions come from the decided task's original command receipt; cancellation does not become another user's opinion.
Raw actor JSON, command hashes, deployment content and Payment's private payout snapshot are omitted.
These read responses do not authorize a decision or a payment.

The initiator receives the original submitted form.
Participants receive only explicit field grants from their actually assigned `core:approval@1.0.0` nodes in the frozen node plan; unspecified fields are hidden and explicit hidden rules win.
This reads publication bytes without requiring retained executable artifacts or re-running the graph.
Nested object paths retain their object shape and omit ungranted siblings; null, false, zero, empty maps and lists retain their JSON types.
The current dot reference syntax cannot address property names containing literal dots.
Lists are atomic fields: a list with element-level rules is omitted until an element projection contract exists.
Unsupported node types and versions grant no form access; query behavior does not silently interpret their configuration as core field rules.

`ApprovalRequestQuery`, `ApprovalFormReader` and the HTTP controller remain internal services.
The instance's `executions` and execution's `tasks` DAL associations express the stored ownership path without a separate query table or a list of every matching task ID.
The response contract is documented in the Admin OpenAPI schema.
Administration lists, detail pages and public decision routes remain subsequent increments.

## Terminal results and delivery work

A command that completes the instance also appends one immutable `approval_flow_result` and one pending `approval_flow_outbox` row in that same transaction.
The result retains a stable ID, instance, bound release, terminal command, aggregate version, outcome and UTC completion time.
Its instance uniqueness and receipt foreign key retain one terminal result per instance and the original audit reference.
Receipt replay returns before recording another result.
Failure to write either the result or delivery work rolls back the instance transition, executions and command receipt.
Generic DAL/Sync inserts, updates and deletes of results are rejected by the instance write guard.

`ApprovalResultStore`, `ApprovalResultOutbox` and `ApprovalResultDelivery` are internal infrastructure contracts.
The supported immutable `ApprovalResult` carries core completion facts; handlers also receive the original `ApprovalRequestEntity` with its business identity, submission round, frozen business revision, binding and form.
This is the current read-only form contract; editable form revisions and remote consumers remain later work.
The work table follows the existing Webhook delivery-table pattern and has no generic DAL CRUD model.

`ApprovalResultOutbox::claim(Context)` accepts only a system source and returns at most one committed delivery lease.
It uses a current `FOR UPDATE SKIP LOCKED` read and increments the attempt when pending work becomes due or a previous lease has expired.
The same result ID is retained across attempts; the attempt fences acknowledgements and retries from older workers.
The lease lasts 60 seconds, and a live attempt may be acknowledged or rescheduled only before expiry.
Acknowledgement and retry read the clock after acquiring the row lock, so lock waits cannot preserve an expired lease.
Retry delays are 5, 30, 300 and 1800 seconds; the fifth failed attempt is retained as failed.
An expired fifth lease is also marked failed during the next claim instead of being delivered indefinitely.
Claim, acknowledgement and retry require an independent transaction and reject an already active caller transaction.
This prevents a caller from acting on work or reporting confirmation before its database commit.
Delivery failures change only work state and do not reopen completed instances or rewrite their results.

The scheduled task `approval_flow.deliver_results` invokes `ApprovalResultDispatcher` every minute, claiming at most 100 deliveries per run.
Register scheduled tasks and run the normal task runner and Messenger worker to enable it.
The dispatcher loads the original request and binding through DAL and selects the exact business type/version from `ApprovalResultHandlerRegistry`.
Register an `ApprovalResultHandler` with `approval_flow.result_handler` in the owning business module's DI.
`getType()` and `getVersion()` identify the exact business contract; duplicate identities fail the complete catalogue and no newer-version fallback is allowed.
Missing requests, bindings or handlers cannot become successful deliveries.

`handle(result, request, Context)` must commit the business change and its result-ID deduplication record together before returning.
Only then does the dispatcher acknowledge the current lease; exceptions schedule bounded retries and leave the approval instance completed.
A crash between the business commit and acknowledgement may deliver the same stable result ID again.
The handler must replay its prior success even if the live business record later changes.
Network side effects require the business's own durable outbox and must not run under the business row lock.
The handler is an exclusive business application boundary, rather than a broadcast notification whose listener presence could be mistaken for successful consumption.

Payment registers `payment:transfer@1.0.0` through its own DI without a transfer branch in Framework.
Its handler locks the transfer, checks the original request and current business snapshot against the submitted revision, and inserts an immutable `payment_transfer_approval_result` through DAL.
The same row is the business approval decision and its inbox deduplication record; it does not change the payout lifecycle or invoke a channel.
Unavailable recipients/accounts or changed transfer facts leave the result pending for bounded retry instead of applying it to different inputs.
Existing receipts replay before rechecking live facts, so a redelivery after the business commit does not fail because those facts changed later.
Ordinary Administration writes, updates and deletes cannot replace that decision.
The migration adds a new table without converting legacy Workflow records.

Payment's existing submission preflight and atomic attempt claim both call `PaymentTransferApprovalService::assertApproved()`.
For a transfer with an Approval Flow submission, `PaymentTransferApprovalResultVerifier` requires the original request's applied `Approved` decision.
A pending, rejected or not-yet-delivered decision cannot authorize a payout or select the legacy Workflow path.
Only transfers without a new submission use their existing Workflow approval binding; there is no conversion or missing-capability fallback.
Under the transfer lock, the verifier reads the current business revision and decision, then rechecks the current transfer, recipient and active merchant account.
It compares that snapshot with the submitted revision and the outgoing transfer's financial inputs and reason before returning the approved recipient.
The same check runs inside the attempt claim; failure rolls back the claim before any channel call.
The verifier does not duplicate the submitter's app ownership, state or prior-attempt checks.
Approval result delivery still does not submit a payout automatically, and the Administration creation and approval pages remain later work.

Handler identity is selected by the frozen business version; deployment artifact pins for handler code, editable form results and remote delivery are not implemented by this increment.
The lease prevents a stale local confirmation; it does not provide exactly-once external execution.

## Versioned business bindings

`ApprovalBindingService::save(type, businessVersion, releaseId, expectedRevision, Context)` saves the next immutable configuration for one business type.
It freezes the exact business version and form schema bytes, ID and hash alongside the selected release ID.
The release is verified with the current deployment, its start node must name the same business type, and its `meta.formSchemaRef` must name that form schema.
A release currently carries only a form reference; this check does not claim to verify a form hash absent from the release contract.

Saving does not enable the new configuration.
`head(type, Context)` reports the latest saved revision, nullable active revision and activation version.
`activate(type, revision, expectedVersion, Context)` selects a saved revision after verifying the frozen business form and release again.
Each successful activation increments the version, including a return to an older revision.
The revision and activation version protect different changes; an intervening save does not change the active selection.

The store locks the per-type head and reads the target revision with current locking reads.
A unique-key upsert initializes the coordination row inside the save transaction.
Immutable binding inserts use the DAL; the store updates the locked coordination head directly so DAL existence reads cannot hide a head created after an older transaction snapshot.
Ordinary DAL and Sync writes to the head are rejected; internal pointer changes emit no DAL head-written event.
The head's composite foreign key prevents selecting a revision belonging to another business type.
Same-type save/activation reentry is rejected until the current operation returns, including calls made from DAL listeners.
The write guard admits one binding insert batch and checks its command identities and payloads; a known scope name or event callback cannot reuse that admission.

`read(type, revision, Context)` reads saved history; `active(type, Context)` reads the saved active selection.
These internal configuration reads grant no business submission or execution authority.
They do not continuously validate deployment or form availability; actual submission must validate its selected configuration again.
No instance is created or rebound by saving or activating a binding.
Business admission retains the chosen binding and release; later activations affect subsequent requests only.
Conditional routing, rollout, business adapters, field authorization, menu actions and workers are subsequent capabilities.

## Business-owned binding validation

After verifying the release, business identity and form reference, the binding service dispatches `ApprovalBindingValidationEvent` before saving or activating a revision.
Listeners receive the exact business declaration, immutable verified graph JSON and the original `Context`.
They may reject the binding with a business exception; they must not perform network calls or change business state.
Validation runs inside the binding store's transaction and same-type reentry guard, including the first save.
A failed validation rolls back the coordination row and leaves the previous active revision unchanged.

Payment contributes `payment:transfer@1.0.0` through its own DI and resource directory.
Its form declares integer amounts in currency minor units, within `PaymentTransferDraft::MAX_AMOUNT`, plus currency, a masked recipient label, a merchant account label and a nullable reason.
The labels contain no raw account identities, ciphertext or credentials.
The Payment subscriber rejects editable form rules and approvals configured to skip when assignees are unavailable.
Payment currently admits only the standard core approval node for review work; a custom node declaring a request-approval effect is rejected because its raw configuration does not promise the core approval schema.
Every path from start must reach a mandatory core approval before any node whose declaration allows a complete effect.
Extension nodes with only advance effects can precede that approval without changes to the subscriber or shared skeleton.
These rules validate configuration; they neither resolve live reviewers nor create an application, grant payout approval or switch the existing transfer interface.
Business submission must revalidate its selected binding and freeze authoritative business inputs in its transaction.

## Business request admission

`ApprovalRequestService::create(type, businessId, round, businessRevision, formJson, Context)` accepts a snapshot generated by the owning business service.
It requires an Administration user, selects the current active binding under a shared lock, and revalidates the exact declaration, deployment, release and business-owned configuration rules.
The form is checked against the frozen JSON Schema before an immutable `approval_flow_request` and its draft `approval_flow_instance` are inserted in one DAL batch.
The request retains the binding ID, business identity and round, business revision, initiator and original form JSON bytes.
The binding retains the exact business version, form schema and release; activation never rebinds existing requests.
A unique business-type/business-ID/round key and a unique instance key enforce those identities in storage.

The business owner must serialize its submission identity and freeze its authoritative evidence in the same database transaction.
`find(type, businessId, round, Context)` performs a current read for submission retry and returns the original request only to its initiator.
It is an internal retry lookup, not a general query endpoint or resource authorization policy.
Binding selection and retry reads use locking reads so an older repeatable-read snapshot cannot select stale facts.
The request DAL batch uses a dedicated scope on the original Context and preserves DAL permissions for both request and instance creation.
Ordinary DAL/Sync writes and borrowed listener contexts cannot create or rewrite request facts.

Payment owns `PaymentTransferApprovalRequestService::submit(transferId, Context)` and constructs the form from the locked transfer, recipient and account.
Only a created, unexecuted transfer without a legacy approval may acquire a new request.
An existing request replays its start command before being returned, without rechecking the current binding or mutable business eligibility, so retry preserves its original snapshot.
The form contains integer minor-unit amounts, a masked recipient label and an account display name; private evidence stays in `payment_transfer_approval_request` with the recipient identity still encrypted.
Request, private evidence, initial executions, reviewer tasks and the running instance commit or roll back together, without channel calls.
Payment currently uses submission round 1 because reapplication creates another transfer record.

Both Payment approval entry points lock the same transfer row and reject a committed binding in the other system.
They also share a per-transfer operation guard while their callbacks run: database row locks are reentrant on the same connection.
The new path covers validation, DAL events and private freeze; the legacy path covers its lookup, runtime start and binding write.
The old store owns this temporary coordination while both implementations coexist; it is not a new shared-framework extension contract.
Generic DAL updates or deletes of private evidence are rejected by Payment's existing write protection.

`ApprovalRequestService::start(requestId, Context)` starts an admitted draft only for its original Administration initiator.
The immutable request ID also identifies its single start command; the existing instance transaction locks the instance, checks version 1 and commits version 2 with its start receipt.
The command enters the frozen release's start node, follows serial advance-only nodes and stops at the first approval, resolving active users and creating their tasks with the frozen approval policy.
It uses the original Context and instance write gate; the shared skeleton has no Payment dependency.
Initial splits, joins and completion before approval are explicitly rejected in this increment.
A failure leaves no partial executions, tasks or receipt; an already admitted request remains draft and can be retried.
Payment calls this operation inside its submission transaction, so a failed first start rolls back admission and private evidence too.

A repeated start returns its original receipt before current instance state or deployment verification, even after later suspension or missing deployment artifacts.
It does not recreate tasks, re-enter plugin executors or switch to a newer active binding.
An in-memory per-instance command guard rejects recursive commands from DAL events; database row locks serialize independent processes.
Tests exercise tagged advance-only plugin nodes before and after the first approval, a personal decision against a generated task and terminal result creation.
Initial start verifies the release once for start entry and once for the complete successor route; later progression verifies it once for the route.
There is no cross-command admission cache; large-graph performance has not been measured.

This is still an internal integration path without Administration pages or payout execution.
Entry-point resource authorization remains the responsibility of the future action endpoint, as with the existing business-owned paths.

## Scheduled serial progression

`ApprovalInstanceService::progress(instanceId, executionId, commandId, expectedVersion, Context)` advances a completed approval group under the existing instance lock.
After approval it enters the single unconditional route, completing pure advance nodes and stopping at the next approval or the published end boundary.
The route is loaded and verified once per call; plugin executors remain pure and cannot perform external effects.
Only the published end may return a terminal completion along this path.
A rejection completes the instance without requiring deployment artifacts or entering the approved route.
Executions, new tasks, the instance version, receipt and any terminal result/outbox record commit in one transaction.
A failed progression rolls back that transaction and preserves the earlier personal decisions for retry.

`ApprovalProgressionRunner` scans running instances whose latest execution is a completed approval group with a frozen outcome.
The host task `approval_flow.progress` runs every minute and uses the completed execution ID as its command ID.
Candidates are read in pages of 100 ordered by instance ID; each command rechecks current facts under its instance lock.
A failed candidate is logged and does not prevent later pages from running; subsequent task runs retry it from current storage.
Concurrent stale candidates lose through the existing version or terminal-state checks instead of creating duplicate work.
No new queue table is needed: the committed approval group is the durable continuation point.

Deployments must register scheduled tasks with `bin/console scheduled-task:register` and run the normal scheduled-task runner and Messenger worker.
Tests cover host task discovery, registered handler execution, repeat runs, suspended instances, failure recovery, 101 candidates across pages and competing processes.
Actual broker delivery, multi-worker throughput and latency have not been measured.
This task advances serial approval work only; `approval_flow.deliver_results` separately applies business results without executing transfers.
