# 6.8.0.0

## PayPal and Stripe payment integrations

PayPal and Stripe integrations were removed from Core and moved to the separate `swag/payments` plugin.
Install and activate `SwagPayments` before using these providers and configure your own merchant credentials.
Update integration imports from `Shopwell\Core\System\Payment\Integration\Paypal` and `Shopwell\Core\System\Payment\Integration\Stripe` to their equivalents under `Swag\Payments\Integration`.
Update calls to `PaymentTransferRecipientService::markStripeAccountReady()` to `markProviderAccountReady()`.
The former Core sandbox account initialization and integration namespace aliases are not provided.

## Shopwell Payments placeholder

The empty `sw-payments` Administration module, `sw.payments.index` route and `sw-payments.*` privilege mapping were removed.
Extensions that used this placeholder as a navigation parent must register their own functional menu entry and page access privileges.
The `sw-payment` payment orders and refunds module remains available.

## User and permission modules

The `sw-system` Administration module was replaced by `sw-user` and `sw-permissions`.
Update component names, Twig block prefixes and snippet roots to the module that owns the feature.
User routes use `sw.user.*`; role and menu routes use `sw.permissions.role.*` and `sw.permissions.menu`.
The `users_and_permissions.*` privilege mapping was split into `user.*` and `permissions.*`.
Menu structure management retains `admin_menu.*`.
The shared `sw-system` navigation group remains a folder.
Menu-side role assignment was removed; save a role's complete `menuIds` list through the user-verified role endpoint.
Role menus use explicit selections only; page permissions no longer add navigation entries automatically.
Clients of `/_action/admin-menu/visible` must honor `allowUnmanaged: false` and suppress undeclared navigation fallbacks.

## Sales channel lists removed from `sw-settings-search-live-search` and `sw-newsletter-recipient-list`

The `salesChannels` data property, the `salesChannelRepository` computed property and the `fetchSalesChannels()` method of `sw-settings-search-live-search` were removed without replacement.
The `salesChannelFilters` data property of `sw-newsletter-recipient-list` was removed without replacement.
Both components no longer load sales channels when they are created, because their sales channel selects load their options themselves.
Extensions that read these properties must load the sales channels themselves, for example with `repositoryFactory.create('sales_channel')`.

## Default Storefront theme

The former default base and skin partial imports were replaced by the new foundation at the same entry points.
Child themes that relied on unimported default selectors must supply their page styles or adopt the current UX blocks and CSS properties documented in `src/Storefront/Resources/THEME.md`.
The `base_navigation` and `base_navigation_inner` blocks moved from `storefront/layout/header.html.twig` into `storefront/layout/header/header.html.twig`, inside the new `layout_header_navbar` block.
Move navigation block overrides to the inner header template.
The separate `layout_header_navigation_toggle_tablet*` blocks were replaced by the shared `layout_header_navigation_toggle*` blocks for all widths below `lg`.
The header search became collapsible at every breakpoint.
Keep the `header-search-toggle` button ID and its `searchCollapse` target when overriding the search trigger, so the search skip link and focus handling remain connected.

## `--no-validate` removed from `app:install` and `app:refresh`

The option had no effect since 6.7.
Passing it now fails with an unknown-option error.

## Non-visible API associations are rejected

Admin API and Store API requests, as well as app scripts using `services.repository` or `services.store`, now reject associations in the `associations` parameter that are not visible in the caller's API scope with HTTP 400 and `FRAMEWORK__ACCESSOR_NOT_ALLOWED`.
Selections in the `fields` parameter that traverse a non-visible association are also rejected.
In 6.7, these requested associations and their nested criteria, and these field selections, were ignored with a deprecation notice in debug mode.
Remove associations that are not exposed in the relevant API scope and field selections that traverse them from your criteria before upgrading.
App permissions do not change association visibility through `services.store`.

## Reference-based Admin API detail routes use one-to-one associations

