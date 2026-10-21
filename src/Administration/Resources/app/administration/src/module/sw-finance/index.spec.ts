/**
 * @sw-package framework
 */

import './index';

jest.mock('./acl', () => jest.fn());

const { Module, Component } = Shopwell;

describe('src/module/sw-finance/index', () => {
    it('registers the pages and the modals of the module', () => {
        const components = Component.getComponentRegistry();

        [
            'sw-finance-transfer-list',
            'sw-finance-transfer-detail',
            'sw-finance-wallet-transactions-list',
            'sw-finance-transfer-create-modal',
            'sw-finance-transfer-approve-modal',
            'sw-finance-transfer-reject-modal',
        ].forEach((component) => {
            expect(components.has(component)).toBe(true);
        });
    });

    it('registers the module between the automation module and the extension store', () => {
        const module = Module.getModuleRegistry().get('sw-finance');

        expect(module).toBeTruthy();

        const navigation = module?.manifest.navigation ?? [];

        expect(navigation.map((entry) => entry.id)).toEqual([
            'sw-finance',
            'sw-finance-transfer',
            'sw-finance-wallet-transactions',
        ]);

        // the automation module sits on 70, the extension store on 80
        expect(navigation[0]?.position).toBe(75);

        // neither child may hide the entry of a role that only holds one of the two privileges
        expect(navigation[0]?.privilege).toBeUndefined();
    });

    it('registers the transfer and wallet transaction routes', () => {
        const routes = Module.getModuleRegistry().get('sw-finance')?.routes;

        expect(routes?.has('sw.finance.transfer')).toBe(true);
        expect(routes?.has('sw.finance.transferDetail')).toBe(true);
        expect(routes?.has('sw.finance.walletTransactions')).toBe(true);
    });
});
