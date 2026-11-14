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
