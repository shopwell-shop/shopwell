# Storefront and plugin engineering

Use `shopwell-storefront-component` for component work and the PHP/Admin/test skills for the other surfaces a plugin touches.
Read actual framework contracts when a historical skill example or upstream Shopware example disagrees with this checkout.

## Choose an extension's deployment and ownership model

`README.md` distinguishes themes, apps, plugins and headless frontends.
A theme changes presentation, an app integrates through its supported remote/declarative APIs, and a plugin adds in-process PHP behavior.
Do not assume an app can call a PHP service or that a theme alone can enforce a business rule.
Plugin-owned configuration, entities, migrations, services and resources belong to that plugin.

Read `src/Core/Framework/Plugin.php`, `Bundle.php` and `Plugin/PluginLifecycleService.php` before using lifecycle hooks.
Composer type, PSR-4 and the entry class must agree; `custom/plugins/SwagPlatformDemoData/composer.json` is a metadata example.
Its demo-data creation/deletion behavior is unsuitable as a general lifecycle template.
Do not copy a fixture plugin's destructive behavior into production plugin setup.

Installation calls `install()` before its migration stage and `postInstall()` afterwards.
Do not assume the plugin's schema or container services are available at every callback.
Activation can rebuild the container and reload the instance; deactivation and uninstall have different effects.
Respect `UninstallContext::keepUserData()` when removing owned data.
Use owned migrations rather than arbitrary startup DDL.
Current Bundle loading supports PHP service/route configuration and deprecates XML service/route configuration for the announced version; inspect the actual loader before copying old wiring.
Plugin business configuration still uses its separate bundle-relative `Resources/config/config.xml` contract.

For plugin settings, read `src/Core/System/SystemConfig/Util/ConfigReader.php` and the existing Admin extension configuration screen before creating a custom settings API/entity/module.
Trace `SystemConfigValidator`, `SystemConfigController` and `SystemConfigService` for the exact write path: UI/batch validation does not automatically establish guarantees for direct service or other API writes.
Use an existing change event for genuinely needed cross-field/domain validation, scoped to the plugin's keys; preserve null overrides when they mean sales-channel inheritance.

For a supported extension, identify the host contract, registration mechanism, ordering/conflict policy, lifecycle, permissions and intended version range.
An existing `@internal` class or private component is not a permanent plugin API merely because it is importable.
If a task must integrate with an internal surface, make that limitation explicit rather than advertising stability the host does not promise.
Prefer supported events/hooks over whole-class or whole-template replacement when their timing fits.

## Trace Storefront data from Core to props

Controllers and page loaders adapt customer-facing Core operations rather than introducing their own repository queries.
Read `src/Storefront/Controller/ProductController.php` and `Page/Product/ProductPageLoader.php` for the page flow and its criteria/loaded-event timings.
Their comments state the route boundary, but the loader retains a historical review repository dependency; do not use that exception to justify new bypasses.
`src/Storefront/Page/Account/OrderReturn/GuestOrderReturnPageLoader.php` is a clearer current example of delegating authorized data access to a Core route while keeping request/rendering ownership explicit.

Choose an event at the phase that supplies the required information:

- A criteria event can adjust the upcoming query without a second per-product lookup.
- A page-loaded event can enrich the assembled page.
- A typed extension event can wrap/replace an explicitly supported Core operation.
- An app script hook serves the app scripting contract at its declared phase.

These mechanisms have different mutation/replacement and failure semantics.
Trace the actual dispatch and consumer instead of choosing by a familiar event name.
Retain `SalesChannelContext`, visibility, language, currency, version and customer authorization in the Core path.
If presentation enforces a real purchasing restriction, enforce that rule at the authoritative server operation too.

Pass component inputs explicitly rather than assuming a page extension/global will reach every descendant.
Current product rendering uses ContentSystem; inspect the actual layout and prop handoff.
`src/Storefront/ContentSystem/AGENTS.md` requires content elements to be rendered through the shared Slot component.
Core owns general content semantics; Storefront owns its section assignment/rendering concerns.
Do not assume every declared assignment table is already used by every rendering path.

## Twig composition and extension compatibility

Use anonymous UX components by default and PHP-backed components when actual server behavior requires them.
Core and plugin bundle component namespaces differ; inspect registration before naming a plugin component.
`src/Storefront/Resources/views/components/Sw/Button/index.html.twig` demonstrates explicit props, CVA variants with a base, merged `attributes.defaults()` and a meaningful content block.
Expose replaceable content through meaningful blocks/slots and keep domain decisions out of templates.
Preserve contextual escaping; do not pass arbitrary untrusted HTML as a convenience prop.

