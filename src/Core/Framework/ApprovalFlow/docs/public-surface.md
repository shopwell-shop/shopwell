# Approval Flow public surface

The supported extension surface covers node and approval business type declaration, configuration compilation, pure node entry, assembly and business-owned binding validation.
The DAL models `approval_flow_binding`, `approval_flow_binding_head`, `approval_flow_definition`, `approval_flow_draft`, `approval_flow_release`, `approval_flow_instance`, `approval_flow_operation`, `approval_flow_execution`, `approval_flow_execution_scope`, `approval_flow_execution_branch` and `approval_flow_task` provide typed Definition, Entity and Collection classes using the host's normal repository contracts.
Writes use the controlled draft, release and instance paths.
Personal request query HTTP routes are supported as described below; publication and runtime command services remain internal.
The diagnostic commands `approval-flow:node-types` and `approval-flow:graph:diagnose <file>` inspect declarations and draft graphs without publishing or executing a process.

## Personal request query API

- `GET /api/_action/approval-flow/requests` supports `view=todo|done|started`, optional lifecycle `status`, `limit` (1–100, default 25) and nonnegative `offset` (default 0).
- `GET /api/_action/approval-flow/requests/{requestId}` returns the request summary, viewer-filtered form, executed nodes, tasks and decision history.

Both routes require `approval_flow:access` and an Administration user identity, excluding integration identities.
This privilege does not grant the generic entity read privileges.
Started uses the original initiator; todo uses this user's pending task on a running instance; done uses this user's approved or rejected task and excludes cancelled tasks.
Request-level results are deduplicated, counted and paginated after applying ownership, with stable creation-time and ID ordering.
Only the initiator or a user with an assigned task may read a detail; unknown and unrelated IDs both return 404.
There is no manager-wide visibility override in these personal routes.

The initiator reads the original form; participants read explicit grants from their assigned `core:approval@1.0.0` nodes in the frozen node plan.
Unspecified fields are hidden, explicit hidden rules take priority, and unsupported nodes and versions grant no field access.
Nested objects retain only visible branches; lists are atomic and are omitted when element rules would require unsupported projection.
Literal dots in property names cannot be addressed by the current field reference syntax.
Empty visible objects and lists retain their shapes; a fully hidden form is `{}`.
Private business evidence, raw actor JSON, request hashes and executable release content are not response fields.
Decision history includes only personal approved/rejected tasks and their original receipt reason/time/version; system cancellation does not fabricate an opinion.
These queries grant no task decision, form edit or payment execution authority.

The response schema is maintained in `AdminApi/paths/approval-flow-query.json`.
`ApprovalRequestQuery`, `ApprovalFormReader`, `ApprovalRequestView` and `ApprovalRequestController` are implementation services rather than supported PHP extension contracts.
The `executions` instance association and `tasks` execution association use the normal DAL collection model and do not add tables or write authority.
Summary responses also include the frozen publication name as `title` and the current nullable `initiatorName`.
Details include `formLabels` keyed by visible field path and `participantNames` for the initiator and assigned reviewers.
Field labels use the binding's frozen JSON Schema `title` and optional `x-shopwell-label-snippet`; hidden fields have no label entry.
Names are display data and grant no generic user read access.

## Administration entry points

The top-level Approvals menu contains My pending approvals, My completed reviews and My requests.
Routes `sw.approval.flow.todo`, `sw.approval.flow.done` and `sw.approval.flow.started` share the personal list page; `sw.approval.flow.detail` opens an application from a list.
The `approval_flow.viewer` role mapping grants only `approval_flow:access`; routes and menu entries use that mapping.
The list retains page, limit and status in its URL, and detail links preserve the originating inbox and filters.
The private module API service uses the personal query endpoints above rather than generic entity repositories.
The pages display visible form data, progress and decision history.
The `approval_flow.editor` role adds `approval_flow_task:decide` and depends on viewer; it grants no generic entity write or payment execution privileges.
Only the signed-in user's pending tasks on a running instance display approve and reject actions.

## Personal task decision API

