<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;

/**
 * Declares the core administration navigation.
 *
 * The data mirrors the `navigation` declarations of the administration modules, kept as
 * narrow metadata (internal key, label snippet, icon, privilege, position): the routes
 * and the components behind them stay registered in code, this provider only makes the
 * navigation layer available to the server side. A CI check compares both sources, so a
 * module that changes its navigation without updating this provider fails loudly.
 *
 * The declarations seed the `admin_menu_item` table and feed the route whitelist; the
 * visible tree also renders them for entries the database does not know yet, so a core
 * upgrade cannot lose newly added menu items.
 */
#[Package('fundamentals@framework')]
class CoreAdminMenuProvider implements AdminMenuDefinitionProviderInterface
{
    public function getPluginTechnicalName(): ?string
    {
        return null;
    }

    public function getDefinitions(): array
    {
        return [
            // Dashboard
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-dashboard',
                title: 'sw-dashboard.general.mainMenuItemGeneral',
                icon: 'regular-dashboard',
                position: 10,
            ),

            // Catalogue
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-catalogue',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'global.sw-admin-menu.navigation.mainMenuItemProducts',
                icon: 'regular-products',
                position: 20,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-product',
                title: 'sw-product.general.mainMenuItemOverview',
                icon: 'regular-products',
                parent: 'sw-catalogue',
                position: 10,
                privilege: 'product.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-category',
                title: 'sw-category.general.mainMenuItemIndex',
                parent: 'sw-catalogue',
                position: 25,
                privilege: 'category.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-review',
                title: 'sw-review.general.mainMenuItemList',
                icon: 'regular-star',
                parent: 'sw-catalogue',
                position: 20,
                privilege: 'review.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-product-stream',
                title: 'sw-product-stream.general.mainMenuItemGeneral',
                parent: 'sw-catalogue',
                position: 30,
                privilege: 'product_stream.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-property',
                title: 'sw-property.general.mainMenuItemGeneral',
                parent: 'sw-catalogue',
                position: 40,
                privilege: 'property.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-manufacturer',
                title: 'sw-manufacturer.general.mainMenuItemList',
                parent: 'sw-catalogue',
                position: 50,
                privilege: 'product_manufacturer.viewer',
            ),

