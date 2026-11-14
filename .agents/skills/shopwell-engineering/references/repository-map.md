# Repository map, evidence and verification

This reference locates responsibilities and current checks for platform and plugin work.
It is a navigation map, not an assertion that every file in every listed area was read or audited.
The initial source study was performed on 2026-11-14 in the working checkout based on `9cdd77b010eb4699449a996829d161b795656411`.
That checkout contained substantial uncommitted work, so the commit alone does not reproduce every studied file.
Re-read the target source and current rules when applying the skill after changes.

## Repository responsibilities

| Area | Responsibility and entry points |
| --- | --- |
| `src/Core/Framework` | Platform facilities including DAL, routing/API, context, extensions/events, validation, plugin/app systems, ContentSystem and background work; read its subsystem's own documentation. |
| `src/Core/Content` | Domain-owned catalogue/content/automation behavior; Product listing and Flow dispatch show integration with platform contracts. |
| `src/Core/Checkout` | Customer/cart/order/payment/document business operations; Cart and DocumentV2 supply contrasting phase/extension examples. |
| `src/Core/System` | Shared business capabilities such as currency, language, sales channels and state machines; preserve Context and domain ownership. |
| `src/Administration` | Admin backend shell and Vue/TS application; distinguish `core`, `app`, modules and build/extension tooling. |
| `src/Storefront` | Customer HTTP/page composition, Twig components, browser code, themes and Storefront-specific ContentSystem concerns. |
| `src/Elasticsearch` | DAL/search/indexing adapters and separate product/Admin search implementations. |
| `custom/plugins`, `custom/apps`, `custom/static-plugins` | Extension-owned implementation; use the actual package/manifest/deployment contract. |
| `src/Core/Migration` and package/plugin migrations | Ordered schema/data changes under their owner; inspect local baseline/version conventions. |
| `src/Core/DevOps`, `bin`, build configuration | Developer tooling and enforced conventions; read actual commands/configuration rather than guessing runner arguments. |
| `src/Core/Installer`, `Maintenance`, `Service`, `Profiling` | Specialized installation, maintenance, service integration and instrumentation boundaries; read their entry point and tests for work in that area. |
| `tests/unit`, `tests/integration`, `tests/migration` | Pure/isolated behavior, real platform boundaries and migration guarantees respectively. |
| `tests/acceptance`, `tests/performance`, `tests/devops` | Browser behavior, workload-sensitive measurements and tooling/static-analysis enforcement. |
| `.github` | Shared CI/release/agentic automation; read `.github/AGENTS.md` before changes. |
| `coding-guidelines`, `adr`, technical docs | Normative engineering policy, decisions with tradeoffs and subsystem/extension contracts. |

## What the initial study established

The backend study traced representative Cart calculation, DAL Product listing, Context/state-machine forwarding, DocumentV2 registries, ContentSystem mutation/persistence/diagnostics and typed extension dispatch.
It read real implementations and selected tests rather than deriving conventions from directory names.
The frontend study covered canonical small Admin component/composable/store examples and their specs, standard currency/rule/flow pages, Experience Studio editor/state/layout, native authoring, virtual modules and extension tooling.
The Storefront study covered product/guest-return page flow, Slot and Button composition, modern and legacy JS lifecycle, styles/theme compilation, plugin lifecycle and search adapter/indexing code.
The shared study read current coding guidelines, manifests, test/build scripts, source inventories and CI instructions.

Those are representative cross-repository traces, not a line-by-line review of the repository.
Installer, maintenance, profiling, full app scripting/webhook delivery, every business domain, every plugin and the performance/CI implementations were not exhaustively inspected.
For work there, trace the actual entry point, owner, extension consumer and test before making claims or writing implementation.
A source map is not evidence that an unfamiliar subsystem has already been learned in depth.

## Apply the skill on a real task

For a substantial feature or refactor, keep a short evidence note in the task:

- The current rule and one or two relevant implementation/test paths actually read.
- The selected layer/owner and why an existing capability does or does not fit.
- The input/output, state lifecycle and failure behavior at changed boundaries.
- The supported plugin contract and any experimental/internal limitation relevant to the task.
- The verification performed, its scope and any gap.

