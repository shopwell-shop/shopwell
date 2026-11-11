# 6.7.0.0 (upcoming)

## Core

### After-sales refund destination

Administration refund confirmations default to the original payment sources and can instead credit a registered customer's wallet.
The approve, receive and refund actions accept optional `refundDestination` (`original` or `wallet`); the first payout attempt fixes this value on the return.
Wallet payouts also reduce the collected payment's refundable amount and use the return number for idempotency.
Guest returns support the original payment sources only.

### Free growth alongside paid membership

Customers continue earning tier points during paid membership.
The effective tier is the higher of the earned tier and the valid paid tier; expiry falls back to the earned tier immediately when read.
Paid tier qualification is available as `loyalty_account.paidTierId`, independently of the effective `tierId`.
Existing running subscriptions are migrated automatically.

### SMS provider extension contract

SMS providers extend `Shopwell\Core\System\Sms\Provider\AbstractSmsProvider` and register with `shopwell.sms.provider`.
Credential and connection fields use `SmsConfigField`; the Administration renders them and enabled accounts are validated when written.
The provider metadata endpoint includes `optionDefinitions`.
Template sending and template approval are independent capabilities under `Provider\Audit`.
The development baseline removes the former gateway factory registration and uses `Routing\SmsGatewayResolverInterface` for routing.
The `SelectSmsGatewayExtension` extension can reorder or remove eligible accounts.


### PayPal and Stripe integrations moved to SwagPayments

PayPal and Stripe are provided by the separate `swag/payments` plugin and are no longer bundled with Core.
Install and activate `SwagPayments` and configure your merchant accounts to use their checkout, refund, payout and notification capabilities.
The SDK dependencies, payment method initialization, icons and Administration translations are owned by the plugin.
Integration classes now use `Swag\Payments\Integration` instead of `Shopwell\Core\System\Payment\Integration`.
The Core installation baseline seeds only Alipay and WeChat Pay, and the first-run wizard no longer installs the legacy `SwagPayPal` plugin.
The generic recipient readiness method is named `PaymentTransferRecipientService::markProviderAccountReady()`.


### Blog publishing and content layouts

New Blog entries default to drafts (`active=false`).
Publish an entry by setting `active=true`; a future `publishedAt` schedules its publication.
Blog entries reuse system tags and support native `blog_content_layout` assignments with `rootSource=blog`.
Tags expose their related entries directly through the native `blogs` association.
The Blog installation baseline provisions shared article, video, and listing layouts automatically.
The repair command `bin/console content:blog:provision-layouts` remains available.
The type-specific configuration keys are `core.content_system.default_blog_post_content_layout` and `core.content_system.default_blog_video_content_layout`, with `core.content_system.default_blog_content_layout` as the shared fallback.
The shared homepage/category listing default is configured through `core.content_system.default_blog_listing_content_layout` and uses `rootSource=none`.
Blog layout associations extend `content_layout` through `extensions.blogContentLayouts`.
Explicit Blog assignments take precedence over legacy standalone body layouts, which remain supported.
Register custom types through `BlogTypeRegistry::addType($type, $template, $schemaType)` instead of type handlers.
`GET /api/_action/blog/types` lists registered type names.


### MCP servers run on `mcp/sdk` 0.9 and `symfony/mcp-bundle` 0.15

Shopwell now requires `mcp/sdk` 0.9 and `symfony/mcp-bundle` 0.15.
Plugins and bundles that register MCP tools are affected in these points:

* A tool that declares an `outputSchema` has its `structuredContent` validated against it.
  A result that does not match is sent as an error result with `isError: true`.
  Return `new \stdClass()` for an empty object, since `[]` is sent as an array.
* A tool whose `inputSchema` has a `properties` member that is not an object, or a `required` member that is not a list, is rejected when it is registered.
* `#[CompletionProvider]` no longer takes `providerClass`; pass the class to `provider` instead.

