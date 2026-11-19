# Approval Flow public surface

The supported extension surface covers node declaration, configuration compilation, pure node entry and assembly.
The DAL models `approval_flow_definition`, `approval_flow_draft` and `approval_flow_release` provide typed Definition, Entity and Collection classes using the host's normal repository contracts.
They carry neither `@internal` nor `@final`; this does not authorize direct writes around the controlled draft path.
No HTTP action routes, execution commands or supported PHP publication services are exposed by this increment.
The diagnostic commands `approval-flow:node-types` and `approval-flow:graph:diagnose <file>` inspect declarations and draft graphs without publishing or executing a process.

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
- `enter()` returns the closed union `AdvanceEffect|RequestApprovalEffect|CompleteEffect`, without infrastructure calls or storage changes.
- `ApprovalNodeConfiguration` and `ApprovalFieldConfiguration` are supported immutable values for approval rules and candidates carried by `RequestApprovalEffect`.
- `ApprovalOutcome` fixes approved/rejected process outcomes at the PHP type boundary; `CompleteEffect` accepts this enum rather than an arbitrary string.

A plugin may implement its own node compiler, configuration and executor and return one of the controlled intents without importing internal framework types.
The current configuration classes identify the v1 core contract; future incompatible versions must retain their own configuration type and exact services.
Executors are pure entry services, not authorization or persistence extension points.
Effects are uncommitted proposals: the future runtime must supply execution identity, resolve candidates, revalidate transitions and validate the whole effect set before any writes.
The internal entry path restores compiler-produced configurations from verified persisted releases; no supported runtime decoder or instance-execution endpoint exists yet.

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
Persistence integration tests cover DAL mapping, permissions, source/scope preservation, revision conflicts, removal confirmation, complete rollback, direct-write rejection and plugin loss/restoration.
An independent writer process establishes an older read snapshot, proves real row contention through a lock timeout and then receives a revision conflict after the first writer commits.
Publication preflight tests cover complete graph admission, blocked incomplete graphs, canonical hashing, exact used declaration capture, schema byte drift and business-owned tagged loader assembly.
Integration tests prove preparation leaves saved draft bytes and revisions unchanged, and later draft edits cannot alter an earlier snapshot.
Compiler tests cover graph and snapshot bindings, versioned wire shape, branch/merge topology, parallel connections, numeric/special IDs and independent business declarations through actual DI.
Integration tests prove graph plan compilation leaves stored draft bytes/revisions unchanged on success and failure.
The exact executor availability gate is covered by real container removal and plugin registration tests.
Deployment attestation and retained byte checks now cover compilation records; historical executable loading, remaining activation gates, runtime behavior and transfer execution require future evidence.

Node configuration tests cover real tagged business compilers, exact versions and missing registrations, semantic rules, deterministic byte freezing, mutable plugin results and rejected compiler outputs.
Database integration verifies that success and semantic failure leave saved draft bytes and revisions unchanged.

Node entry tests cover typed configuration mismatch, exact version coexistence, duplicate/invalid registration failures and the three core intents.
Kernel integration composes actual registered compilers and executors and verifies saved draft bytes and revision remain unchanged.

## Internal compilation publication

`ArtifactManifestLoader`, its versioned deployment manifest schema, `ApprovalReleaseCompiler` and `CompiledApprovalRelease` remain internal.
`ApprovalReleaseService`, `ApprovalReleaseStore`, `PublishedApprovalRelease` and `ApprovalReleaseWriteGuard` also remain internal while their controlled publication contract is exercised.
The release DAL model follows the normal supported Definition/Entity/Collection contracts; repository access does not authorize bypassing controlled insertion or changing existing content.
Publication requires the deployment-owned `APPROVAL_FLOW_ARTIFACT_MANIFEST` setting and freezes exact used-service bindings plus their retained artifact dependency closure.
Opaque artifact versions can coexist; duplicate complete identities and duplicate service bindings fail.
Deployment attests origins and closure completeness; SHA-256 verification covers retained bytes and does not establish loaded-code provenance, historical loading or worker readiness.
These records grant no activation authority, and there is no activation or instance-execution API.

Artifact tests cover transitive closure, deterministic ordering, multiple retained versions, missing files/bindings/dependencies, changed bytes, cycles and conflicting declarations.
Compiler tests use real core and tagged business services, and distinguish graph or artifact hash changes from formatting-only edits.
Release integration tests cover immutable content, preview conflicts, privilege-first access, Administration actor/context preservation, DAL/Sync write rejection, transaction rollback and later draft edits.
A separate publisher process proves contention and reuse after an older read snapshot; removing the deduplication current read makes that test fail.
The release migration test creates an isolated database and verifies repeated execution preserves records, the unique key and foreign-key deletion policies.

## Internal verified node entry

`NodeConfigurationCompiler`, `ApprovalReleaseLoader` and `ApprovalNodeEntry` remain internal orchestration services.
Plugins continue to implement `NodeCompiler`, `NodeConfiguration` and `NodeExecutor`; no additional decoder interface or service tag is required.
`CompiledApprovalRelease` exposes the complete frozen documents and hash directly as readonly strings and does not expose intermediate compiler-stage objects.
Its storage wire format and hash derivation remain unchanged.

Loading a persisted release requires release read privileges and exact agreement with a fresh compilation of its saved graph, all frozen documents, current deployment bindings and full content hash.
Only the selected node's typed configuration is restored, verified against its frozen output and immediately handed to its exact executor.
No configuration collection escapes for later mutation, and no caller-supplied object grants execution authority.
The returned effect remains uncommitted and cannot authorize instance state or business side effects.
Whole-release verification runs for each call without a cache; performance and command-scoped admission will need verification with the future runtime.

Integration tests cover every changed frozen document, changed deployment bytes, permission-first reads, missing releases and independence from later draft edits.
Actual DI assemblies exercise core and independently tagged business nodes, missing exact executor versions, missing artifact bindings, whole-release failure before entry and compiler drift during configuration restoration.
The extraction retains the existing configuration validation, error causes, JSON shapes and immediate freezing tests.
