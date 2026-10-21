/**
 * @sw-package after-sales
 */

import './index';

jest.mock('./acl', () => jest.fn());

const { Module, Component } = Shopwell;

type RouteMeta = {
    privilege?: string;
    parentPath?: string;
};

describe('src/module/sw-order-return/index', () => {
    it('registers the two pages and the decline modal of the queue', () => {
        const components = Component.getComponentRegistry();

        ['sw-order-return-list', 'sw-order-return-detail', 'sw-order-return-decline-modal'].forEach((component) => {
            expect(components.has(component)).toBe(true);
        });
    });

    it('registers the module with its own entity', () => {
        const module = Module.getModuleRegistry().get('sw-order-return');

        expect(module).toBeTruthy();
        expect(module?.manifest.entity).toBe('order_return');
    });

    it('registers the list and the detail route, both behind the viewer privilege', () => {
        const module = Module.getModuleRegistry().get('sw-order-return');
        const routes = module?.routes;

        expect(routes?.has('sw.order.return.list')).toBe(true);
        expect(routes?.has('sw.order.return.detail')).toBe(true);

        const listRoute = routes?.get('sw.order.return.list') as { meta?: RouteMeta } | undefined;
        const detailRoute = routes?.get('sw.order.return.detail') as { meta?: RouteMeta } | undefined;

        expect(listRoute?.meta?.privilege).toBe('order_return.viewer');
        expect(detailRoute?.meta?.privilege).toBe('order_return.viewer');
        expect(detailRoute?.meta?.parentPath).toBe('sw.order.return.list');
    });

    it('lowers the id of the route into the prop of the page', () => {
        const route = Module.getModuleRegistry().get('sw-order-return')?.routes?.get('sw.order.return.detail');
        const props = route?.props as { default: (route: { params: Record<string, string> }) => { orderReturnId: string } };

        expect(props.default({ params: { id: 'ABCDEF0123456789ABCDEF0123456789' } })).toEqual({
            orderReturnId: 'abcdef0123456789abcdef0123456789',
        });
    });

    it('puts the queue between the customers and the content modules', () => {
        const navigation = Module.getModuleRegistry().get('sw-order-return')?.manifest.navigation ?? [];

        const entry = navigation.find((item) => item.id === 'sw-order-return');
        const listEntry = navigation.find((item) => item.id === 'sw-order-return-list');

        expect(entry?.position).toBe(45);
        expect(listEntry?.parent).toBe('sw-order-return');
        expect(listEntry?.path).toBe('sw.order.return.list');
        expect(listEntry?.privilege).toBe('order_return.viewer');
    });
});