The Admin API detail routes `/api/customer/{customerId}/default-billing-address`, `/api/customer/{customerId}/default-shipping-address`, and `/api/order/{orderId}/billing-address` now resolve their configured reference only.
Previously, these routes could return unrelated records or fail because the underlying DAL associations were not modeled as one-to-one associations.

</details>

## Recovery entity accessors

`UserEntity::getRecoveryUser()`, `UserEntity::setRecoveryUser()`, `CustomerEntity::getRecoveryCustomer()`, and `CustomerEntity::setRecoveryCustomer()` were removed.
Remove criteria using the `user.recoveryUser` or `customer.recoveryCustomer` associations, which were removed in a security update.

<details>

## Added `primaryOrderDelivery` and `primaryOrderTransaction`

Currently, there are multiple order deliveries and multiple order transactions per order.
If only one, the "primary", order delivery and order transaction is displayed and used in the administration.
There is now an easy way to make use of this by using the `primaryOrderDelivery` and `primaryOrderTransaction` properties.
All existing orders will be updated with a migration so that they also have the primary values.
From now on, the `OrderTransactionStatusRule::match` will always use the `primaryOrderTransaction` instead of the most recently successful transaction.
Starting with 6.8, integrations and API users that write orders through the Admin API, Sync API, or DAL must set `primaryOrderDeliveryId` and `primaryOrderTransactionId` when they write deliveries or transactions.
Otherwise, the delivery address, delivery state, or payment state will be missing for those orders in the Administration.

### Use `primaryOrderDelivery`

Get the first order delivery with `order.primaryOrderDelivery` so you should replace methods like `order.deliveries.first()` or `order.deliveries[0]`

### Use `primaryOrderTransaction`

Get the latest order transaction with `order.primaryOrderTransaction` so you should replace methods like `order.transactions.last()` or `order.transactions[length - 1]`.

### The order detail renders every shipment

The Administration's order detail no longer treats every delivery besides the primary one as a shipping discount.
A delivery with negative shipping costs is a discount, every other delivery is a shipment, which matches how `Shopwell\Core\Checkout\Cart\Order\RecalculationService` already tells the two apart.
The general tab shows one shipping cost row per shipment and the details tab one delivery card per shipment, sorted by delivery `position` like the storefront, mails and documents.
The order overview, the order list, the state history and the bulk edit keep showing and changing the primary delivery.

Overrides of the Twig blocks inside `sw_order_detail_details_shipping` of `sw-order-detail-details` run once per shipment and must use the loop variable `shipment` instead of the `delivery` computed, which still points at the primary delivery.
App component sections at the `sw-order-details-state-delivery` positions are rendered in every shipment card as well.

## Removed translation of import/export profile label

The translation of the import/export profile label has been removed.
Profiles are now identified and displayed only by their technical name.

Direct construction of `ImportExportProfileTranslationDefinition`, `ImportExportProfileTranslationEntity` and `ImportExportProfileTranslationCollection`, including empty collections, already throws when the `v6.8.0.0` feature flag is active. Remove these constructions and use the profile's technical name instead of translated labels.

- The `$label` property and the following methods in `Shopwell\Core\Content\ImportExport\ImportExportProfileEntity` have been removed:
  - `getLabel()`
  - `setLabel()`
  - `getTranslations()`
  - `setTranslations()`
- The following classes have been removed:
  - `Shopwell\Core\Content\ImportExport\ImportExportProfileTranslationCollection`
  - `Shopwell\Core\Content\ImportExport\ImportExportProfileTranslationDefinition`
  - `Shopwell\Core\Content\ImportExport\ImportExportProfileTranslationEntity`
- The `importExportProfileTranslations` association has been removed from `Shopwell\Core\System\Language\LanguageDefinition`, and the following methods in `Shopwell\Core\System\Language\LanguageEntity` have been removed:
  - `getImportExportProfileTranslations()`
  - `setImportExportProfileTranslations()`
- `createLog()` and `getConfig()` in `Shopwell\Core\Content\ImportExport\Service\ImportExportService` now use `$technicalName` instead of `$label` when generating filenames.
- `generateFilename()` in `Shopwell\Core\Content\ImportExport\Service\FileService` now uses `$technicalName` instead of `$label` as profile name.

