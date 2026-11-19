# Approval Flow public surface

The supported extension surface currently covers node declaration and assembly.
No API routes, DAL entities, execution commands or publication services are exposed by this increment.
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

## Internal adapters

`YamlNodeTypeLoader`, `NodeTypeSourceDirectory` and framework DI are internal adapters.
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
Publication gates, executor pins, durable concurrency, runtime behavior and transfer execution require their own future evidence.
