/**
 * @sw-package framework
 */
import createMenuService from './menu.service';
import type { NavigationEntryLike } from '../component/structure/sw-admin-menu/merge-navigation.helper';
import PrivilegesService from './privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

const modules = [
    'sw-blog',
    'sw-category',
    'sw-customer',
    'sw-dashboard',
    'sw-experience-studio',
    'sw-extension',
    'sw-finance',
    'sw-first-run-wizard',
    'sw-flow',
    'sw-import-export',
    'sw-integration',
    'sw-mail-template',
    'sw-manufacturer',
    'sw-media',
    'sw-membership',
    'sw-newsletter-recipient',
    'sw-order',
    'sw-order-return',
    'sw-payment',
    'sw-permissions',
    'sw-product',
    'sw-product-stream',
    'sw-promotion-v2',
    'sw-property',
    'sw-review',
    'sw-sales-channel',
    'sw-settings',
    'sw-settings-basic-information',
    'sw-settings-cache',
    'sw-settings-cart',
    'sw-settings-country',
    'sw-settings-currency',
    'sw-settings-custom-field',
    'sw-settings-customer-group',
    'sw-settings-delivery-times',
    'sw-settings-dictionary',
    'sw-settings-document',
    'sw-settings-language',
    'sw-settings-listing',
    'sw-settings-logging',
    'sw-settings-login-registration',
    'sw-settings-mailer',
    'sw-settings-measurement',
    'sw-settings-media',
    'sw-settings-membership',
    'sw-settings-message-stats',
    'sw-settings-newsletter',
    'sw-settings-number-range',
    'sw-settings-oauth2-login',
    'sw-settings-order-return',
    'sw-settings-organization',
    'sw-settings-payment',
    'sw-settings-payment-gateway',
    'sw-settings-payment-provider',
    'sw-settings-position',
    'sw-settings-product-feature-sets',
    'sw-settings-rule',
    'sw-settings-search',
    'sw-settings-seo',
    'sw-settings-services',
    'sw-settings-shipping',
    'sw-settings-shopwell-updates',
    'sw-settings-sitemap',
    'sw-settings-sms',
    'sw-settings-snippet',
    'sw-settings-state-machine',
    'sw-settings-store',
    'sw-settings-tag',
    'sw-settings-tax',
    'sw-settings-units',
    'sw-settings-usage-data',
    'sw-settings-user-permission',
    'sw-user',
    'sw-workflow',
];

describe('original navigation with workflow and content articles', () => {
    beforeAll(async () => {
        for (const name of modules) {
            await import(`src/module/${name}`);
        }
    });

    function entries(): NavigationEntryLike[] {
        const menu = new createMenuService(Shopwell.Module) as {
            getNavigationFromAdminModules(): NavigationEntryLike[];
        };
        return menu.getNavigationFromAdminModules();
    }

    it('retains the original business roots and independent workflow', () => {
        expect(
            entries()
                .filter((entry) => !entry.parent)
                .map((entry) => entry.id)
                .sort(),
        ).toEqual(
            [
                'sw-dashboard',
                'sw-catalogue',
                'sw-order',
                'sw-customer',
                'sw-order-return',
                'sw-content',
                'sw-marketing',
                'sw-workflow',
                'sw-finance',
                'sw-extension',
                'sw-settings',
                'sw-system',
            ].sort(),
        );
        expect(entries().find((entry) => entry.id === 'sw-blog')?.parent).toBe('sw-content');
    });

    it('orders financial pages directly under one root with independent permissions', () => {
        const navigation = entries();
        expect(navigation.some((entry) => entry.id === 'sw-payment')).toBe(false);
        expect(navigation.find((entry) => entry.id === 'sw-finance')?.privilege).toBeUndefined();
        expect(
            navigation
                .filter((entry) => entry.parent === 'sw-finance')
                .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                .map((entry) => entry.path),
        ).toEqual([
            'sw.payment.index',
            'sw.payment.refund',
            'sw.finance.transfer',
            'sw.finance.walletTransactions',
        ]);
    });

    it('keeps all article tools below Content and workflow tools in their own root', () => {
        const navigation = entries();
        expect(navigation.filter((entry) => entry.parent === 'sw-blog').map((entry) => entry.id)).toEqual([
            'sw-blog-content',
            'sw-blog-category',
            'sw-blog-author',
            'sw-blog-comment',
        ]);
        expect(navigation.find((entry) => entry.id === 'sw-workflow')?.parent).toBeUndefined();
        expect(navigation.find((entry) => entry.id === 'sw-workflow-design')?.parent).toBe('sw-workflow');
        expect(navigation.filter((entry) => entry.parent === 'sw-workflow').map((entry) => entry.id)).toEqual([
            'sw-workflow-tasks',
            'sw-workflow-design',
        ]);
    });

    it('retains the original settings entries and grouping declarations', () => {
        const navigation = entries();
        for (const id of [
            'sw-settings-organization',
            'sw-settings-position',
            'sw-settings-rule',
            'sw-settings-state-machine',
            'sw-settings-oauth2-login',
            'sw-settings-payment-provider',
        ]) {
            expect(navigation.find((entry) => entry.id === id)?.parent).toBe('sw-settings');
        }
        expect(navigation.find((entry) => entry.id === 'sw-newsletter-recipient')?.label).toBe(
            'sw-newsletter-recipient.general.mainMenuItemGeneral',
        );
        expect(navigation.some((entry) => entry.id === 'sw-settings-security')).toBe(false);
        expect(navigation.find((entry) => entry.id === 'sw-user')?.parent).toBe('sw-system');
        expect(Shopwell.Module.getModuleRegistry().get('sw-flow')?.manifest.settingsItem).toEqual(
            expect.arrayContaining([expect.objectContaining({ group: 'automation', to: 'sw.flow.index' })]),
        );
        expect(Shopwell.Module.getModuleRegistry().get('sw-settings')?.routes.get('sw.settings.index')?.redirect).toEqual({
            name: 'sw.settings.index.shop',
        });
    });
});
