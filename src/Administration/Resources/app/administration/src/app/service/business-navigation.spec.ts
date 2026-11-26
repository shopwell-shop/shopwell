/**
 * @sw-package framework
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import createMenuService from './menu.service';
import type { NavigationEntryLike } from '../component/structure/sw-admin-menu/merge-navigation.helper';
import PrivilegesService from './privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

const modules = [
    'sw-approval-flow',
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
    'sw-organization',
    'sw-settings-payment',
    'sw-settings-payment-gateway',
    'sw-settings-payment-provider',
    'sw-position',
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
];

describe('business navigation with approval and content articles', () => {
    beforeAll(async () => {
        for (const name of modules) {
            await import(`src/module/${name}`);
        }
    });

    function bindings(): NavigationEntryLike[] {
        const menu = new createMenuService(Shopwell.Module) as {
            getNavigationFromAdminModules(): NavigationEntryLike[];
        };
        return menu.getNavigationFromAdminModules();
    }

    function entries(): NavigationEntryLike[] {
        const file = resolve(__dirname, '../../../../../../../Core/System/AdminMenu/Resources/config/admin-menu.xml');
        const xml = new DOMParser().parseFromString(readFileSync(file, 'utf8'), 'application/xml');
        return Array.from(xml.getElementsByTagName('menu')).map((menu) => {
            const id = menu.getAttribute('id')!;
            const binding = bindings().find((entry) => entry.id === id || entry.path === id);
            let parent = menu.parentElement;
            while (parent?.getAttribute('type') === 'group') parent = parent.parentElement;
            return {
                ...binding,
                id,
                label: Array.from(menu.children).find((child) => child.tagName === 'title')?.textContent,
                parent:
                    parent?.tagName === 'menu'
                        ? (parent.getAttribute('id') ?? undefined)
                        : (menu.getAttribute('parent') ?? undefined),
                position: Number(menu.getAttribute('position') ?? 0),
                privilege: menu.getAttribute('privilege') ?? undefined,
                type: menu.getAttribute('type') ?? 'menu',
            };
        });
    }

    it('binds every XML page to a registered route with the matching navigation permission', () => {
        const registeredRoutes = new Set(
            Array.from(Shopwell.Module.getModuleRegistry().values()).flatMap((module) => Array.from(module.routes.keys())),
        );
        for (const entry of entries().filter((item) => item.type === 'menu')) {
            const binding = bindings().find((item) => item.id === entry.id || item.path === entry.id);
            expect(binding, entry.id).toBeDefined();
            expect(registeredRoutes.has(binding?.path ?? '')).toBe(true);
        }
        expect(
            entries()
                .filter((entry) => entry.type === 'menu')
                .flatMap((entry) => {
                    const binding = bindings().find((item) => item.id === entry.id || item.path === entry.id);
                    return binding?.privilege === entry.privilege
                        ? []
                        : [{ id: entry.id, xml: entry.privilege, frontend: binding?.privilege }];
                }),
        ).toEqual([]);
        for (const binding of bindings()) {
            expect(entries().some((entry) => entry.id === binding.id || entry.id === binding.path)).toBe(true);
            expect(binding.label).toBeUndefined();
            expect(binding.parent).toBeUndefined();
            expect(binding.position).toBeUndefined();
        }
    });

    it('retains the business roots and independent approval', () => {
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
                'sw-approval-flow',
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

    it('keeps all article tools below Content and approval tools in their own root', () => {
        const navigation = entries();
        expect(navigation.filter((entry) => entry.parent === 'sw-blog').map((entry) => entry.id)).toEqual([
            'sw-blog-content',
            'sw-blog-category',
            'sw-blog-author',
            'sw-blog-comment',
        ]);
        expect(navigation.find((entry) => entry.id === 'sw-approval-flow')?.parent).toBeUndefined();
    });

    it('places organization and positions under System without settings cards', () => {
        for (const [id, path, privilege] of [
            ['sw-organization', 'sw.organization.index', 'organization.viewer'],
            ['sw-position', 'sw.position.index', 'position.viewer'],
        ]) {
            expect(entries().find((entry) => entry.id === id)).toEqual(
                expect.objectContaining({ parent: 'sw-system', path, privilege }),
            );
            expect(Shopwell.Module.getModuleRegistry().get(id)?.manifest.settingsItem).toBeUndefined();
        }
    });

    it('retains the original settings entries and grouping declarations', () => {
        const navigation = entries();
        for (const id of [
            'sw-settings-rule',
            'sw-settings-state-machine',
            'sw-settings-oauth2-login',
            'sw-settings-payment-provider',
        ]) {
            expect(navigation.find((entry) => entry.id === id)?.parent).toBe('sw-settings');
        }
        expect(navigation.find((entry) => entry.id === 'sw-newsletter-recipient')?.label).toBe('Newsletter recipients');
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