## MCP tools returning the legacy `{"success": …}` string deprecated

Returning the `{"success": …}` JSON string from an MCP tool was deprecated, whether it was built with `McpToolResponse::success()`, `::error()` and the other string helpers or by hand.
The string can't carry a summary or error details, and a PHP tool that returns it fails.
The string helpers `success()`, `error()`, `requirePrivilege()`, `missingPrivilegesError()`, `invalidCriteriaError()`, `decodeJsonOrError()` and `executeWithDryRun()` of `McpToolResponse` are removed.
Return a `Shopwell\Core\Framework\Mcp\Result\McpToolResult` instead, built with the helper of the same name plus `Result`.
Two helpers drop their `Error` suffix: `invalidCriteriaError()` becomes `invalidCriteriaResult()` and `missingPrivilegesError()` becomes `missingPrivilegesResult()`.

```php
// Before
public function __invoke(string $id): string
{
    return $this->success(['id' => $id]);
}

// After
public function __invoke(string $id): McpToolResult
{
    return $this->successResult(['id' => $id]);
}
```

A PHP tool that returns any other string, such as plain text, fails as well.
Return `McpToolResult::text('…')` for a result that is only text for the model; every client gets the text as is.
App tools are not affected: their responses keep the `{"success": …}` envelope or plain text.

**Troubleshooting:** a plugin tool that fails with `MCP tool "…" returned a string` or `returned the legacy {"success": …} JSON string` still returns a string.
Change the return type of `__invoke()` to `McpToolResult` and return `McpToolResult::text()` or the `…Result()` helper instead.

The counterparts are `successResult()`, `errorResult()`, `requirePrivilegeResult()`, `missingPrivilegesResult()`, `invalidCriteriaResult()`, `decodeJsonOrErrorResult()` and `executeWithDryRunResult()`.
App tools are not affected and keep their response format.

The PHPStan rule `McpToolLegacyStringResultRule` (identifier `shopwell.mcpToolLegacyStringResult`) moves from `core-rules.neon` to `rules.neon`.
It reports a `#[McpTool]` method whose native return type is `string`, `?string`, a union containing `string`, `mixed`, or missing.

## `ImitateCustomerTokenGenerator::HMAC_HASH_ALGORITHM` removed

The constant was unused.
Use `'sha256'` directly if you computed the imitation token hash yourself.

## Jest helper `test/_helper_/majorFeatureFlags` removed

Extension Jest setups that require `src/Administration/Resources/app/administration/test/_helper_/majorFeatureFlags` to activate the major feature flags should run on the Jest harness of the extension tooling instead.
It registers the feature flags of the installed Shopwell version, including the major flags of a major run.

```js
import { shopwellAdminJestConfig } from './.shopwell/jest.mjs';

export default shopwellAdminJestConfig({
    moduleNameMapper: { '^MyPlugin/(.*)$': '<rootDir>/src/$1' },
});
```

`composer admin:setup-extension-tooling` generates the `.shopwell/jest.mjs` bridge.
See *Running specs with Jest* in `src/Administration/Resources/app/administration/extension-tooling/README.md` for the accepted options.

## Administration HTTP client ships only Axios 1.x

The Administration HTTP client (`httpClient`) became a single Axios 1.x instance.
The legacy Axios 0.x dependency and the `axios-v1` package alias were removed.
The compatibility mode for Axios 0.x behaviour, the `CancelToken` shim and the version-specific aliases were removed with 6.8.

### Axios 1.x behaviour for every request

The `useAxiosV1` request option was removed.
Every request now behaves like Axios 1.x:

- **Query encoding.** `[` and `]` are percent-encoded (`?ids%5B%5D=1&ids%5B%5D=2`), Axios 0.x kept them literal (`?ids[]=1&ids[]=2`).
  This also applies to `httpClient.getUri()`.
  Pass an own `paramsSerializer` if a request depends on the literal form.
- **Response headers.** `response.headers` is an `AxiosHeaders` instance instead of a plain object.
  Lower-case property access such as `response.headers['content-type']` works as before.

