/**
 * @sw-package checkout
 */

import { customerDetailExtension } from './index';

type Tab = {
    label: string;
    name: string;
    onClick: () => void;
};

type TabContext = {
    customerId: string;
    editMode: boolean;
    loyaltyRoute: { name: string; params: { id: string }; query: { edit: boolean } };
    walletRoute: { name: string; params: { id: string }; query: { edit: boolean } };
    acl: { can: (privilege: string) => boolean };
    $super: (name: string) => Tab[];
    $router: { push: (location: unknown) => Promise<unknown> };
    $t: (key: string) => string;
};

const createContext = (privileges: string[]): TabContext => ({
    customerId: 'customer-id',
    editMode: true,
    loyaltyRoute: { name: 'sw.customer.detail.loyalty', params: { id: 'customer-id' }, query: { edit: true } },
    walletRoute: { name: 'sw.customer.detail.wallet', params: { id: 'customer-id' }, query: { edit: true } },
    acl: { can: (privilege: string) => privileges.includes(privilege) },
    $super: () => [{ label: 'base', name: 'base', onClick: () => {} }],
    $router: { push: jest.fn(() => Promise.resolve()) },
    $t: (key: string) => key,
});

describe('src/module/sw-customer/page/sw-customer-detail/extension/sw-customer-detail/index', () => {
    it('ships the template of the page with the added tabs', () => {
        expect(customerDetailExtension.template).toBeDefined();
    });

    it('builds the loyalty route of the customer', () => {
        const route = customerDetailExtension.computed.loyaltyRoute.call(createContext([]));

        expect(route).toEqual({
            name: 'sw.customer.detail.loyalty',
            params: { id: 'customer-id' },
            query: { edit: true },
        });
    });

    it('builds the wallet route of the customer', () => {
        const route = customerDetailExtension.computed.walletRoute.call(createContext([]));

        expect(route).toEqual({
            name: 'sw.customer.detail.wallet',
            params: { id: 'customer-id' },
            query: { edit: true },
        });
    });

    it('adds no tab to a role that holds neither privilege', () => {
        const tabs = customerDetailExtension.computed.customerDetailTabs.call(createContext([]));

        expect(tabs).toHaveLength(1);
        expect(tabs[0].name).toBe('base');
    });

    it('adds the points tab for a loyalty viewer', () => {
        const tabs = customerDetailExtension.computed.customerDetailTabs.call(createContext(['loyalty.viewer']));

        expect(tabs.map((tab: Tab) => tab.name)).toEqual(['base', 'sw.customer.detail.loyalty']);
    });

    it('adds the wallet tab for a wallet viewer', () => {
        const tabs = customerDetailExtension.computed.customerDetailTabs.call(createContext(['wallet.viewer']));

        expect(tabs.map((tab: Tab) => tab.name)).toEqual(['base', 'sw.customer.detail.wallet']);
    });

    it('adds both tabs for a role that holds both privileges', () => {
        const tabs = customerDetailExtension.computed.customerDetailTabs.call(
            createContext(['loyalty.viewer', 'wallet.viewer']),
        );

        expect(tabs.map((tab: Tab) => tab.name)).toEqual([
            'base',
            'sw.customer.detail.loyalty',
            'sw.customer.detail.wallet',
        ]);
    });

    it('navigates to the tab that was clicked', () => {
        const context = createContext(['loyalty.viewer']);

        const tabs = customerDetailExtension.computed.customerDetailTabs.call(context);
        tabs[1].onClick();

        expect(context.$router.push).toHaveBeenCalledWith(context.loyaltyRoute);
    });
});
