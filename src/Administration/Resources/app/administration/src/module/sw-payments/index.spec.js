/**
 * @sw-package checkout
 */

import './index';

jest.mock('./acl', () => jest.fn());

const { Module } = Shopwell;

describe('src/module/sw-payments/index.ts', () => {
    it('should register module base information', () => {
        const module = Module.getModuleRegistry().get('sw-payments');
        expect(module).toBeDefined();

        expect(module.manifest).toEqual({
            type: 'core',
            name: 'shopwell-payments',
            title: 'sw-payments.general.mainMenuItemGeneral',
            description: 'sw-payments.general.description',
            version: '1.0.0',
            targetVersion: '1.0.0',
            color: '#FFBC51',
            icon: 'solid-shopwell-payments',
            routes: expect.any(Object),
            navigation: [
                {
                    id: 'sw-payments',
                    label: 'global.sw-admin-menu.navigation.mainMenuItemShopwellPayments',
                    color: '#FFBC51',
                    icon: 'regular-shopwell-payments',
                    moduleType: 'core',
                    position: 35,
                    privilege: 'sw-payments.viewer',
                },
            ],
            display: true,
        });
    });

    it('should register module routes', () => {
        const register = Module.getModuleRegistry().get('sw-payments').routes;
        expect(register).toBeDefined();

        expect(register.size).toBe(1);

        const route = register.get('sw.payments.index');
        expect(route.path).toBe('/sw/payments/index');
    });
});