`POST /api/_action/approval-flow/instances/{instanceId}/tasks/{taskId}/decide` accepts `commandId`, positive `expectedVersion`, `outcome` (`approved` or `rejected`) and optional `reason` (at most 1000 characters, default empty).
It requires `approval_flow_task:decide` and the active assigned Administration user; the Context determines the actor even if unrelated identity fields appear in the body.
The existing instance transaction verifies task assignment and current state, commits one opinion and its group result, and records an immutable command receipt.
An identical retry returns the original receipt even after subsequent progression; reusing a command ID with different input returns 409.
A different command with an outdated version also returns 409 and writes no opinion.
The response contains `instanceId`, `commandId`, `status`, `aggregateVersion` and `outcome` from the committed command.
A personal opinion receipt remains `running` with null request outcome; it does not assert final approval or payment execution.
The existing `approval_flow.progress` scheduled task continues the committed group outcome independently.
The HTTP action delegates strategy execution to the decision command, which verifies the frozen release before evaluating a new opinion.
It does not perform successor progression inside the decision request; receipt replay does not reload execution artifacts.

The confirmation dialog keeps its first command ID, version, outcome and reason for an uncertain-result retry.
A definite rejection or state conflict asks the user to refresh rather than silently posting a new command at a new version.
After success the detail reload is a separate operation; a failed reload does not present the saved opinion as failed.
The request schema and response receipt are maintained in `AdminApi/paths/approval-flow-task.json`.
`ApprovalTaskController` remains an internal PHP adapter.
Approval and Process definitions are available under the Approval menu.

## Node declarations

- `NodeTypeLoader::load()` returns a list of `NodeTypeSpecification` values.
- `NodeTypeSpecification` contains a namespaced type, an exact version, a label snippet, source, immutable schema bytes and a named input/output port map.
- Its derived complete-schema ID and SHA-256 digest refer to those exact bytes.
- An optional explicit draft configuration schema controls missing fields; absent draft artifacts use the complete schema unchanged.
- `NodeTypeRegistry::all()` returns specifications keyed by `type@version`.
- `NodeTypeRegistry::get(type, version)` resolves only that exact identity and reports an explicit error if it is missing.
- `approval_flow.node_type_loader` requires services implementing `NodeTypeLoader`; the same contract is registered in static analysis.
- `ApprovalFlowException` supplies stable error codes and source-aware declaration failures.

A consumer cannot infer executability or authorization from the presence of a declaration.
Loaders must be deterministic for the lifetime of the compiled container; there is no request-time registration or hot replacement API.
The Registry caches only a complete successful load and never exposes a partially loaded catalogue.

## Approval business types

- `ApprovalBusinessTypeLoader::load()` returns a list of `ApprovalBusinessTypeSpecification` values; register it with `approval_flow.business_type_loader` in the owning business module.
- The service tag's interface contract is registered in static analysis alongside the existing node loader contracts.
- `ApprovalBusinessTypeSpecification` carries the exact business type/version, label snippet, source and form schema bytes.
- `formSchemaId` and `formSchemaHash` derive from the supplied schema; the hash retains the original bytes, including JSON map/list distinctions.
- `ApprovalBusinessTypeRegistry::all()` returns a complete catalogue keyed by `type@version`; `get(type, version)` never substitutes another version.

Duplicate exact versions fail with both declaration sources, including subsequent reads after a failed load.
Loaders must remain deterministic for the compiled container lifetime.
The schema root and identity are checked on declaration construction; full schema compilation, form data validation and field authorization are not provided by the catalogue.
Business type declarations do not establish service availability, artifact retention, business authorization or consumer routing.
Source and label snippet are diagnostic and display metadata, independent of catalogue admission.
The start-node configuration uses `businessType` for the namespaced business identity.
Business adapter and access policy implementation interfaces remain subsequent increments.
The supported result handler contract is described under terminal results below.
The diagnostic command `approval-flow:business-types` reads declarations only and fails before displaying an invalid partial catalogue.
`YamlApprovalBusinessTypeLoader`, `ApprovalBusinessTypeSourceDirectory` and `ApprovalBusinessTypeListCommand` are internal adapters.
Plugins implement the public loader contract instead of depending on these file or console adapters.

## Node configuration compilers

- `NodeCompiler` declares exact supported node versions through `getTypes()` and compiles complete configuration JSON into a concrete `NodeConfiguration`.
- Register implementations with `approval_flow.node_compiler` in the owning module; its interface contract is enforced by static analysis.
- `NodeConfiguration` exposes deterministic node-specific object JSON through `jsonSerialize()`; empty objects must remain explicit objects.

