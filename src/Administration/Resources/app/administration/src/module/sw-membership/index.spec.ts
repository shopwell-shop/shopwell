/**
 * @sw-package checkout
 */

import './index';

jest.mock('./acl', () => jest.fn());
jest.mock('./service', () => ({}));

const { Module, Component } = Shopwell;

describe('src/module/sw-membership/index', () => {
    it('registers the pages of the tiers, the plans and the orders', () => {
        const components = Component.getComponentRegistry();

        [
            'sw-membership-tier-list',
            'sw-membership-tier-detail',
            'sw-membership-tier-create',
            'sw-membership-plan-list',
            'sw-membership-plan-detail',
            'sw-membership-plan-create',
            'sw-membership-order-list',
        ].forEach((component) => {
            expect(components.has(component)).toBe(true);
        });
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

    it('puts the membership orders next to the sales orders instead of into the settings', () => {
        const module = Module.getModuleRegistry().get('sw-settings-membership');

        const navigation = (module?.manifest.navigation ?? []) as Array<{
            id: string;
            path?: string;
            parent?: string;
            privilege?: string;
        }>;

        expect(navigation).toHaveLength(1);
        expect(navigation[0]).toMatchObject({
            id: 'sw-membership-orders',
            path: 'sw.settings.membership.orders',
            parent: 'sw-order',
            privilege: 'loyalty.viewer',
        });
    });

    it('registers the tier, plan and order routes', () => {
        const routes = Module.getModuleRegistry().get('sw-settings-membership')?.routes;

        [
            'sw.settings.membership.index',
            'sw.settings.membership.detail',
            'sw.settings.membership.create',
            'sw.settings.membership.plans',
            'sw.settings.membership.planDetail',
            'sw.settings.membership.planCreate',
            'sw.settings.membership.orders',
        ].forEach((route) => {
            expect(routes?.has(route)).toBe(true);
        });
    });
});
