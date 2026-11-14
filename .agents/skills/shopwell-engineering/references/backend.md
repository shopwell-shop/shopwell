# Backend ownership and extension contracts

Use `shopwell-php-code` for implementation and `shopwell-phpunit-tests` for tests.
Read the owning subtree's instructions before applying an example from another domain.

## Find the owner before adding layers

Core separates Framework infrastructure, Content domains, Checkout domains and System capabilities.
A domain integration belongs to that domain even when it plugs into a shared framework.
ContentSystem explicitly registers domain-owned services in the domain's DI rather than its central framework configuration.
Read `src/Core/Framework/ContentSystem/AGENTS.md`, its principle index and domain-placement documentation when touching it.

Useful implementations to read by question:

| Question | Source and decision to inspect |
| --- | --- |
| How are extension phases separated? | `src/Core/Checkout/Cart/Processor.php`: collect required facts before ordered calculation; original and calculated cart have distinct roles. |
| Who owns a pure transformation? | `src/Core/Framework/ContentSystem/Mutation/MutationPipeline.php`: mutate, seed the appropriate new elements, diagnose, derive wiring, then ensure diagnostics describe the returned tree. |
| Who owns the authoritative write? | `src/Core/Framework/ContentSystem/Mutation/PersistedLayoutMutator.php`: lock, reload, compare version, perform mutation and persist through DAL. |
| How is a large query assembled? | `src/Core/Content/Product/SalesChannel/Listing/ProductListingLoader.php`: resolve IDs/aggregations and load entities in batches with their required associations. |
| How should an adapter orchestrate work? | `src/Core/Framework/ContentSystem/Api/ContentLayoutMutationController.php`: transport validation, explicit ACL, typed operation and delegation to the write owner. |

These examples justify their own phases and collaborators.
They do not require every feature to introduce a pipeline, a planner, an adapter and a DTO.
Controllers, CLI commands and subscribers translate infrastructure input; services express domain decisions through appropriately narrow collaborators.
Check that an extraction reduces knowledge in its callers rather than merely forwarding the same arguments through more files.

For cart behavior, trace `src/Core/Checkout/Cart/CartDataCollectorInterface.php`, `CartProcessorInterface.php` and `src/Core/Checkout/DependencyInjection/cart.php` before choosing a generic page/event hook.
The real `shopwell.cart.collector` and `shopwell.cart.processor` collections and their priorities determine calculation timing.
Use existing price calculators under `src/Core/Checkout/Cart/Price` for the applicable tax/rounding contract.
Read `src/Core/Checkout/Cart/CartRuleLoader.php` when a new calculated value affects rule eligibility; recalculation can re-evaluate rules, so self-referential thresholds require an explicit policy and behavior test.

## DAL is the persistence model

Use `EntityDefinition`, fields/flags, `Entity`, typed `EntityCollection`, `EntityRepository` and `Criteria` for normal entity operations.
Read `src/Core/Checkout/DocumentV2/DocumentDefinition.php` for how foreign keys, reference versions, associations and API exposure form one contract.
A property in an Entity does not by itself define storage, write admission or API visibility.
Do not introduce Doctrine ORM entities/repositories or a generic QueryBuilder in business services.

Some infrastructure has a real need for specialized DBAL operations.
ProductListingLoader's preview projection explicitly preserves entity/version semantics; locking, indexing and migrations have similarly specific needs.
Those exceptions do not make direct SQL a general alternative to DAL.
If the required guarantee cannot be expressed through the established DAL path, isolate that operation and retain the context/version/transaction semantics it replaces.
Batch related reads and writes, request only needed associations and counts, and inspect inheritance/language/version behavior before optimizing.

Keep migrations in the owning package and follow `coding-guidelines/core/database-migations.md`.
Use real creation timestamps and meaningful migration tests.
For JSON maps and lists, read `coding-guidelines/core/empty-json-maps.md`; empty PHP arrays can otherwise change the wire shape.
Do not add compatibility scaffolding for an unreleased contract without an actual compatibility requirement.
Do not generalize a subtree's development-baseline instruction into permission to remove another area's supported public API.

## Context, authorization and concurrency are part of behavior

`src/Core/Checkout/Order/Aggregate/OrderTransaction/OrderTransactionStateHandler.php` forwards the supplied `Context` into state transitions.
Its source, permissions, language and version are part of the operation and its events/history.
Do not construct a default/system Context to bypass a failing user operation.
When privileged internal writes are legitimate, use a narrow supported scope after business authorization and retain the original actor.
`src/Core/Framework/Context.php::scope()` restores scope/state in `finally`.