Implementations own complete input validation and semantic rules, and perform no user resolution, business reads, persistence or node execution.
They remain stable for the compiled container lifetime; collisions fail rather than selecting a priority winner.
The host freezes each result immediately and reports malformed output or registration metadata as an internal compiler fault.
Use the owning module's `HttpException` with status 400 or 422 for invalid configuration, optionally carrying a configuration-local `path`; the host adds the node path and retains the original cause.
Other statuses and unexpected failures propagate unchanged.
The supported compiler contract accepts business-owned configuration types without exposing internal publication orchestration services.
Declaring a compiler does not establish runtime capability or a deployment artifact pin.

## Node entry and effects

- `NodeExecutor` declares one exact type/version and accepts a compiler-produced `NodeConfiguration` for that identity.
- Register it with `approval_flow.node_executor`; static analysis enforces the interface.
- `NodeEffectType` provides the declaration values `Advance`, `RequestApproval`, `Complete`, `OpenScope` and `Wait`.
- `NodeTypeSpecification` requires an explicit, nonempty `allowedEffects` list without duplicates; YAML declarations use the corresponding wire strings.
- `enter()` returns `NodeEffect`. Use the framework effect values `AdvanceEffect`, `RequestApprovalEffect`, `CompleteEffect`, `OpenScopeEffect` and `WaitEffect`.
- `ApprovalNodeConfiguration` and `ApprovalFieldConfiguration` are supported immutable values for approval rules and candidates carried by `RequestApprovalEffect`.
- `ApprovalOutcome` fixes approved/rejected process outcomes at the PHP type boundary; `CompleteEffect` accepts this enum rather than an arbitrary string.

A plugin may implement its own node compiler, configuration and executor and return one of the controlled intents without importing internal framework types.
The current configuration classes identify the v1 core contract; future incompatible versions must retain their own configuration type and exact services.
The internal entry path rejects unsupported effect implementations, effects outside the published permission set, changed approval rules and changed end-node outcomes.
Approval intents currently require a matching compiled `ApprovalNodeConfiguration`; custom configurations cannot supply unrelated task rules.
Declared business completion intents remain subject to runtime token, scope and state validation.
Executors are pure entry services, not authorization or persistence extension points.
Effects are uncommitted proposals. The runtime supplies execution identity, resolves candidates and validates the resulting plan before applying its changes.
The internal entry path restores compiler-produced configurations from a command-scoped verified release. The execution plan, command handlers and storage adapter are internal.

## Internal adapters

`YamlNodeTypeLoader`, `NodeTypeSourceDirectory` and framework DI are internal adapters.
`ApprovalDraftService`, `ApprovalDraftStore`, the write guard and revision-bearing draft results are internal while the controlled persistence contract is exercised.
`GraphPublicationPreflight` and `GraphPublicationSnapshot` are internal graph preparation contracts.
They freeze admitted graph and declaration bytes without establishing a published release, runtime configuration handle or executable dependency set.
`GraphPlanCompiler`, `CompiledGraphPlan` and the versioned structural plan schema remain internal.
`CoreNodeCompiler`, `NodeCompilerRegistry`, the start/end configuration values, `NodePlanCompiler`, `CompiledNodePlan` and its envelope schema also remain internal.
`NodeExecutorRegistry`, `StartExecutor`, `ApprovalExecutor` and `EndExecutor` remain internal implementations; extensions implement NodeExecutor instead of inheriting them.
Structural plan compilation rechecks its draft input and preserves graph identity; compiler versions and topological order do not grant runtime capability or scheduling guarantees.
Ordinary repository and Sync writes to definitions, drafts and releases are rejected; the storage adapter opens the internal scope only for admitted writes.
The scope does not restrict trusted in-process PHP code that can also access the database directly.
Graph values, codecs, pure mutations, diagnostics and the versioned draft wire schema remain internal while their contracts are exercised by real consumers.
No executable graph or validated runtime configuration handle is exposed by this increment.
They are not extension inheritance hooks.
A plugin may register its own loader service through the supported interface and tag rather than importing internal framework configuration.

## Verification