            // Orders
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-order',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-order.general.mainMenuItemGeneral',
                icon: 'regular-shopping-bag',
                position: 30,
                privilege: 'order.viewer',
            ),
            // The administration keys an entry by its id, falling back to the route name for
            // entries without an id - the internal key has to match, or the visible tree and
            // the module navigation would render the same page twice.
            new AdminMenuDefinitionStruct(
                internalKey: 'sw.order.index',
                title: 'sw-order.general.mainMenuItemList',
                parent: 'sw-order',
                privilege: 'order.viewer',
            ),
            // A paid membership is an order the customer placed, so its queue belongs in the order
            // folder and not in the settings: an operator looking for "the subscription this customer
            // paid for" starts at the orders, and the settings are where the plans are maintained.
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-membership-orders',
                title: 'sw-membership.order.general.mainMenuItemGeneral',
                icon: 'regular-clipboard',
                parent: 'sw-order',
                privilege: 'loyalty.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-order-return',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-order-return.general.mainMenuItemGeneral',
                icon: 'regular-undo',
                position: 45,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-order-return-list',
                title: 'sw-order-return.navigation.list',
                parent: 'sw-order-return',
                privilege: 'order_return.viewer',
            ),

            // Payments
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-payment',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-payment.general.mainMenuItemGeneral',
                icon: 'regular-credit-card',
                position: 35,
                privilege: 'payment_order.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw.payment.index',
                title: 'sw-payment.general.mainMenuItemPaymentOrders',
                parent: 'sw-payment',
                privilege: 'payment_order.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw.payment.refund',
                title: 'sw-payment.general.mainMenuItemRefunds',
                parent: 'sw-payment',
                privilege: 'payment_refund.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-payments',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'global.sw-admin-menu.navigation.mainMenuItemShopwellPayments',
                icon: 'regular-shopwell-payments',
                position: 36,
                privilege: 'sw-payments.viewer',
            ),

            // Customers
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-customer',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-customer.general.mainMenuItemGeneral',
                icon: 'regular-users',
                position: 40,
                privilege: 'customer.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw.customer.index',
                title: 'sw-customer.general.mainMenuItemList',
                icon: 'regular-users',
                parent: 'sw-customer',
                privilege: 'customer.viewer',
            ),

            // Content
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-content',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'global.sw-admin-menu.navigation.mainMenuItemContent',
                icon: 'regular-image-text',
                position: 50,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-cms',
                title: 'sw-cms.general.mainMenuItemGeneral',
                icon: 'regular-image-text',
                parent: 'sw-content',
                position: 10,
                privilege: 'cms.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-media',
                title: 'sw-media.general.mainMenuItemGeneral',
                icon: 'regular-image',
                parent: 'sw-content',
                position: 20,
                privilege: 'media.viewer',
            ),
            // A blog entry is content between a landing page and a cms layout, so it hangs below
            // `sw-content` instead of opening a menu of its own.
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-blog',
                title: 'sw-blog.general.mainMenuItemGeneral',
                parent: 'sw-content',
                position: 40,
                privilege: 'blog.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-blog-category',
                title: 'sw-blog.general.mainMenuItemCategory',
                parent: 'sw-blog',
                position: 10,
                privilege: 'blog.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-blog-author',
                title: 'sw-blog.general.mainMenuItemAuthor',
                parent: 'sw-blog',
                position: 15,
                privilege: 'blog.viewer',
            ),

            // Marketing
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-marketing',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'global.sw-admin-menu.navigation.mainMenuItemMarketing',
                icon: 'regular-megaphone',
                position: 60,
                privilege: 'promotion.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-promotion-v2',
                title: 'sw-promotion-v2.general.mainMenuItemGeneral',
                icon: 'regular-discount',
                parent: 'sw-marketing',
                position: 10,
                privilege: 'promotion.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-newsletter-recipient',
                title: 'sw-newsletter-recipient.general.mainMenuItemGeneral',
                icon: 'regular-inbox',
                parent: 'sw-marketing',
                position: 20,
                privilege: 'newsletter_recipient.viewer',
            ),

            // Finance
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-finance',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-finance.general.mainMenuItemGeneral',
                icon: 'regular-money-bill',
                position: 75,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-finance-transfer',
                title: 'sw-finance.navigation.transfer',
                parent: 'sw-finance',
                privilege: 'payment_transfer.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-finance-wallet-transactions',
                title: 'sw-finance.navigation.walletTransactions',
                parent: 'sw-finance',
                privilege: 'wallet.viewer',
            ),

            // Extensions - always the second to last group of the navigation
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-extension',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-extension.mainMenu.mainMenuItemExtensionStore',
                icon: 'regular-plug',
                position: 980,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-extension-store',
                title: 'sw-extension.mainMenu.store',
                parent: 'sw-extension',
                position: 10,
                privilege: 'system.extension_store',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-extension-my-extensions',
                title: 'sw-extension.mainMenu.purchased',
                parent: 'sw-extension',
                position: 10,
                privilege: 'system.plugin_maintain',
            ),
            // Settings - always the last entry of the navigation; every settings page is a
            // child of it, mirrored one to one from the modules' `settingsItem` declarations
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings',
                title: 'sw-settings.general.mainMenuItemGeneral',
                icon: 'regular-cog',
                position: 990,
            ),

            // The rule builder is a settings page like every other one. It used to hang below
            // an "automation" folder of its own, which made a single page look like a section
            // of the product - the settings section the page belongs to is `automation`.
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-rule',
                groupName: 'automation',
                title: 'sw-settings-rule.general.mainMenuItemGeneral',
                icon: 'regular-rule',
                parent: 'sw-settings',
                position: 300,
                privilege: 'rule.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-basic-information',
                groupName: 'general',
                title: 'sw-settings-basic-information.general.mainMenuItemGeneral',
                icon: 'regular-bars',
                parent: 'sw-settings',
                position: 100,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-cache',
                groupName: 'system',
                title: 'sw-settings-cache.general.mainMenuItemGeneral',
                icon: 'regular-files',
                parent: 'sw-settings',
                position: 700,
                privilege: 'system.clear_cache',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-cart',
                groupName: 'general',
                title: 'sw-settings-cart.general.mainMenuItemGeneral',
                icon: 'regular-shopping-cart',
                parent: 'sw-settings',
                position: 110,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-country',
                groupName: 'localization',
                title: 'sw-settings-country.general.mainMenuItemGeneral',
                icon: 'regular-map',
                parent: 'sw-settings',
                position: 400,
                privilege: 'country.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-currency',
                groupName: 'localization',
                title: 'sw-settings-currency.general.mainMenuItemGeneral',
                icon: 'regular-euro',
                parent: 'sw-settings',
                position: 410,
                privilege: 'currencies.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-custom-field',
                groupName: 'content',
                title: 'sw-settings-custom-field.general.mainMenuItemGeneral',
                icon: 'regular-bars-square',
                parent: 'sw-settings',
                position: 500,
                privilege: 'custom_field.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-customer-group',
                groupName: 'customer',
                title: 'sw-settings-customer-group.general.mainMenuItemGeneral',
                icon: 'regular-users',
                parent: 'sw-settings',
                position: 200,
                privilege: 'customer_groups.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-delivery-times',
                groupName: 'commerce',
                title: 'sw-settings-delivery-time.general.mainMenuItemGeneral',
                icon: 'regular-clock',
                parent: 'sw-settings',
                position: 600,
                privilege: 'delivery_times.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-dictionary',
                groupName: 'general',
                title: 'sw-settings-dictionary.general.mainMenuItemGeneral',
                icon: 'regular-layer-group',
                parent: 'sw-settings',
                position: 120,
                privilege: 'dictionary.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-document',
                groupName: 'commerce',
                title: 'sw-settings-document.general.mainMenuItemGeneral',
                icon: 'regular-file-text',
                parent: 'sw-settings',
                position: 610,
                privilege: 'document.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-language',
                groupName: 'localization',
                title: 'sw-settings-language.general.mainMenuItemGeneral',
                icon: 'regular-flag',
                parent: 'sw-settings',
                position: 420,
                privilege: 'language.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-listing',
                groupName: 'commerce',
                title: 'sw-settings-listing.general.mainMenuItemGeneral',
                icon: 'regular-products',
                parent: 'sw-settings',
                position: 620,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-logging',
                groupName: 'system',
                title: 'sw-settings-logging.general.mainMenuItemGeneral',
                icon: 'regular-server',
                parent: 'sw-settings',
                position: 710,
                privilege: 'system.logging',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-login-registration',
                groupName: 'customer',
                title: 'sw-settings-login-registration.general.mainMenuItemGeneral',
                icon: 'regular-sign-in',
                parent: 'sw-settings',
                position: 210,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-mailer',
                groupName: 'system',
                title: 'sw-settings-mailer.general.mainMenuItemGeneral',
                icon: 'regular-envelope',
                parent: 'sw-settings',
                position: 720,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-measurement',
                groupName: 'general',
                title: 'sw-settings-measurement.general.mainMenuItemGeneral',
                icon: 'regular-balance-scale',
                parent: 'sw-settings',
                position: 130,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-media',
                groupName: 'content',
                title: 'sw-settings-media.general.title',
                icon: 'regular-image',
                parent: 'sw-settings',
                position: 510,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-message-stats',
                groupName: 'system',
                title: 'sw-settings-message-stats.general.mainMenuItemGeneral',
                icon: 'regular-bars-square',
                parent: 'sw-settings',
                position: 730,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-newsletter',
                groupName: 'content',
                title: 'sw-settings-newsletter.general.mainMenuItemGeneral',
                icon: 'regular-inbox',
                parent: 'sw-settings',
                position: 520,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-number-range',
                groupName: 'general',
                title: 'sw-settings-number-range.general.mainMenuItemGeneral',
                icon: 'regular-file-signature',
                parent: 'sw-settings',
                position: 140,
                privilege: 'number_ranges.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-oauth2-login',
                groupName: 'general',
                title: 'sw-settings-oauth2-login.general.mainMenuItemGeneral',
                icon: 'regular-key',
                parent: 'sw-settings',
                position: 150,
                privilege: 'oauth2_client.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-order-return',
                groupName: 'commerce',
                title: 'sw-settings-order-return.general.mainMenuItemGeneral',
                icon: 'regular-undo',
                parent: 'sw-settings',
                position: 630,
                privilege: 'order_return.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-organization',
                groupName: 'general',
                title: 'sw-settings-organization.general.mainMenuItemGeneral',
                icon: 'regular-officebuilding',
                parent: 'sw-settings',
                position: 160,
                privilege: 'organization.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-payment-gateway',
                groupName: 'payment',
                title: 'sw-settings-payment-gateway.general.mainMenuItemGeneral',
                icon: 'regular-plug',
                parent: 'sw-settings',
                position: 800,
                privilege: 'payment_app.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-payment-provider',
                groupName: 'payment',
                title: 'sw-settings-payment-provider.general.mainMenuItemGeneral',
                icon: 'regular-credit-card',
                parent: 'sw-settings',
                position: 810,
                privilege: 'payment_provider_config.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-payment',
                groupName: 'commerce',
                title: 'sw-settings-payment.general.mainMenuItemGeneral',
                icon: 'regular-credit-card',
                parent: 'sw-settings',
                position: 640,
                privilege: 'payment.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-position',
                groupName: 'general',
                title: 'sw-settings-position.general.mainMenuItemGeneral',
                icon: 'regular-briefcase',
                parent: 'sw-settings',
                position: 170,
                privilege: 'position.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-product-feature-sets',
                groupName: 'commerce',
                title: 'sw-settings-product-feature-sets.general.mainMenuItemGeneral',
                icon: 'regular-check-square',
                parent: 'sw-settings',
                position: 650,
                privilege: 'product_feature_sets.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-search',
                groupName: 'general',
                title: 'sw-settings-search.general.mainMenuItemGeneral',
                icon: 'regular-search',
                parent: 'sw-settings',
                position: 180,
                privilege: 'product_search_config.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-seo',
                groupName: 'content',
                title: 'sw-settings-seo.general.mainMenuItemGeneral',
                icon: 'regular-search',
                parent: 'sw-settings',
                position: 530,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-services',
                groupName: 'system',
                title: 'sw-settings-services.general.title',
                icon: 'regular-view-grid',
                parent: 'sw-settings',
                position: 740,
                privilege: 'system.plugin_maintain',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-shipping',
                groupName: 'commerce',
                title: 'sw-settings-shipping.general.mainMenuItemGeneral',
                icon: 'regular-truck',
                parent: 'sw-settings',
                position: 660,
                privilege: 'shipping.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-shopwell-updates',
                groupName: 'system',
                title: 'sw-settings-shopwell-updates.general.menuTitle',
                icon: 'regular-sync',
                parent: 'sw-settings',
                position: 750,
                privilege: 'system.core_update',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-sitemap',
                groupName: 'content',
                title: 'sw-settings-sitemap.general.mainMenuItemGeneral',
                icon: 'regular-sitemap',
                parent: 'sw-settings',
                position: 540,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-sms',
                groupName: 'system',
                title: 'sw-settings-sms.general.mainMenuItemGeneral',
                icon: 'regular-envelope',
                parent: 'sw-settings',
                position: 760,
                privilege: 'sms_gateway.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-snippet',
                groupName: 'localization',
                title: 'sw-settings-snippet.general.mainMenuItemGeneral',
                icon: 'regular-globe-stand',
                parent: 'sw-settings',
                position: 430,
                privilege: 'snippet.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-state-machine',
                groupName: 'general',
                title: 'sw-settings-state-machine.general.mainMenuItemGeneral',
                icon: 'regular-history',
                parent: 'sw-settings',
                position: 190,
                privilege: 'state_machine.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-store',
                groupName: 'system',
                title: 'sw-settings-store.general.mainMenuItemGeneral',
                icon: 'regular-laptop',
                parent: 'sw-settings',
                position: 770,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-tag',
                groupName: 'content',
                title: 'sw-settings-tag.general.mainMenuItemGeneral',
                icon: 'regular-tag',
                parent: 'sw-settings',
                position: 550,
                privilege: 'tag.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-tax',
                groupName: 'localization',
                title: 'sw-settings-tax.general.mainMenuItemGeneral',
                icon: 'regular-chart-pie',
                parent: 'sw-settings',
                position: 440,
                privilege: 'tax.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-units',
                groupName: 'general',
                title: 'sw-settings-units.general.mainMenuItemGeneral',
                icon: 'regular-box',
                parent: 'sw-settings',
                position: 200,
                privilege: 'scale_unit.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-usage-data',
                groupName: 'system',
                title: 'sw-settings-usage-data.general.mainMenuItemGeneral',
                icon: 'regular-shield',
                parent: 'sw-settings',
                position: 780,
                privilege: 'system.system_config',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-user-permission',
                groupName: 'system',
                title: 'sw-settings-user-permission.general.mainMenuItemGeneral',
                icon: 'regular-lock',
                parent: 'sw-settings',
                position: 790,
                privilege: 'users_and_permissions.viewer',
            ),

            // System
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-system',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-system.general.mainMenuItemSystem',
                icon: 'regular-user-shield',
                position: 95,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-system-user',
                title: 'sw-system.navigation.user',
                parent: 'sw-system',
                position: 10,
                privilege: 'users_and_permissions.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-system-role',
                title: 'sw-system.navigation.role',
                parent: 'sw-system',
                position: 20,
                privilege: 'users_and_permissions.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-system-menu',
                title: 'sw-system.navigation.menu',
                parent: 'sw-system',
                position: 30,
                privilege: 'admin_menu.viewer',
            ),
        ];
    }
}