Existing inheritance surfaces still use `sw_extends`, `sw_include`, named blocks and `parent()`.
Read `src/Storefront/Resources/views/storefront/page/content/page.html.twig` for coexistence with component rendering.
Replacing that mechanism with ordinary Twig inheritance can break multi-bundle extension behavior.
Do not migrate neighboring templates when a targeted supported block solves the task.

## Browser lifecycle has two implementations

For modern components, read `src/Storefront/Resources/app/storefront/src/component-system/README.md`, `component.ts` and the relevant initialization code in `shopwell.ts`.
The runtime discovers component scripts, initializes dynamic descendants and destroys removed components.
Its options merge is shallow, unlike the legacy plugin system's deep merging.
The base constructor calls `init()`, so initialization must not depend on subclass fields assigned only after `super()` returns.

`src/Storefront/Resources/views/components/Sw/Product/BuyButton.js` demonstrates stored bound handlers, existing form/cart integration, an interception event and cleanup of the same listener references.
Bind once and remove the identical function in `destroy()`; calling `.bind(this)` again during removal creates another function and leaks the original.
The older Storefront component skill's illustrative cleanup sample contains that error; follow the actual lifecycle contract and correct source pattern.
This does not endorse every Twig convention in the BuyButton component as a current template example.

For an existing legacy JS plugin, inspect `src/Storefront/Resources/app/storefront/src/main.js`, `plugin-system/plugin.class.js` and the actual manager registration.
Preserve its selector, registration name, options attributes and update/destroy contract.
Do not mix `data-component-options` with legacy `data-<plugin-name>-options`.
Use an available event/interception seam before an override that conflicts with another extension's override.

Own listeners, requests, timers and observers at the component lifetime.
For asynchronous refreshes, define cancellation/stale-response behavior so an older request cannot overwrite newer user input or update a destroyed instance.
Subscribe to an event the actual source dispatches; do not invent a global event from the feature's name.

## Styling, builds and cache lifetime

Use Bootstrap primitives and theme variables where they fit; scope additional component selectors and keep BEM flat.
Use existing `--bs-*` customization and component-owned `--sw-*` properties for runtime values.
Do not guess Sass tokens or use global specificity overrides to hide a wrong component/layout choice.
Read `src/Storefront/Resources/app/storefront/src/scss/variables.scss`, `scss/skin/shopwell/abstract/_variables.scss` and `scss/abstract/variables/_css-properties.scss` for variable ownership.
`Sw/Button/index.scss` under the component directory demonstrates token-based Bootstrap customization, but its historical selector shape does not override current component rules.

Read the Storefront package scripts and `src/Storefront/Theme/ThemeCompiler.php` when assets change.
This checkout has legacy Webpack builds and modern Vite component/import-map builds.
A stale “no build needed” or manual `@Components` theme example is not a reliable description of the current pipeline.
Do not edit published assets or generated caches as source fixes.

Read `coding-guidelines/core/http-cache-and-assets.md` for cache or asset changes.
Cache keys/tags must describe the data that changes the output; customer-specific state cannot be put into shared HTML without the relevant policy.
Old assets can remain referenced by cached HTML/CDNs after a new compile.
Do not synchronously delete a previously referenced asset solely because its file age is high.
Use the owning invalidation/retention mechanism rather than a new global response subscriber or blanket cache disablement.

## Test at the surface that owns the behavior

Use PHP unit tests for domain decisions and integration tests for actual DAL/API/ACL/migration/lifecycle behavior.
Use the Storefront package's Jest setup for legacy plugins and its Vitest component project for modern components.
Check the existing test's owning harness during a change: the BuyButton integration test still resides at `src/Storefront/Resources/app/storefront/test/component-system/buy-button.test.js` under Jest.
For new colocated component tests, inspect the current Vitest configuration and `src/Storefront/Resources/views/components/Sw/Form/index.test.js` rather than assuming old setup helpers apply unchanged.
Verify actual file discovery; a green test command that excluded `custom/plugins` does not validate that plugin.
Use browser/acceptance checks for rendered interactions, composition and accessibility.
For Twig-only changes, run the Twig lint and appropriate rendered checks; do not add a direct-render PHP integration test that can poison shared request-dependent globals.