The Registry tests cover independent loaders, duplicate exact versions and missing-version failure.
The specification and YAML tests cover stable identity, schema shape, exact bytes, declaration errors and file reference constraints.
The wiring test compiles a real Symfony container with core and business-owned declarations, without changing framework configuration for the business node.
Graph tests additionally cover explicit draft rules, closed wire shapes, exact missing versions, JSON value preservation, safe numeric lexemes, duplicate keys, graph references, control-flow diagnostics and atomic mutations with complete loss records.
The graph wiring test exercises the same independently registered finance source through the actual codec and mutator services.
Persistence integration tests cover DAL mapping, DAL write permissions, source/scope preservation, revision conflicts, removal confirmation, complete rollback, direct-write rejection and plugin loss/restoration.
An independent writer process establishes an older read snapshot, proves real row contention through a lock timeout and then receives a revision conflict after the first writer commits.
Publication preflight tests cover complete graph admission, blocked incomplete graphs, canonical hashing, exact used declaration capture, schema byte drift and business-owned tagged loader assembly.
Integration tests prove preparation leaves saved draft bytes and revisions unchanged, and later draft edits cannot alter an earlier snapshot.
Compiler tests cover graph and snapshot bindings, versioned wire shape, branch/merge topology, parallel connections, numeric/special IDs and independent business declarations through actual DI.
Integration tests prove graph plan compilation leaves stored draft bytes/revisions unchanged on success and failure.
The exact executor availability gate is covered by real container removal and plugin registration tests.
Deployment attestation and retained byte checks now cover compilation records; historical executable loading, remaining activation gates, runtime behavior and transfer execution require future evidence.

Node configuration tests cover real tagged business compilers, exact versions and missing registrations, semantic rules, deterministic byte freezing, mutable plugin results and rejected compiler outputs.
Database integration verifies that success and semantic failure leave saved draft bytes and revisions unchanged.

Node entry tests cover typed configuration mismatch, exact version coexistence, duplicate/invalid registration failures and the core entry intents.
Kernel integration composes actual registered compilers and executors and verifies saved draft bytes and revision remain unchanged.

## Internal compilation publication

`ArtifactManifestLoader`, its versioned deployment manifest schema, `ApprovalReleaseCompiler` and `CompiledApprovalRelease` remain internal.
`ApprovalReleaseService`, `ApprovalReleaseStore`, `PublishedApprovalRelease` and `ApprovalReleaseWriteGuard` also remain internal while their controlled publication contract is exercised.
The release DAL model follows the normal supported Definition/Entity/Collection contracts; repository access does not authorize bypassing controlled insertion or changing existing content.
Publication requires the deployment-owned `APPROVAL_FLOW_ARTIFACT_MANIFEST` setting and freezes exact used-service bindings plus their retained artifact dependency closure.
Opaque artifact versions can coexist; duplicate complete identities and duplicate service bindings fail.
Deployment attests origins and closure completeness; SHA-256 verification covers retained bytes and does not establish loaded-code provenance, historical loading or worker readiness.
These records grant no activation authority, and there is no public activation or instance-execution HTTP API.

Artifact tests cover transitive closure, deterministic ordering, multiple retained versions, missing files/bindings/dependencies, changed bytes, cycles and conflicting declarations.
Compiler tests use real core and tagged business services, and distinguish graph or artifact hash changes from formatting-only edits.
Release integration tests cover immutable content, preview conflicts, DAL write authorization, Administration actor/context preservation, DAL/Sync write rejection, transaction rollback and later draft edits.
A separate publisher process proves contention and reuse after an older read snapshot; removing the deduplication current read makes that test fail.
The release migration test creates an isolated database and verifies repeated execution preserves records, the unique key and foreign-key deletion policies.

## Internal verified node entry

`NodeConfigurationCompiler`, `ApprovalReleaseLoader` and `ApprovalNodeEntry` remain internal orchestration services.
Plugins continue to implement `NodeCompiler`, `NodeConfiguration` and `NodeExecutor`; no additional decoder interface or service tag is required.
`CompiledApprovalRelease` exposes the complete frozen documents and hash directly as readonly strings and does not expose intermediate compiler-stage objects.
Its storage wire format and hash derivation remain unchanged.

