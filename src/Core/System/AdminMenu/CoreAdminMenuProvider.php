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
                position: 35,
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

            // Automation
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-automation',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'global.sw-admin-menu.navigation.mainMenuItemAutomation',
                icon: 'regular-rule',
                position: 70,
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings-rule',
                title: 'sw-settings-rule.general.mainMenuItemGeneral',
                icon: 'regular-rule',
                parent: 'sw-automation',
                position: 10,
                privilege: 'rule.viewer',
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

            // Extensions
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-extension',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-extension.mainMenu.mainMenuItemExtensionStore',
                icon: 'regular-plug',
                position: 80,
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
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-extension-sdk',
                type: AdminMenuItemDefinition::TYPE_FOLDER,
                title: 'sw-extension-sdk.general.mainMenuItemGeneral',
                icon: 'regular-view-grid',
                position: 110,
            ),

            // Settings
            new AdminMenuDefinitionStruct(
                internalKey: 'sw-settings',
                title: 'sw-settings.general.mainMenuItemGeneral',
                icon: 'regular-cog',
                position: 90,
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
                internalKey: 'sw.system.user',
                title: 'sw-system.navigation.user',
                parent: 'sw-system',
                position: 10,
                privilege: 'users_and_permissions.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw.system.role',
                title: 'sw-system.navigation.role',
                parent: 'sw-system',
                position: 20,
                privilege: 'users_and_permissions.viewer',
            ),
            new AdminMenuDefinitionStruct(
                internalKey: 'sw.system.menu',
                title: 'sw-system.navigation.menu',
                parent: 'sw-system',
                position: 30,
                privilege: 'admin_menu.viewer',
            ),
        ];
    }
}
