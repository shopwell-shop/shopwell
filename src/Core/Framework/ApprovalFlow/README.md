# Approval Flow

Approval Flow is an independent approval module in the existing Core host.
Its first integration is payment transfer approval.
The current increment establishes node declarations, exact version lookup and service assembly.
It does not yet publish or execute workflows.

## Skeleton responsibilities

The skeleton defines where a capability belongs, how it is declared and discovered, how the host calls it and which boundary validates its output.
A list of approval features or empty interfaces does not establish that structure.
Use ContentSystem's responsibility boundaries, naming and typed assembly as the backend reference; use Experience Studio's module, page, store and lifecycle boundaries for the future Administration client.
Keep business implementations and their DI in the owning business module.

- `Graph/Type/Specification` owns validated node identity, ports and the exact configuration schema artifact.
- `Graph/Type/Loader` translates declarations into specifications and retains their source.
- `Graph/Type/Registry` combines loaders, rejects duplicate exact versions and never substitutes a newer version.
- `Framework/DependencyInjection/approval-flow.php` assembles framework services through the existing host.

A loaded declaration does not establish executor availability, publication validity or authorization.
The schemas in `Resources/node-types` describe the initial configuration subset; draft codecs, executable gates and the full capability manifest remain separate implementation increments.
There are no published releases using these schemas yet.

## Registering another node source

Implement `NodeTypeLoader`, or register a separate `YamlNodeTypeLoader` with the business or plugin's source directories.
Tag that service with `approval_flow.node_type_loader` in its own DI configuration.
The core Registry discovers it through the tagged iterator without importing the business service.
Run `bin/console approval-flow:node-types` to load and inspect the actual assembled catalogue; duplicate or malformed declarations fail the command.
The wiring test compiles the actual framework configuration with an independently registered finance declaration.

The YAML loader accepts `type`, `version`, `labelSnippet`, `configurationSchema` and `ports` only.
`configurationSchema` names a JSON file beside the YAML declaration; the schema must declare an object and a nonempty `$id`.
The loader reads declarations at the directory's top level in filename order.
Automatic module directory discovery is not implemented by this increment.
The public and internal boundaries are listed in [public-surface](docs/public-surface.md).

## Implementation order

1. Validate declarations and real typed assembly with duplicate, missing and version coexistence cases.
2. Add the authoritative graph codec, pure mutation and diagnostics with shared schema fixtures.
3. Add controlled persistence and publication gates, then a minimal start → approval → end execution path.
4. Prove new nodes, operations and transfer integration through the public seams before expanding complex execution semantics.

Read the closest implementation and adjacent tests before each increment, and review naming, responsibility, failures and dependency direction alongside tests and lint.
Keep the new Approval menu and its pages deferred until their real capabilities are available.
The legacy workflow menu is hidden through the existing menu visibility event; removing its code belongs to the completed replacement stage.

## Source calibration

- ContentSystem `README.md`, `NAMING.md` and role suffix rules: responsibility and ownership.
- `ContentSystemElementTypeRegistry` and its tests: loader aggregation and duplicate source reporting.
- `YamlTypeLoader`: declaration loading and source tracking, without introducing its decorator hierarchy.
- ContentSystem DI and Registry tests: actual tagged consumers and business-owned registrations.
- `VisibleMenuTreeBuilder` and its tests: runtime visibility for stored, customized and synthesized menu entries.

The new graph and execution model must not depend on ContentSystem or the legacy workflow implementation.
