/**
 * @sw-package after-sales
 */

import { orderDetailReturnTab } from './index';

type Tab = {
    label: string;
    name: string;
    onClick: () => void;
};

type TabContext = {
    orderId: string;
    returnTabRoute: { name: string; params: { id: string } };
    acl: { can: (privilege: string) => boolean };
    $super: (name: string) => Tab[];
    $router: { push: (location: unknown) => Promise<unknown> };
    $t: (key: string) => string;
};

const createContext = (privileges: string[]): TabContext => ({
    orderId: 'order-id',
    returnTabRoute: { name: 'sw.order.detail.returns', params: { id: 'order-id' } },
    acl: { can: (privilege: string) => privileges.includes(privilege) },
    $super: () => [
        { label: 'general', name: 'sw.order.detail.general', onClick: () => {} },
        { label: 'documents', name: 'sw.order.detail.documents', onClick: () => {} },
    ],
    $router: { push: jest.fn(() => Promise.resolve()) },
    $t: (key: string) => key,
});

describe('src/module/sw-order/page/sw-order-detail/extension/sw-order-detail/index', () => {
    it('ships the template of the page with the added tab', () => {
        expect(orderDetailReturnTab.template).toBeDefined();
    });

    it('builds the returns route of the order', () => {
        const route = orderDetailReturnTab.computed.returnTabRoute.call(createContext([]));

        expect(route).toEqual({ name: 'sw.order.detail.returns', params: { id: 'order-id' } });
    });

    it('keeps the tabs of the page for a role that may not see returns', () => {
        const tabs = orderDetailReturnTab.computed.orderDetailTabs.call(createContext([]));

        expect(tabs.map((tab: Tab) => tab.name)).toEqual(['sw.order.detail.general', 'sw.order.detail.documents']);
    });

    it('appends the returns tab behind the tabs of the page for a viewer', () => {
        const tabs = orderDetailReturnTab.computed.orderDetailTabs.call(createContext(['order_return.viewer']));

        expect(tabs.map((tab: Tab) => tab.name)).toEqual([
            'sw.order.detail.general',
            'sw.order.detail.documents',
            'sw.order.detail.returns',
        ]);
    });

    it('navigates into the returns of the order when the tab is clicked', () => {
        const context = createContext(['order_return.viewer']);

        const tabs = orderDetailReturnTab.computed.orderDetailTabs.call(context);
        tabs[2].onClick();

        expect(context.$router.push).toHaveBeenCalledWith({
            name: 'sw.order.detail.returns',
            params: { id: 'order-id' },
        });
    });
});