Place load/check/write guarantees at the authoritative mutation owner.
An expected-version value alone does not serialize two concurrent writes.
PersistedLayoutMutator demonstrates a resource lock and storage-precision version comparison; use the actual feature's transaction/locking model rather than copying that lock universally.

Read `src/Core/Framework/DataAbstractionLayer/Doctrine/RetryableTransaction.php` before putting work in a retry closure.
The closure and commit can fail, and the closure can run again.
Account for external side effects, in-memory state and event delivery at that boundary.
An in-memory deferred queue, after-commit delivery, idempotency and a durable outbox provide different guarantees.
Choose the required guarantee and test it; do not add all mechanisms speculatively.

## Extend through a contract whose semantics are stated

Prefer an existing event or typed extension event when its timing supports the customization.
`src/Core/Framework/Extensions/ExtensionDispatcher.php` defines pre/error/post behavior, replacement results and error propagation.
`src/Core/Content/Product/Extension/ResolveListingExtension.php` shows a typed result and explicit criteria/context input.
For new Store API routes, read `adr/2026-09-24-replace-abstract-route-classes-with-extension-events.md` rather than copying an old abstract route/decorator pattern.
Existing supported abstract/decorator contracts remain subject to their compatibility rules.

Choose a registry's duplicate and priority policy from its purpose:

- `DocumentV2/Renderer/DocumentRendererRegistry.php` takes the first renderer per format according to tagged priority, enabling replacement.
- `DocumentV2/Provider/DocumentDataProviderRegistry.php` composes matching providers and rejects colliding output keys.
- `ContentSystem/Layout/Type/Registry/ContentSystemElementTypeRegistry.php` rejects ambiguous type identities.

The paths above are below `src/Core/Checkout/` for DocumentV2 and `src/Core/Framework/` for ContentSystem.
Do not prescribe universal first-wins, last-wins or duplicate-error behavior.
Register the implementation in the real DI collection and prove its consumer discovers it.
`DocumentV2/Type/DocumentTypeRegistry.php` also demonstrates lazy cache reset through `ResetInterface`; a long-lived cache needs invalidation ownership.

Separate supported HTTP/DAL/event contracts from PHP implementation support.
An internal controller can expose a public route; that does not make the controller a supported inheritance API.
Mark and document public surfaces deliberately, following `internal.md`, `final-and-internal.md` and the PHP skill.
Add matching Admin/Store OpenAPI paths and run the route-schema test when adding or changing core API routes.

## Preserve the meaning and cause of failures

Follow `coding-guidelines/core/domain-exceptions.md`.
`src/Core/Checkout/DocumentV2/DocumentV2Exception.php` demonstrates stable codes, relevant identifiers and previous exceptions for wrapped failures.
Use enough field/domain context for a caller to act without logging sensitive payloads.
Recover from the outcomes the contract declares recoverable; do not turn every exception into an empty list or a success response.
An unfinished draft, absent entity, invalid request and corrupted stored value can legitimately require different outcomes.

## Async work, apps and search

Read the actual task/handler/registration when adding scheduled work.
`src/Core/Framework/MessageQueue/ScheduledTask/ScheduledTaskExecutor.php` owns task state, clock use, failure and rescheduling; a handler owns its domain operation.
Do not copy its legacy fallback branch into a new feature.
Messages should carry serializable identity/state needed by their handlers; evaluate replay, context and failure handling at the consuming boundary.

An app integrates through manifests, APIs, webhooks and script hooks; a PHP plugin executes in process.
`src/Core/Framework/App/Lifecycle/Handler/WebhookLifecycleHandler.php` illustrates named reconciliation, target validation and integration-owned cleanup.
It is an app-system implementation, not a plugin-facing service to call directly.
For webhook changes also inspect the current delivery/target-validation paths; lifecycle inspection alone does not establish delivery safety.

Elasticsearch adapts DAL search rather than owning business authorization.
Read `src/Elasticsearch/Framework/DataAbstractionLayer/ElasticsearchEntitySearcher.php`, `ElasticsearchHelper.php` and `src/Elasticsearch/DependencyInjection/services.php` for eligibility, fallback and tags.
Read `src/Elasticsearch/Product/ElasticsearchProductDefinition.php` with the indexer when changing mappings: mapping, fetched document values and query semantics must agree.
`src/Elasticsearch/Framework/Indexing/ElasticsearchIndexer.php` checks individual bulk item failures; an HTTP-successful bulk request is not proof that every item succeeded.
