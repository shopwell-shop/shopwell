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
            // A membership purchase is an order the customer placed, so its queue belongs in the
            // order folder and not in the settings: an operator looking for "the subscription this
            // customer paid for" starts at the orders, and the settings are where the plans are
            // maintained.
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-membership-purchases',
                title: 'sw-membership.purchase.general.mainMenuItemGeneral',
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
                internalKey: 'sw-experience-studio',
                title: 'sw-experience-studio.general.mainMenuItemGeneral',
                icon: 'regular-palette',
                parent: 'sw-content',
                position: 10,
                privilege: 'experience_studio.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-media',
                title: 'sw-media.general.mainMenuItemGeneral',
                icon: 'regular-image',
                parent: 'sw-content',
                position: 20,
                privilege: 'media.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-blog',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-blog.general.mainMenuItemGeneral',
                icon: 'regular-file-text',
                parent: 'sw-content',
                position: 30,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-blog-content',
                title: 'sw-blog.navigation.content',
                parent: 'sw-blog',
                position: 10,
                privilege: 'blog.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-blog-category',
                title: 'sw-blog.general.mainMenuItemCategory',
                parent: 'sw-blog',
                position: 20,
                privilege: 'blog.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-blog-author',
                title: 'sw-blog.general.mainMenuItemAuthor',
                parent: 'sw-blog',
                position: 30,
                privilege: 'blog.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-blog-comment',
                title: 'sw-blog.general.mainMenuItemComment',
                parent: 'sw-blog',
                position: 40,
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
                internalKey: 'sw.payment.index',
                title: 'sw-payment.general.mainMenuItemPaymentOrders',
                parent: 'sw-finance',
                position: 10,
                privilege: 'payment_order.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw.payment.refund',
                title: 'sw-payment.general.mainMenuItemRefunds',
                parent: 'sw-finance',
                position: 20,
                privilege: 'payment_refund.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-finance-transfer',
                title: 'sw-finance.navigation.transfer',
                parent: 'sw-finance',
                position: 30,
                privilege: 'payment_transfer.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-finance-wallet-transactions',
                title: 'sw-finance.navigation.walletTransactions',
                parent: 'sw-finance',
                position: 40,
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
            // Workflow
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-workflow',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-workflow.navigation.root',
                icon: 'regular-flow',
                position: 72,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-workflow-design',
                title: 'sw-workflow.navigation.design',
                parent: 'sw-workflow',
                position: 10,
                privilege: 'workflow.viewer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-workflow-tasks',
                title: 'sw-workflow.runtime.navigation',
                icon: 'regular-check-circle',
                parent: 'sw-workflow',
                position: 25,
                privilege: 'workflow_participant.viewer',
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
                title: 'sw-settings-rule.general.mainMenuItemGeneral',
                icon: 'regular-rule',
                parent: 'sw-settings',
                position: 300,
                privilege: 'rule.viewer',
                groupName: 'automation',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-basic-information',
                title: 'sw-settings-basic-information.general.mainMenuItemGeneral',
                icon: 'regular-bars',
                parent: 'sw-settings',
                position: 100,
                privilege: 'system.system_config',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-cache',
                title: 'sw-settings-cache.general.mainMenuItemGeneral',
                icon: 'regular-files',
                parent: 'sw-settings',
                position: 700,
                privilege: 'system.clear_cache',
                groupName: 'system',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-cart',
                title: 'sw-settings-cart.general.mainMenuItemGeneral',
                icon: 'regular-shopping-cart',
                parent: 'sw-settings',
                position: 110,
                privilege: 'system.system_config',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-country',
                title: 'sw-settings-country.general.mainMenuItemGeneral',
                icon: 'regular-map',
                parent: 'sw-settings',
                position: 400,
                privilege: 'country.viewer',
                groupName: 'localization',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-currency',
                title: 'sw-settings-currency.general.mainMenuItemGeneral',
                icon: 'regular-euro',
                parent: 'sw-settings',
                position: 410,
                privilege: 'currencies.viewer',
                groupName: 'localization',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-custom-field',
                title: 'sw-settings-custom-field.general.mainMenuItemGeneral',
                icon: 'regular-bars-square',
                parent: 'sw-settings',
                position: 500,
                privilege: 'custom_field.viewer',
                groupName: 'content',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-customer-group',
                title: 'sw-settings-customer-group.general.mainMenuItemGeneral',
                icon: 'regular-users',
                parent: 'sw-settings',
                position: 200,
                privilege: 'customer_groups.viewer',
                groupName: 'customer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-membership',
                title: 'sw-membership.general.mainMenuItemGeneral',
                icon: 'regular-star',
                parent: 'sw-settings',
                position: 220,
                privilege: 'loyalty.viewer',
                groupName: 'customer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-membership-plans',
                title: 'sw-membership.plan.general.mainMenuItemGeneral',
                icon: 'regular-shopping-bag',
                parent: 'sw-settings',
                position: 230,
                privilege: 'loyalty.viewer',
                groupName: 'customer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-delivery-times',
                title: 'sw-settings-delivery-time.general.mainMenuItemGeneral',
                icon: 'regular-clock',
                parent: 'sw-settings',
                position: 600,
                privilege: 'delivery_times.viewer',
                groupName: 'commerce',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-dictionary',
                title: 'sw-settings-dictionary.general.mainMenuItemGeneral',
                icon: 'regular-layer-group',
                parent: 'sw-settings',
                position: 120,
                privilege: 'dictionary.viewer',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-document',
                title: 'sw-settings-document.general.mainMenuItemGeneral',
                icon: 'regular-file-text',
                parent: 'sw-settings',
                position: 610,
                privilege: 'document.viewer',
                groupName: 'commerce',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-language',
                title: 'sw-settings-language.general.mainMenuItemGeneral',
                icon: 'regular-flag',
                parent: 'sw-settings',
                position: 420,
                privilege: 'language.viewer',
                groupName: 'localization',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-listing',
                title: 'sw-settings-listing.general.mainMenuItemGeneral',
                icon: 'regular-products',
                parent: 'sw-settings',
                position: 620,
                privilege: 'system.system_config',
                groupName: 'commerce',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-logging',
                title: 'sw-settings-logging.general.mainMenuItemGeneral',
                icon: 'regular-server',
                parent: 'sw-settings',
                position: 710,
                privilege: 'system.logging',
                groupName: 'system',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-login-registration',
                title: 'sw-settings-login-registration.general.mainMenuItemGeneral',
                icon: 'regular-sign-in',
                parent: 'sw-settings',
                position: 210,
                privilege: 'system.system_config',
                groupName: 'customer',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-mailer',
                title: 'sw-settings-mailer.general.mainMenuItemGeneral',
                icon: 'regular-envelope',
                parent: 'sw-settings',
                position: 720,
                privilege: 'system.system_config',
                groupName: 'system',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-measurement',
                title: 'sw-settings-measurement.general.mainMenuItemGeneral',
                icon: 'regular-balance-scale',
                parent: 'sw-settings',
                position: 130,
                privilege: 'system.system_config',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-media',
                title: 'sw-settings-media.general.title',
                icon: 'regular-image',
                parent: 'sw-settings',
                position: 510,
                privilege: 'system.system_config',
                groupName: 'content',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-message-stats',
                title: 'sw-settings-message-stats.general.mainMenuItemGeneral',
                icon: 'regular-bars-square',
                parent: 'sw-settings',
                position: 730,
                privilege: 'system.system_config',
                groupName: 'system',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-newsletter',
                title: 'sw-settings-newsletter.general.mainMenuItemGeneral',
                icon: 'regular-inbox',
                parent: 'sw-settings',
                position: 520,
                privilege: 'system.system_config',
                groupName: 'content',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-number-range',
                title: 'sw-settings-number-range.general.mainMenuItemGeneral',
                icon: 'regular-file-signature',
                parent: 'sw-settings',
                position: 140,
                privilege: 'number_ranges.viewer',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-oauth2-login',
                title: 'sw-settings-oauth2-login.general.mainMenuItemGeneral',
                icon: 'regular-key',
                parent: 'sw-settings',
                position: 150,
                privilege: 'oauth2_client.viewer',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-order-return',
                title: 'sw-settings-order-return.general.mainMenuItemGeneral',
                icon: 'regular-undo',
                parent: 'sw-settings',
                position: 630,
                privilege: 'order_return.viewer',
                groupName: 'commerce',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-organization',
                title: 'sw-settings-organization.general.mainMenuItemGeneral',
                icon: 'regular-officebuilding',
                parent: 'sw-settings',
                position: 160,
                privilege: 'organization.viewer',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-payment-gateway',
                title: 'sw-settings-payment-gateway.general.mainMenuItemGeneral',
                icon: 'regular-plug',
                parent: 'sw-settings',
                position: 800,
                privilege: 'payment_app.viewer',
                groupName: 'payment',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-payment-provider',
                title: 'sw-settings-payment-provider.general.mainMenuItemGeneral',
                icon: 'regular-credit-card',
                parent: 'sw-settings',
                position: 810,
                privilege: 'payment_account.viewer',
                groupName: 'payment',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-payment',
                title: 'sw-settings-payment.general.mainMenuItemGeneral',
                icon: 'regular-credit-card',
                parent: 'sw-settings',
                position: 640,
                privilege: 'payment.viewer',
                groupName: 'commerce',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-position',
                title: 'sw-settings-position.general.mainMenuItemGeneral',
                icon: 'regular-briefcase',
                parent: 'sw-settings',
                position: 170,
                privilege: 'position.viewer',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-product-feature-sets',
                title: 'sw-settings-product-feature-sets.general.mainMenuItemGeneral',
                icon: 'regular-check-square',
                parent: 'sw-settings',
                position: 650,
                privilege: 'product_feature_sets.viewer',
                groupName: 'commerce',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-search',
                title: 'sw-settings-search.general.mainMenuItemGeneral',
                icon: 'regular-search',
                parent: 'sw-settings',
                position: 180,
                privilege: 'product_search_config.viewer',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-seo',
                title: 'sw-settings-seo.general.mainMenuItemGeneral',
                icon: 'regular-search',
                parent: 'sw-settings',
                position: 530,
                privilege: 'system.system_config',
                groupName: 'content',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-services',
                title: 'sw-settings-services.general.title',
                icon: 'regular-view-grid',
                parent: 'sw-settings',
                position: 740,
                privilege: 'system.plugin_maintain',
                groupName: 'system',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-shipping',
                title: 'sw-settings-shipping.general.mainMenuItemGeneral',
                icon: 'regular-truck',
                parent: 'sw-settings',
                position: 660,
                privilege: 'shipping.viewer',
                groupName: 'commerce',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-shopwell-updates',
                title: 'sw-settings-shopwell-updates.general.menuTitle',
                icon: 'regular-sync',
                parent: 'sw-settings',
                position: 750,
                privilege: 'system.core_update',
                groupName: 'system',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-sitemap',
                title: 'sw-settings-sitemap.general.mainMenuItemGeneral',
                icon: 'regular-sitemap',
                parent: 'sw-settings',
                position: 540,
                privilege: 'system.system_config',
                groupName: 'content',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-sms',
                title: 'sw-settings-sms.general.mainMenuItemGeneral',
                icon: 'regular-envelope',
                parent: 'sw-settings',
                position: 760,
                privilege: 'sms_gateway.viewer',
                groupName: 'system',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-snippet',
                title: 'sw-settings-snippet.general.mainMenuItemGeneral',
                icon: 'regular-globe-stand',
                parent: 'sw-settings',
                position: 430,
                privilege: 'snippet.viewer',
                groupName: 'localization',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-state-machine',
                title: 'sw-settings-state-machine.general.mainMenuItemGeneral',
                icon: 'regular-history',
                parent: 'sw-settings',
                position: 190,
                privilege: 'state_machine.viewer',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-store',
                title: 'sw-settings-store.general.mainMenuItemGeneral',
                icon: 'regular-laptop',
                parent: 'sw-settings',
                position: 770,
                privilege: 'system.system_config',
                groupName: 'system',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-tag',
                title: 'sw-settings-tag.general.mainMenuItemGeneral',
                icon: 'regular-tag',
                parent: 'sw-settings',
                position: 550,
                privilege: 'tag.viewer',
                groupName: 'content',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-tax',
                title: 'sw-settings-tax.general.mainMenuItemGeneral',
                icon: 'regular-chart-pie',
                parent: 'sw-settings',
                position: 440,
                privilege: 'tax.viewer',
                groupName: 'localization',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-units',
                title: 'sw-settings-units.general.mainMenuItemGeneral',
                icon: 'regular-box',
                parent: 'sw-settings',
                position: 200,
                privilege: 'scale_unit.viewer',
                groupName: 'general',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-usage-data',
                title: 'sw-settings-usage-data.general.mainMenuItemGeneral',
                icon: 'regular-shield',
                parent: 'sw-settings',
                position: 780,
                privilege: 'system.system_config',
                groupName: 'system',
            ),

            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-user-permission',
                title: 'sw-settings-user-permission.general.mainMenuItemGeneral',
                icon: 'regular-lock',
                parent: 'sw-settings',
                position: 790,
                privilege: 'user.viewer',
                groupName: 'system',
            ),

            // System
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-system',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'global.sw-admin-menu.navigation.mainMenuItemSystem',
                icon: 'regular-user-shield',
                position: 95,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-user',
                title: 'sw-user.general.label',
                parent: 'sw-system',
                position: 10,
                privilege: 'user.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-permissions-role',
                title: 'sw-permissions.roles.general.label',
                parent: 'sw-system',
                position: 20,
                privilege: 'permissions.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-permissions-menu',
                title: 'sw-permissions.menu.headline',
                parent: 'sw-system',
                position: 30,
                privilege: 'admin_menu.viewer',
            ),
        ];
    }
}