This note need not become a new project document.
Do not require a repository-wide audit before a small local change.
For an explicitly exhaustive audit, inventory scope by dependencies and integration call sites as well as filenames, and record reviewed/partial/unreviewed status per file.
Do not report search coverage as semantic review.

## Verification selection

Follow root `AGENTS.md`, the relevant domain skill and current `composer.json`/package scripts.
These commands are starting points; inspect their argument forwarding and file globs before choosing a targeted invocation.
They are not a claim that the initial source study executed them.

| Changed contract | Relevant checks |
| --- | --- |
| PHP implementation | `composer cs`, `composer phpstan` and behavior tests at the owning layer, following `shopwell-phpunit-tests`. |
| DAL/API/permissions | Real mapping/write/ACL integration tests; new or changed core routes also need OpenAPI paths and `tests/integration/Core/Framework/ApiRoutesHaveASchemaTest.php`. |
| Migration | Meaningful `tests/migration` coverage and the repository migration guidance; test actual schema/data effects. |
| Admin behavior | Adjacent Jest `.spec.ts` tests via `composer admin:unit` or an appropriate focused invocation; `composer eslint:admin`, `composer format:admin`, type checks and `composer fallow:admin` as applicable. |
| Admin SCSS | `composer stylelint:admin`; rendered inspection for actual spacing, wrapping, sizing, focus and responsive behavior. |
| Admin extension manifests | Relevant block/position/module generation plus metadata checks when those contracts change. |
| Plugin Administration | `src/Administration/Resources/app/administration/extension-tooling/README.md`: setup drift check and targeted extension checks; verify no required extension was skipped. |
| Legacy Storefront JS | `composer storefront:unit` and `composer eslint:storefront`. |
| Modern Storefront components | `composer storefront:components:unit` uses Vitest; check the component project includes the changed files. |
| Storefront SCSS/Twig | `composer stylelint:storefront`, `composer ludtwig:storefront` and rendered checks when needed. |
| Snippets | `composer translation:lint`; use `en.json`/`zh.json` and the corresponding Storefront filenames. |
| Browser integration | Existing Playwright acceptance fixtures/page objects; prove real user behavior rather than implementation internals. |
| CI/tooling | `.github/AGENTS.md` and `coding-guidelines/core/ci-workflows.md`; a job must prove the work ran before success. |

Plugin files may live outside default root lint/test globs.
Use the host extension tooling and plugin's actual test/build setup to cover them; report skipped or undiscovered files honestly.
Likewise verify that style commands include component SCSS under `src/Storefront/Resources/views/components`, not only legacy `src/Storefront/Resources/app/storefront/src/scss`.
Do not install a separate framework/test stack merely to avoid the host setup.
Behavior changes require meaningful tests; style/snippet/docs-only changes follow their scoped lint and validation rules.
Do not add a PHP integration test just to render Twig.

Read `coding-guidelines/administration/testing.md` for async/state cleanup and behavior-focused suite structure.
Read `coding-guidelines/core/unit-tests.md` and the PHPUnit skill for fixtures, coverage and isolation.
For concrete assertion quality, inspect `tests/unit/Core/Checkout/DocumentV2/Renderer/DocumentRendererRegistryTest.php` for extension priority, and `tests/unit/Core/Framework/ContentSystem/Mutation/PersistedLayoutMutatorTest.php` for the actual write payload, returned diagnostics and propagated rejection.
For retry/concurrency changes, make the relevant interleaving/failure observable; a sequential happy path does not prove a race fixed.
Prefer deterministic events, controllable clocks and explicit promises to unexplained sleeps.
For visual/interaction changes, inspect the built, current UI with realistic labels and permissions; passing unit tests does not establish layout quality.

## Keep documentation proportional and accurate

Use `shopwell-release-docs` for changed developer-facing API, extension, feature or configuration behavior.
Use `shopwell-pr-hygiene` only when preparing a PR.
Maintain supported compatibility contracts deliberately; do not invent migrations, shims or deprecations for hypothetical installations.
Do not weaken an invariant, suppress a new lint error or grow a baseline merely to make checks green.
Report static inspection, executed verification and limitations separately.
Do not label work “senior-quality” solely because it follows this skill; the changed behavior, source readability and contract evidence must support the conclusion.
