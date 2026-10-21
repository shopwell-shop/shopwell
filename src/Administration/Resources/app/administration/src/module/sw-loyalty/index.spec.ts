/**
 * @sw-package checkout
 */

import './index';

jest.mock('./acl', () => jest.fn());
jest.mock('./service', () => ({}));

const { Module, Component } = Shopwell;

describe('src/module/sw-loyalty/index', () => {
    it('registers the pages of the tiers, the plans and the orders', () => {
        const components = Component.getComponentRegistry();

        [
            'sw-loyalty-tier-list',
            'sw-loyalty-tier-detail',
            'sw-loyalty-tier-create',
            'sw-loyalty-plan-list',
            'sw-loyalty-plan-detail',
            'sw-loyalty-plan-create',
            'sw-loyalty-order-list',
        ].forEach((component) => {
            expect(components.has(component)).toBe(true);
        });
    });

    it('registers the module under the customers group of the settings', () => {
        const module = Module.getModuleRegistry().get('sw-settings-loyalty');

        expect(module).toBeTruthy();

        const settingsItems = (module?.manifest.settingsItem ?? []) as Array<{
            id: string;
            group?: string;
            privilege?: string;
        }>;

        expect(settingsItems.map((item) => item.id)).toEqual([
            'sw-settings-loyalty',
            'sw-settings-loyalty-plans',
            'sw-settings-loyalty-orders',
        ]);

        settingsItems.forEach((item) => {
            expect(item.group).toBe('customer');
            expect(item.privilege).toBe('loyalty.viewer');
        });
    });

    it('registers the tier, plan and order routes', () => {
        const routes = Module.getModuleRegistry().get('sw-settings-loyalty')?.routes;

        [
            'sw.settings.loyalty.index',
            'sw.settings.loyalty.detail',
            'sw.settings.loyalty.create',
            'sw.settings.loyalty.plans',
            'sw.settings.loyalty.planDetail',
            'sw.settings.loyalty.planCreate',
            'sw.settings.loyalty.orders',
        ].forEach((route) => {
            expect(routes?.has(route)).toBe(true);
        });
    });
});
