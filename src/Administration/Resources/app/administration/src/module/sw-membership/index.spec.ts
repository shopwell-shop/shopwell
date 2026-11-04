/**
 * @sw-package checkout
 */

import './index';

const { Module, Component } = Shopwell;

describe('src/module/sw-membership/index', () => {
    it('registers the purchase list page', () => {
        const components = Component.getComponentRegistry();

        expect(components.has('sw-membership-purchase-list')).toBe(true);
    });

    it('registers the module with the purchase as its entity', () => {
        const module = Module.getModuleRegistry().get('sw-membership');

        expect(module).toBeTruthy();
        expect(module?.manifest.entity).toBe('membership_purchase');
    });

    it('puts the membership purchases next to the sales orders instead of into the settings', () => {
        const module = Module.getModuleRegistry().get('sw-membership');

        const navigation = (module?.manifest.navigation ?? []) as Array<{
            id: string;
            path?: string;
            parent?: string;
            privilege?: string;
        }>;

        expect(navigation).toHaveLength(1);
        expect(navigation[0]).toMatchObject({
            id: 'sw-membership-purchases',
            path: 'sw.membership.purchases',
            parent: 'sw-order',
            privilege: 'loyalty.viewer',
        });
    });

    it('registers the purchase route without a foreign parent path', () => {
        const route = Module.getModuleRegistry().get('sw-membership')?.routes?.get('sw.membership.purchases');

        expect(route).toBeTruthy();

        const meta = route?.meta as { parentPath?: string; privilege?: string };

        // a parent path of the sales orders would highlight both entries of the orders menu at once
        expect(meta.parentPath).toBeUndefined();
        expect(meta.privilege).toBe('loyalty.viewer');
    });
});
