# Administration architecture, UI and plugin integration

Use `shopwell-admin-js` and the Administration's nested instructions.
Read `coding-guidelines/administration/architecture.md` and `testing.md` for the current rules.
Unless fully qualified, paths below are relative to `src/Administration/Resources/app/administration/`.

## Keep layers and state ownership explicit

Dependency direction is Module → App → Core.
Core is Vue-independent and can run in the worker; App owns shared Vue facilities; modules own feature UI and orchestration.
Do not directly import another module's private implementation.
Use registered services, repositories, routes, stores or an intentionally shared app/core contract.
Do not promote a feature utility to Core just to avoid assigning its owner.

Read these current small references with their adjacent specs:

- `src/app/component/media/sw-media-folder-thumbnail/index.ts`: limited props, validation, focused computed output and a reason for its runtime color property.
- `src/app/composables/use-theme.ts`: typed shared behavior and an explicitly application-wide detached scope.
- `src/app/store/tabs.store.ts`: stable registration identity, upsert semantics and observable unknown-target behavior.

A page typically owns loading, persistence, dirty-state decisions and navigation.
A child should receive its rendering inputs and emit semantic intent to that owner.
Keep component-local state local; choose Pinia for an actual shared/lifecycle contract.
Use `storeToRefs` when destructuring reactive store fields.
Define who resets shared state and who disposes subscriptions, timers, keyboard listeners and observers.
For a preview or dry run, trace the actual write set, completion signal and cancellation semantics before describing it as side-effect-free or immediately canceled.

## Reuse the host's page and design language

For ordinary listing/detail pages, inspect the actual Twig slots in:

- `src/module/sw-settings-currency/page/sw-settings-currency-list/` and `sw-settings-currency-detail/`.
- `src/module/sw-settings-rule/page/sw-settings-rule-detail/`.
- `src/module/sw-flow/page/sw-flow-index/` and `sw-flow-detail/`.

They demonstrate `sw-page`, smart-bar actions, search, content, route tabs, loading/empty states and field errors.
Use current TypeScript/component conventions rather than copying their historical JavaScript/mixins.
Place primary page actions in the host action area and use established save-process feedback where applicable.
Use the same host button variants/sizes for equivalent actions; avoid correcting mismatched component choices with arbitrary CSS widths.

A full visual editor may deliberately use a different shell.
Read `src/module/sw-experience-studio/page/sw-experience-studio-detail/`, its toolbar component and `store/experience-studio-editor.store.ts` together.
The page coordinates the persisted layout and mutations, while the store owns bounded history/selection and resets at owner changes.
The toolbar renders its inputs and emits intent.
Bounded grid columns, `minmax`, `min-height: 0` and explicit scroll containers keep the workspace usable.
Do not copy its panel arrangement without determining the target editor's tasks.

Use scoped BEM selectors and existing Meteor tokens for colors, spacing, borders and typography.
Static styling belongs in SCSS; a dynamic CSS custom property is appropriate for measured geometry, resizing or a selected color.
Keep that property owned by the component, as the thumbnail color and editor panel width do.
Use host fields, buttons, modals, tabs and loading/error patterns before creating replacements.
Check long Chinese/English labels, narrow widths, focus order and keyboard operation in the rendered UI.

For a plugin settings form, first check the host's declarative configuration rather than creating a module.
`src/module/sw-extension/page/sw-extension-config/index.ts` and `src/module/sw-settings/component/sw-system-config/index.js` provide existing configuration loading, sales-channel inheritance and save feedback.
Trace the declaration reader and server validation described in the plugin reference; a form field's limits do not secure every API/service write path.

## Select panels by the task they support

An editor's persistent panels should support frequent work; secondary operations can use tabs, drawers, popovers or dialogs.
Global configuration, diagnostics, version/history review and runtime inspection are different tasks and need not occupy permanent sidebars.
For each proposed panel identify its user, input/state owner, opening trigger, actions, permissions and behavior when nothing is selected.
Do not add an empty panel or generic panel framework just because another product has one.
Provide keyboard/touch access for actions also exposed on hover.

When a new editor interaction has no comparable host implementation, examine actual public product documentation/demos and relevant open-source source code.
Keep observed behavior separate from the proposed adaptation.
Record the URL/revision, task solved, interaction/lifecycle and integration cost; screenshots alone do not prove an extension architecture.
Then use the host components/tokens and a concrete extension contract for the part that really varies.

## Repository saves, capabilities and localization

Use injected `repositoryFactory` and DAL `Criteria`, with the associations and counts the screen requires.
Keep entity/association collection context rather than treating collections as unrelated arrays.
Reload a saved entity when continued editing requires resetting origin/change tracking.
Distinguish save failure from post-save reload failure when the persisted result differs.
Experience Studio detail's save path illustrates that distinction; Currency detail illustrates field-error mapping and language-change protection.
Rule detail illustrates separate dirty tracking for its associated condition tree.

Protect routes, navigation and actions with matching capabilities; backend authorization remains authoritative.
Trace every entity/association read or write into the module's ACL mapping.
Evaluate stored-role migrations when existing roles must receive new permissions.
Read Experience Studio's `acl/index.ts` and module registration as an end-to-end example rather than copying its privilege names.

Use `en.json` and `zh.json` for built-in languages and existing error/snippet paths.
Avoid selecting a locale at module-import time for content that must be authored in the current user's locale.
Distinguish persisted authored labels from labels translated at render time.
Use root snippet rules if older module examples show locale-based filenames.

## Preserve the correct extension authoring system

For factory/Twig components use typed `Shopwell.Component.wrapComponentConfig`, host registration/override APIs and meaningful Twig blocks.
Use Vue for runtime conditions/bindings and Twig blocks for extension merging.
An override must preserve the parent's async return and `$super` behavior when it extends that behavior.
New Twig blocks require the block-list generation/check described by the Admin skill.

The current architecture also supports native setup SFCs through Shopwell's transformed extension system.
Do not infer that plain unregistered Vue SFCs are supported, or that every SFC is prohibited.
Read `technical-docs/03-extensibility/07-native-setup-authoring.md` for its experimental status, filename targets, `<script setup>`, `swDefinePublic` and `swDefineOverride` contracts.
Read `08-native-overrides-on-twig-components.md` when crossing authoring systems.
Check the target host/version/feature before selecting that path; do not migrate surrounding components as incidental cleanup.

Prefer extension-aware `Shopwell` APIs and injected collaborators over importing factory internals.
Registration or injectability alone does not make a service a stable public extension API; inspect its support annotations, documented contract and target host version.
For supported `shopwell:*` virtual imports, read `technical-docs/05-global-object/04-virtual-modules.md` and its layer/registration constraints.
Do not assume an arbitrary module/plugin store or mixin is in the generated host registry.
Read `extension-tooling/README.md` when developing a plugin; use the host type bridges and actual extension validation rather than bundling a second Vue/framework stack.
Check the relevant generated block, position and module manifests when their contracts change.