Loading a persisted release requires exact agreement with a fresh compilation of its saved graph, all frozen documents, current deployment bindings and full content hash.
Only the selected node's typed configuration is restored, verified against its frozen output and immediately handed to its exact executor.
No configuration collection escapes for later mutation, and no caller-supplied object grants execution authority.
The declaration and compiled-rule checks validate the returned effect, which remains uncommitted and cannot authorize instance state or business side effects.
Commands that need a published graph verify the whole release once and reuse its executable view for their node entries.
Ready execution units restore their configurations from a verified release; large-graph performance remains unmeasured.

Integration tests cover every changed frozen document, changed deployment bytes, missing releases and independence from later draft edits.
Actual DI assemblies exercise core and independently tagged business nodes, missing exact executor versions, missing artifact bindings, whole-release failure before entry and compiler drift during configuration restoration.
The extraction retains the existing configuration validation, error causes, JSON shapes and immediate freezing tests.

## Instance lifecycle boundary

`ApprovalInstanceStatus` is the closed enum used by the instance and operation DAL models.
The six representable statuses do not imply that all transitions have been implemented.
`ApprovalInstanceService`, `ApprovalInstanceStore`, `ApprovalInstanceWriteGuard` and `ApprovalInstanceCommandResult` are internal; they add no plugin runtime or arbitrary mutation contract.
Administrative suspension changes a running instance to suspended and requires `approval_flow_instance:manage` and a reason.
Start entry is an internal command for an existing running instance.
Start, approval entry, progression and end commands leave authorization to the actual entry point, preserve the original Context and record its actor in both audit and request hash.
They do not impose a SystemSource whitelist.
Start entry verifies the instance's bound release and records the start as completed and its single successor as waiting.
Approval entry takes an execution identity, verifies that the execution belongs to the running instance, and requires a frozen approval effect.
It stores pending task records for the fixed active assignees and moves the execution to awaiting approval.
Its policy type and exact version are frozen on that execution and resolve through `ApprovalPolicyRegistry`.
The configuration constructor rejects empty candidates and multiple candidates under single approval, including direct constructions by business compilers.
An internal personal decision command requires an Administration user with `approval_flow_task:decide`.
Under the instance lock, it checks the task's instance, assigned identity and the account's current active state before returning any receipt.
New decisions require a pending task and an execution awaiting approval.
The command records the vote and evaluates the frozen group policy.
An agreement completes single/any approval; all approval waits for all votes; a rejection vetoes every policy.
Completing a group records the execution outcome and cancels remaining pending tasks in the same transaction as its instance version and receipt.
The task's `completionCommandId` links both personal decisions and cancellations to the original actor and reason.
Internal `advanceApproval` requires a completed, unadvanced approval group whose own tasks are settled and whose scope generation is active.
It queues one unconditional successor from the verified release after approval, and commits `completed/rejected` directly after veto.
The committed `advanced` marker prevents another command from expanding the same execution twice.
Internal `enterEnd` accepts the waiting published end boundary at the root boundary and verifies its frozen completion effect before committing the execution and instance outcome.
Both instance and receipt DAL models retain the optional `ApprovalOutcome`, distinct from lifecycle status.
Receipt replay preserves the original outcome even when the instance has since completed.
These commands require an expected aggregate version and a command identity.
They preserve the original Context, serialize writes with the instance row lock and atomically retain the original response and audit in `approval_flow_operation`.
Personal decisions and suspension recheck their authorization before receipt replay; internal progression retains its entry-point authorization boundary.
Receipts are bound to their original actor and payload.

The DAL/Sync write guard rejects generic writes to instances, operations, executions, scopes, branches and tasks.
The controlled path updates instance status, outcome and version and inserts immutable receipts, node execution records and pending tasks.
Executions in `approval_flow_execution` retain their instance, node, incoming connection, sequence and status.
Each batch receives a one-use write permit which is consumed before ordinary DAL write-event listeners run.
Only its exact admitted command objects and payloads may pass validation, so event listeners and write callbacks cannot borrow the caller Context to create another write batch.
The original Context scope is restored and unused permits are removed when the write exits.
An internal business admission path creates draft instances and starts them through the original initiator; there is no activation endpoint; personal query and task decision routes are documented above.
The internal `approval_flow.progress` scheduled task scans completed approval groups and calls `progress` to dispatch its published successors through the same flow executor and controlled execution store.
It uses keyset pages of 100 and consumes successive completed groups within each instance, retries failed continuations on subsequent runs and preserves already committed personal votes.
Its runner and task services introduce no public PHP extension contract, HTTP route or additional service tag.
Deployments require normal scheduled-task registration, the task runner and Messenger worker.
Branch/join execution uses the public intents and scope snapshot described below; timers and external signals remain future work.
The personal decision service remains internal; its HTTP adapter grants no arbitrary business execution or payment authority.

