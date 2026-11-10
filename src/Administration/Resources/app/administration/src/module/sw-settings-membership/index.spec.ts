/**
 * @sw-package checkout
 */

import './index';

jest.mock('./acl', () => jest.fn());
jest.mock('./service', () => ({}));

const { Module, Component } = Shopwell;

describe('src/module/sw-settings-membership/index', () => {
    it('registers the pages of the tiers and the plans', () => {
        const components = Component.getComponentRegistry();

        [
            'sw-membership-tier-list',
            'sw-membership-tier-detail',
            'sw-membership-tier-create',
            'sw-membership-plan-list',
            'sw-membership-plan-detail',
            'sw-membership-plan-create',
        ].forEach((component) => {
            expect(components.has(component)).toBe(true);
        });

        // the purchases live in their own module next to the sales orders, not in the settings
        expect(components.has('sw-membership-purchase-list')).toBe(false);
    });

    it('registers the module under the customers group of the settings', () => {
        const module = Module.getModuleRegistry().get('sw-settings-membership');

        expect(module).toBeTruthy();

        const settingsItems = (module?.manifest.settingsItem ?? []) as Array<{
            id: string;
            group?: string;
            privilege?: string;
        }>;

        expect(settingsItems.map((item) => item.id)).toEqual([
            'sw-settings-membership',
            'sw-settings-membership-plans',
        ]);

        settingsItems.forEach((item) => {
            expect(item.group).toBe('customer');
            expect(item.privilege).toBe('loyalty.viewer');
        });
    });

    it('keeps the purchases out of the settings navigation', () => {
        const module = Module.getModuleRegistry().get('sw-settings-membership');

        expect(module?.manifest.navigation ?? []).not.toEqual(
            expect.arrayContaining([expect.objectContaining({ path: 'sw.membership.purchases' })]),
        );
    });

    it('registers the tier and plan routes', () => {
        const routes = Module.getModuleRegistry().get('sw-settings-membership')?.routes;

        [
            'sw.settings.membership.index',
            'sw.settings.membership.detail',
            'sw.settings.membership.create',
            'sw.settings.membership.plans',
            'sw.settings.membership.planDetail',
            'sw.settings.membership.planCreate',
        ].forEach((route) => {
            expect(routes?.has(route)).toBe(true);
        });

        expect(routes?.has('sw.settings.membership.purchases')).toBe(false);
    });

    it.each([
        ['sw-settings-membership', 'sw.settings.membership.index'],
        ['sw-settings-membership-plans', 'sw.settings.membership.plans'],
    ])('exposes %s in navigation and customer settings with the same access requirement', (id, path) => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-membership');

        expect(module?.routes?.has(path)).toBe(true);
        expect(module?.manifest.navigation).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id, parent: 'sw-settings', path, privilege: 'loyalty.viewer' }),
            ]),
        );
        expect(module?.manifest.settingsItem).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id, group: 'customer', to: path, privilege: 'loyalty.viewer' }),
            ]),
        );
    });
});