Remove the option from your requests:

```javascript
// Before
this.httpClient.get('/api/endpoint', { useAxiosV1: false });

// After
this.httpClient.get('/api/endpoint');
```

### Request interceptors and header groups

Request interceptors see the headers after Axios 1.x merged the `common` and method groups.
An interceptor that wrote `config.headers.common[...]` or `config.headers.post[...]` failed with a `TypeError`, already on 6.7.
Set the header on `config.headers` directly:

```javascript
// Before
httpClient.interceptors.request.use((config) => {
    config.headers.common['x-my-header'] = 'value';
    return config;
});

// After
httpClient.interceptors.request.use((config) => {
    config.headers['x-my-header'] = 'value';
    return config;
});
```

### `CancelToken` replaced by `AbortController`

`httpClient.CancelToken`, the `cancelToken` request option, and the `CancelToken` and `Cancel` exports of `src/core/factory/http.factory` were removed.
Use `AbortController`:

```javascript
// Before
const source = httpClient.CancelToken.source();
httpClient.get('/api/endpoint', { cancelToken: source.token });
source.cancel();

// After
const controller = new AbortController();
httpClient.get('/api/endpoint', { signal: controller.signal });
controller.abort();
```

Detect cancellations with `httpClient.isCancel(error)`.

`Shopwell.Service('messageQueueService').consume(receiver, signal)` accepts only an `AbortSignal` as second argument.

### Version-specific escape hatches

`httpClient.axiosV0`, `httpClient.axiosV1`, `httpClient.interceptorsV0`, `httpClient.interceptorsV1`, `httpClient.defaultsV0` and `httpClient.defaultsV1` were removed.
Use `httpClient`, `httpClient.interceptors` and `httpClient.defaults` directly:

```javascript
// Before
httpClient.interceptorsV1.request.use(handler);

// After
httpClient.interceptors.request.use(handler);
```

### TypeScript

Type imports from `axios-v1` no longer resolve.
Use Shopwell's HTTP types instead of Axios types:

```typescript
import type { HttpClient, HttpError, HttpRequestConfig, HttpResponse } from 'src/core/factory/http-client.types';
```

`HttpClient` stays structurally compatible with `AxiosInstance`, so passing the client to `axios-mock-adapter` keeps compiling.

See the migration guide at `src/Administration/Resources/app/administration/technical-docs/09-security/axios-migration-guide.md` and the [single-transport ADR](adr/2026-09-11-administration-http-client-single-transport.md).

## App scripts methods maxAge() and invalidationState() removed

Method `response.cache.maxAge()` was removed.
Use `sharedMaxAge()` to set `s-maxage` instead.
The `clientMaxAge()` method is also available for setting `max-age`.

```diff
-{% do response.cache.maxAge(3600) %}
+{% do response.cache.sharedMaxAge(3600) %}
```

Method `response.cache.invalidationState()` was removed.
State-based invalidation is not supported anymore.

```diff
-{% do response.cache.invalidationState('logged-in', 'cart-filled') %}
+{# No replacement #}
```

## `AppCmsExtensionException` removed

`Shopwell\Core\Framework\App\Exception\AppCmsExtensionException` was removed.
A missing CMS block file and a CMS block outside of the `cms/blocks` directory of the App are thrown as `Shopwell\Core\Framework\App\AppException` instead.
Catch `AppException` and check the error codes `FRAMEWORK__APP_CMS_BLOCK_FILE_MISSING` and `FRAMEWORK__APP_CMS_BLOCK_OUTSIDE_OF_BLOCKS_DIRECTORY`.

## Old shop ID format no longer read

The system config key `core.app.shopId` is no longer read, read the shop ID from `core.app.shopIdV2` instead.

</details>

## Product indexing members removed from `sw-category-tree`

The `syncProducts()` and `indexProducts()` methods and the `productRepository` computed property were removed from `sw-category-tree` without replacement.
The server re-indexes the products of a moved category itself, so extensions should stop calling or overriding these members.