## Terminal result boundary

The `approval_flow_result` DAL model stores one immutable result per completed instance, linked to the bound release and terminal command receipt.
Terminal commands append that result and pending delivery work atomically with the instance transition.
Generic DAL/Sync writes cannot create, update or delete result facts.
The raw `approval_flow_outbox` work table has no DAL model and is owned by the internal delivery adapter.

`ApprovalResultStore`, `ApprovalResultOutbox`, `ApprovalResultDelivery`, `ApprovalResultDispatcher` and `ApprovalResultHandlerRegistry` remain internal.
`ApprovalResult` is a supported immutable value containing the result ID, instance, release, command, aggregate version, outcome and UTC completion time.
Business modules implement `ApprovalResultHandler` and register it with `approval_flow.result_handler` in their own DI; static analysis enforces the tag's interface contract.
The exact type/version returned by the handler identifies its business contract and is matched against the request's frozen binding.
Duplicate complete identities fail, and a newer handler cannot replace a missing exact version.
`handle(ApprovalResult, ApprovalRequestEntity, Context)` receives the original request, including its business identity, submission round, business revision, binding and frozen read-only form.
The handler must atomically commit its business change with stable result-ID deduplication before returning; it must perform no network operation under its business lock.
The contract does not expose editable form revisions, remote consumer principals or retained artifact loading for result handlers.

System-only delivery claims use independent committed transactions, skip locked work and retain the result ID across bounded retries.
A live lease and matching attempt are required to acknowledge or reschedule work; old or expired workers cannot settle a new attempt.
The adapter retains failed deliveries without changing the completed approval instance.
The internal `approval_flow.deliver_results` scheduled task invokes the dispatcher once per minute with a limit of 100 claimed deliveries.
No matching request or exact handler means a retry, never an acknowledgement without a consumer.
The dispatcher acknowledges only after the business handler returns; failed or expired confirmations do not count as delivered.
Consumers must deduplicate by the result ID because the delivery contract is at least once.
Payment supplies a real handler that saves an immutable decision for the original transfer request, with its revision check and deduplication under the transfer lock.
The decision authorizes a separate payout action.
Payment's existing submission preflight and atomic attempt claim now require this applied approval for transfers with an Approval Flow submission.
The internal `PaymentTransferApprovalResultVerifier` compares the current business snapshot with the original revision and the outgoing transfer inputs under the transfer lock.
Missing, pending, rejected or changed approval evidence blocks execution and rolls back the claim.
No public submission route, new extension interface or automatic payout trigger is introduced.

## Internal business binding management

`ApprovalBindingService`, `ApprovalBindingStore` and `ApprovalBindingWriteGuard` are internal while real business submission is developed.
The two DAL models use the normal Definition/Entity/Collection contracts; repository visibility does not authorize bypassing controlled writes.
No new route, loader interface, adapter interface or service tag is introduced.

Saving a revision freezes exact business identity/version, authored form bytes and the chosen release.
Activation rechecks that declaration and release and updates the per-type active revision using an expected activation version.
Form matching checks the graph's form reference, not an independent form version/hash inside the release; that release contract does not exist yet.
An active binding is a saved selection, not proof of business permission, current runtime readiness, validated form data or successful result delivery.

Draft, release and binding services leave transport ACL to their actual entry points and retain normal DAL authorization for entity writes.
The binding head is an internal coordination row owned by the store; activation is not a public API or a DAL head-update permission.
They do not classify Context sources for ordinary configuration reads or writes.
Personal task decisions, task ownership and management operations keep their existing business authorization.
Internal progression leaves authorization to its actual entry point; result delivery retains its existing system-only lease boundary.
The binding and instance write guards use explicit WeakMap methods so object-key handling does not depend on an editor recognizing array-access syntax.

## Binding validation event