The SDK classes for an OAuth proxy, client registration and OIDC discovery were removed, see the [mcp/sdk 0.9.0 changelog](https://github.com/modelcontextprotocol/php-sdk/blob/v0.9.0/CHANGELOG.md).
The MCP endpoints match the `Origin` header of browser clients exactly, including scheme and port.
They accept the origins of `APP_URL` and of every sales channel domain.
A loopback origin, for example the MCP Inspector on `http://localhost:6274`, is accepted only for a shop on a loopback host such as `localhost` or `*.localhost`.
Clients without an `Origin` header, such as CLI clients and desktop apps, are not affected.
Allow further origins with `shopwell.mcp.allowed_origins`.
An entry is an exact origin, an origin with `:*` for any port, or `*` for any origin:

```yaml
shopwell:
    mcp:
        allowed_origins:
            - 'https://chat.example.com'
            - 'http://localhost:*'
```

The list can also come from an environment variable, for example `allowed_origins: '%env(csv:MCP_ALLOWED_ORIGINS)%'`.
A rejected origin is logged as a warning in the `mcp` channel.

### Collection class for import/export files

`import_export_file` entities are now loaded into an `ImportExportFileCollection` instead of a generic `EntityCollection`.
Replace `EntityRepository<EntityCollection<ImportExportFileEntity>>` annotations with `EntityRepository<ImportExportFileCollection>`.

### Promotion line items store the discount value in the cart currency

`payload.value` of promotion line items with an absolute, fixed or fixed unit discount now contains the value in the cart currency, using the advanced price of the currency or the currency factor.
Orders placed before this change still contain the default currency value.
Extensions and headless frontends that convert `payload.value` into the cart currency themselves must remove that conversion.

## Administration

### Administration navigation layouts

Administrators can choose sidebar, top, or combined navigation under My profile > User interface and apply the choice by saving their profile.
The preference is stored per user under `core.adminNavigationLayout`; smaller screens continue using the navigation drawer.
All layouts use the configured menu order, role restrictions and registered module navigation.
Top and combined layouts place the existing page search and toolbar actions in one compact header.
Sales channels use their own navigation area instead of appearing in every module sidebar.

### Return settings per sales channel

Return rules support sales channel overrides and inheritance from the global configuration.
Return reasons have a separate settings entry and remain shared across sales channels.
The return rules page uses the standard `system.system_config` permission; reason management continues to use the return permissions.

### Role-based menu assignments and separate user management

User management now uses the `sw-user` module and `user.*` privileges.
Roles, business permissions and menu configuration use `sw-permissions`, `permissions.*` and `admin_menu.*` respectively.
Role details combine explicit menu selection with the existing permission matrix and dependency handling.
Role details now display special operations alongside their owning page and show the pages sharing a capability.
Modules can declare an optional `resource` on an operation privilege role to associate it with a business permission key; this metadata does not grant access.
The verified role write endpoint accepts a complete `menuIds` selection, saved atomically with role privileges.
Menu selections do not grant page or API access, and an empty selection hides navigation.
Role menu selections combine across roles; a role without selected menus contributes no navigation.
The visible menu response includes `allowUnmanaged` to control client declaration fallbacks.
Menu-side role assignment and the `/_action/admin-menu/assign-roles` endpoint were removed.

### Configurable administration menus

Menu sections can now be managed as independent `group` nodes in both navigation and settings.
Legacy provider `groupName` declarations and translated group titles are migrated automatically.
The optional `routeKey` field selects a page without changing a system or plugin node’s `internalKey` identity.
Settings overview groups now follow the visible administration menu configuration, including translated group titles and custom links.
The visible menu endpoint returns `groupName`, `groupTitle`, and `managedKeys` so clients can apply stored groups without restoring hidden provider entries from module declarations.
The route whitelist includes installed plugin providers alongside core declarations.

### Organization settings layout

Organization settings now open a shared tree workspace, with organizations as independent roots and their units nested below them.
The index, create and detail routes reuse this workspace; the detail route accepts `unitId` and `parentId` query parameters to restore unit selection and creation context.
Extensions can use the sidebar and node action slots of `sw-settings-organization-unit-tree`, which now receives both organizations and units.
Extensions of the former `sw_settings_organization_detail_split`, `left`, `left_header`, `right`, and `no_selection` blocks should target `sw_settings_organization_detail_sidebar`, `tree`, or `unit_form` instead, using the `sw_settings_organization_detail_` prefix.
New organizations open the `sw.settings.organization.create` route, which reuses the detail form.
New units open an unsaved entity in the detail form with the selected parent prefilled.
The former organization and unit creation modal blocks are replaced by the detail form blocks.
Changes are persisted through Save and reverted through Cancel using the entity repository.

### Blog editor and extension points

The Blog editor provides a wide content canvas, a compact settings sidebar, and separate Content, Layout and SEO tabs.
Register custom editors through `registerBlogType($type, { component, label, icon })`; existing lazy editor loaders remain supported.
Named editors use the Administration component factory, so component extensions apply.
Plugins can extend `sw-blog-detail-content`, `sw-blog-detail-settings`, and `sw-blog-detail-tabs` through the standard component section and tab extension APIs.
Tags use the system tag selector within the editor and do not add a navigation item.
Blog navigation now groups content, categories, authors, and comments under a localized menu folder.
Category management uses one workspace with a persistent category tree and an inline editor with General, Content, Layout, and SEO tabs.
The category tree follows the system menu tree with drag handles, positional drop previews, sibling and child creation, and expand/collapse actions.
Category search uses the standard page search bar, retains matching ancestor paths, and restores expanded branches when cleared.
Root creation uses the page actions; sibling and child creation use the node action menu.
New categories open an unsaved full form in the same workspace and are persisted only through Save.
New authors open the `sw.blog.author.create` route and use the full detail form; records are persisted only through Save.
Categories support parent and child relationships, drag and drop ordering, and menu actions for moving categories.
Tree changes persist immediately after resolving unsaved editor changes.
Moving a category into itself or its descendants is rejected, and categories with children must be emptied before deletion.
The Layout tab displays the effective global layout and supports copying it or restoring the shared default.

### Today dashboard statistics

The Administration homepage now shows today's product sales, successful refunds, wallet top-ups, membership activations, and hourly sales in the operator's timezone.
`GET /api/_admin/dashboard/today` provides these statistics in the system currency, with comparisons to the same local time yesterday for all four metric cards.
The endpoint derives its timezone from the authenticated user profile.
Product sales exclude wallet top-ups and membership purchases, and duplicate successful payment attempts do not create additional sales.
Posted wallet top-ups are reported separately with amount and count, subject to wallet transaction read permission.
Membership activations count distinct customers by successful activation time, including renewals, subject to membership purchase read permission.
Wallet amounts use current exchange factors and are accumulated in system currency minor units.
The former order-amount endpoint has been replaced by the today endpoint.


### Deprecated the sales channel lists of `sw-settings-search-live-search` and `sw-newsletter-recipient-list`

The `salesChannels` data property, the `salesChannelRepository` computed property and the `fetchSalesChannels()` method of `sw-settings-search-live-search` are deprecated and will be removed without replacement.
The `salesChannelFilters` data property of `sw-newsletter-recipient-list` is deprecated and will be removed without replacement.
Their sales channel selects load their options themselves, so with the `V6_8_0_0` feature flag both components no longer load sales channels when they are created.

### Meteor apps get their own path in the Administration watcher

An extension that ships an Administration entry and a `meteor-app` used to get both dev servers under `/_internal_ext/<technical-name>/`.
Both servers shared one proxy path, so the Administration only reached one of them.
The watcher now serves meteor-apps under `/_internal_app/<technical-name>/`, also for extensions without an Administration entry.
The Administration reads the new URL from `sw-plugin-dev.json`, so you only need to adjust tooling that calls the old URL of a meteor-app directly.

### The Administration watcher applies template edits without a page reload

While `composer watch:admin` runs, saving the Twig template of a component or of a component override updates that component on the page and keeps its state.
Components that extend it update as well.
The watcher still reloads the page when it cannot apply an edit in place:
for a Twig override of a component without a Twig template, for a template whose content another template file has as well, or for a component that has not been rendered yet.

With the `ADMIN_VITE_8` feature flag, the watcher also applies script edits of native `.vue` components and `.override.vue` files of extensions without a page reload.
This includes edits of the files such a component imports.
The watcher remounts the edited component and the components an edited override changes.
It reloads the page for edits it cannot apply in place:
edits that change the blocks of a component, edits of a component built with `Component.extend()` or `Component.override()` or of a component that is not on the page, and updates that fail to render.

### Experimental: Vite 8 and HTTP/2 for the Administration behind the `ADMIN_VITE_8` feature flag

The new `ADMIN_VITE_8` feature flag builds the Administration and the Administration code of extensions with Vite 8.
Vite 8 becomes the default with Shopwell 6.8, so you can use the flag to test your extensions now:

```bash
ADMIN_VITE_8=1 composer watch:admin
ADMIN_VITE_8=1 composer build:js:admin
```

With the flag, building the Administration requires Node.js `^20.19.0 || >=22.12.0`.
The `postinstall` script of the Administration installs Vite 8 into `build/vite-8`.
If your CI restores the Administration dependencies from a cache without running that script, also run `npm ci` in `build/vite-8`.

With the flag, the watcher serves all files over HTTP/2.
Browsers only use HTTP/2 over HTTPS, so put HTTPS in front of the watcher:

* Run `shopwell-cli project proxy up` and open `https://admin-watch.<project>.shopwell.local`.
* Or set `SHOPWELL_ADMIN_HTTPS_CERTIFICATE_FILE` and `SHOPWELL_ADMIN_HTTPS_KEY_FILE` to a certificate and its key, for example created with [mkcert](https://github.com/FiloSottile/mkcert).
  Relative paths are resolved from the project root.

With the flag, the watcher serves the Administration code and the meteor-apps of extensions through its own port.
The separate `Extensions` process and the port per extension are gone.
The `vite.config` of an extension can no longer change the address of its dev server.
The watcher ignores `server.port`, `server.host`, `server.ws` and the connection options of `server.hmr` (`protocol`, `host`, `port`, `clientPort`, `path` and `server`) there.

### `sw-chart` loads only the chart locale it needs

`sw-chart` now loads the ApexCharts locale of the current Administration language on demand instead of importing all 33 locales up front.
English uses the locale built into ApexCharts and loads nothing.
The chart renders once the locale has loaded.
`createdComponent()` and `loadLocaleConfig()` of `sw-chart` now return a promise.
If your override reads `localeConfig` after calling the base method, await it:

```js
async loadLocaleConfig() {
    await this.$super('loadLocaleConfig');
    // this.localeConfig is set here
}
```

## Storefront


### Skip link for listing filters

Category, landing and search pages with a filtered product listing now offer a "Skip to products" link in the skip-to-content bar, so keyboard users can jump past the filters to the products.
The link targets the new `id="product-listing"` on the product listing wrapper, including the `Sw:Product:Listing` component, and is only shown from the `lg` breakpoint, where the filters are not collapsed into the off-canvas button.
Themes that override the `product_listing` block in `storefront/component/product/listing.html.twig` without `parent()` must keep `id="product-listing"` on their wrapper, otherwise the link has no target.
`storefront/component/skip-to-content.html.twig` has a new `productListing` parameter (default `null`) and the new block `component_skip_to_content_product_listing`.

## API

### Installed extensions tell whether they can be removed

Extensions returned by `GET /api/_action/extension/installed` have a new `allowRemove` flag, also available as `ExtensionStruct::isAllowRemove()`.
It is `false` when Shopwell can't delete the extension's files, for example for extensions that Composer installed outside `custom/plugins`, and for extensions that are only available in the store.
Check it before calling `POST /api/_action/extension/remove/{type}/{technicalName}`.


## App System

### App MCP prompt and resource responses

App MCP prompt responses are now decoded as JSON arrays of messages with a `user` or `assistant` role and `content`, for example `[{"role":"user","content":"Hello"}]`.
App MCP resource responses must be JSON objects containing a `text` string or a base64-encoded `blob` string, for example `{"uri":"my-app://status","mimeType":"application/json","text":"{\"ok\":true}"}`.
Plain-text resource responses are no longer accepted; wrap the content in a JSON object.
Resources use the URI declared in `mcp.xml`; a non-empty `mimeType` in the response takes precedence over the configured MIME type.
Invalid JSON, malformed messages or resource contents, and `{"success":false,"error":"..."}` responses produce MCP errors with details for the client.
