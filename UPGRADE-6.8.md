# 6.8.0.0

## PayPal and Stripe payment integrations

PayPal and Stripe integrations were removed from Core and moved to the separate `swag/payments` plugin.
Install and activate `SwagPayments` before using these providers and configure your own merchant credentials.
Update integration imports from `Shopwell\Core\System\Payment\Integration\Paypal` and `Shopwell\Core\System\Payment\Integration\Stripe` to their equivalents under `Swag\Payments\Integration`.
Update calls to `PaymentTransferRecipientService::markStripeAccountReady()` to `markProviderAccountReady()`.
The former Core sandbox account initialization and integration namespace aliases are not provided.

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