`ApprovalBindingValidationEvent` is a supported synchronous extension point for business-owned configuration rules.
The internal binding service dispatches it on save and activation, after the complete release, start business identity and form reference have been verified.
`businessType` is the exact immutable declaration, `graphJson` is the verified graph string, and `getContext()` returns the caller's original context.
Listeners reject by throwing their business exception; they do not replace the graph, mutate business state or perform network calls.
The event runs within the binding transaction and same-type operation guard; exceptions roll back the attempted configuration change.
The event does not authorize submitting a request or executing its business result.

Payment registers its transfer declaration and validation subscriber in its own DI.
Its current financial policy admits standard core approvals and advance-only extensions, and rejects custom request-approval nodes whose compiled review rules cannot yet be inspected through this event.
The shared binding service has no Payment dependency or financial rule.
`PaymentTransferApprovalBindingSubscriber` is internal to Payment and is not a plugin inheritance contract.

## Internal request admission

The `approval_flow_request` DAL model uses normal Definition/Entity/Collection contracts and retains the business identity, submission round and revision, selected immutable binding, instance, initiator and raw form JSON.
Generic DAL and Sync writes are rejected; the request service owns creation together with a draft instance.
Its dedicated write scope retains the original Context and DAL creation permissions.
There is no public submission route or business adapter interface.
Result consumer registration is described under terminal results below.

`ApprovalRequestService`, `ApprovalRequestStore` and the binding service's submission selection remain internal.
They accept trusted business-generated data; callers own resource authorization, serialization of their business identity and atomic evidence freezing.
`find(..., Context)` is an initiator-bound submission retry lookup and is not a general request query contract.
`start(requestId, Context)` verifies the original initiator and uses the immutable request ID as its single draft-to-running command identity.
The existing instance transaction saves initial ready/scoped executions, their first reviewer tasks, version 2 and its receipt atomically.
Retries replay that receipt without entering nodes again; implicit splits, invalid scope topology or completion before approval fail without partial writes.
Same-instance command recursion from DAL events is rejected before a second transaction starts.
These internal operations do not add a public command or extension contract.
The shared skeleton has no Payment dependency.

Payment registers its private evidence entity and submission services in Payment DI.
Private snapshot fields are not API-visible, and the existing Payment write protection rejects their mutation or deletion.
`PaymentTransferApprovalRequestService` is an internal integration entry point that admits and starts an approval in one business transaction; it does not grant payment authority.
The submission store locks the transfer through approval start and rejects same-connection reentry.

## Flow definition reads and design operations

Definition identities, current drafts and immutable publication history use the standard DAL Admin API.
`approval_flow_definition.draft` is the normal one-to-one association to the current draft, whose primary key is `definitionId`.
The Administration reads definitions with `repositoryFactory`, `Criteria` and that association, and filters publication history by `definitionId`.
List and history queries use response includes to omit graph payloads, executable plans and deployment evidence.
The `approval_flow_definition.viewer` mapping grants `approval_flow_definition:read`, `approval_flow_draft:read` and `approval_flow_release:read` without granting mutation, publication or activation authority.
Existing write guards continue to require controlled draft and publication operations.

`GET /api/_action/approval-flow/design/catalog` projects exact node and business declarations from the existing tagged registries, including authored schemas and hashes.
Catalog entries describe declarations; they do not certify compiler, executor or deployment artifact availability.
`POST /api/_action/approval-flow/design/diagnose` accepts `graphJson` and returns graph diagnostics without persisting changes.
Both special operations require `approval_flow_definition:read`.
The OpenAPI contract is maintained in `AdminApi/paths/approval-flow-design.json`.
`ApprovalDesignCatalog` and `ApprovalDesignController` are internal implementations.

The list and publication history reuse the installed Meteor `mt-data-table`, including its native search, pagination, loading, empty and reload behavior.
Missing exact extension declarations remain visible without discarding authored configuration or substituting another version.
The current overview does not yet provide a visual editor, publication controls or business activation controls.

## Approval policies and routing evidence

`ApprovalPolicyHandler` is the supported implementation contract for strategies registered with `approval_flow.approval_policy`.
The concrete Registry and decision evaluator remain internal.
Strategies declare a namespaced type and exact version; duplicate identities fail without exposing a partial registry.
The selected policy identity and executable artifact are frozen in the release; a new decision verifies that release before invoking the current implementation.
The v1 core approval contract requires rejection veto, and an incompatible strategy result fails the entire command.

`NodeExecutor::enter()` accepts the optional public `NodeExecutionContext`, whose form JSON is runtime-provided immutable submission evidence.
`AdvanceEffect::outputPorts` selects declared outputs; null retains unambiguous single-connection routing.
Empty, duplicate, input-only and unknown port selections fail.
Ordinary advance admits one selected outgoing connection, including conditional paths that later reconverge; explicit `OpenScopeEffect` provides scoped concurrent activation.
Implementations must accept the context argument; the development baseline does not retain a second legacy executor contract.

## Lifecycle and action boundaries

`ApprovalOutcome` currently represents the approved/rejected verdict used by personal decisions, group evaluation and the final approval result.
Transfer, return, add-sign, withdrawal and timeout recovery require their own command, task-plan and lifecycle semantics; they are not additional verdicts.

Group policy aggregation and task activation order are separate responsibilities; the current policy seam does not implement sequential activation.

The current runtime implements serial progression, conditional selection and scoped branch/join coordination.
Child-process waiting, external/timer signals, dynamic task-plan revisions and generation reset remain future work.
`Withdrawn` and `Terminated` are currently enum values without corresponding runtime commands.
Result recording currently follows a verdict, so a future withdrawal or termination command must also define its closing fact, pending-work cancellation and business delivery contract.
Previously delivered verdicts remain immutable; later business reversal requires an explicitly related process or compensation fact.

### Returning an approval task

Use `GET /api/_action/approval-flow/instances/{instanceId}/tasks/{taskId}/return-targets` to find the previously approved reviews that the task can reopen.
Submit the selected `targetExecutionId` with `commandId`, `expectedVersion` and a return reason to the task's `POST .../return` action.
The active assignee can reopen an earlier review on the active path in the same branch and scope generation.
The operation preserves recorded opinions, cancels remaining tasks in the current review and opens fresh tasks using the instance's published release.
Request detail exposes the committed return in `returns`.

A Symfony event subscriber can handle `ApprovalReturnTargetsEvent` and call `restrictTo()` with the allowed execution IDs.
The event runs for target listing and for the locked command, so listeners must apply the same rules without writing business state.
Each listener can remove candidates from the current list.

## Scope execution intents and snapshots

`OpenScopeEffect` and `WaitEffect` are supported public node intents alongside advance, approval request and completion.
Node declarations must permit their corresponding `NodeEffectType` values before an executor can return them.
`OpenScopeEffect` supplies the selected published output ports and one return node; all selected paths must return within the enclosing region.
`WaitEffect` retains the kernel-created scope arrival subscription; it cannot create an arbitrary timer or external callback.

`NodeExecutionContext::scope` contains the supported readonly `ApprovalScopeSnapshot`, with scope ID, generation and actual branch ID/status pairs.
The exact-version join executor owns its decision to wait or advance from these facts.
Returning advance closes the child scope, cancels unfinished descendant work and continues in the opening execution’s enclosing branch.
Closed/generation-mismatched scope work cannot vote or dispatch again; completed opinions survive cancellation.
The singleton waiting execution is created with the opening command, rather than with the first arrival, so older transaction snapshots can wake it through DAL safely.

`ApprovalExecutionScopeDefinition/Entity/Collection` and `ApprovalExecutionBranchDefinition/Entity/Collection` are normal supported DAL models.
`ApprovalFlowExecutor` is the internal ready-work coordinator; it interprets intents without depending on DBAL or DAL.
`ApprovalExecutionStore` owns locked runtime facts and controlled persistence, bound to the existing instance-command transaction and one-use write scope.
The store does not load the graph or invoke node executors.
Both run inside `ApprovalInstanceStore::runCommand()`, which owns the instance lock, legal state, expected version, history, receipt and terminal outbox transaction.
These internal services are not plugin inheritance or direct-write extension contracts.
`approval_flow_execution` stores `scopeId`, `branchId`, `generation`, `openedScopeId`, `waitingScopeId` and `advanced`.
The task `completionReason` is an open namespaced string with core constants in `ApprovalTaskCompletionReason`, separate from status, opinion and completion command.

Node and policy IDs stay open registry identities; kernel `NodeEffectType` and shared verdict `ApprovalOutcome` remain controlled enums.
A custom fork or join rule composes published kernel primitives; it does not gain permission to modify runtime DAL directly.
No new HTTP operation, Administration designer or stock parallel node declaration is introduced by this runtime increment.
