import { mount } from '@vue/test-utils';

const httpGet = jest.fn();

async function createWrapper(privileges = ['order.viewer']) {
    return mount(await wrapTestComponent('sw-dashboard-index', { sync: true }), {
        global: {
            stubs: {
                'mt-card': { template: '<article><slot/></article>' },
                'sw-page': await wrapTestComponent('sw-page'),
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-dashboard-metrics': {
                    name: 'sw-dashboard-metrics',
                    props: [
                        'summary',
                        'salesLoading',
                        'salesError',
                    ],
                    template: '<div />',
                },
                'sw-dashboard-statistics': true,
                'sw-dashboard-order-states': true,
                'sw-dashboard-latest-orders': true,
                'sw-extension-component-section': true,
                'sw-search-bar': true,
                'sw-app-topbar-button': true,
                'sw-app-topbar-sidebar': true,
                'sw-notification-center': true,
                'sw-help-center-v2': true,
                'router-link': true,
                'sw-app-actions': true,
                'sw-error-summary': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
            },
            mocks: {
                $t: (key) => key,
                $route: {
                    meta: {
                        $module: {},
                    },
                },
            },
            provide: {
                acl: { can: (privilege) => privileges.includes(privilege) },
                repositoryFactory: { create: () => ({ buildHeaders: () => ({}) }) },
            },
        },
    });
}

/**
 * @sw-package after-sales
 */
describe('module/sw-dashboard/page/sw-dashboard-index', () => {
    beforeAll(() => {
        Shopwell.Application.addInitializer('httpClient', () => ({ get: httpGet }));
    });

    beforeEach(() => {
        Shopwell.Store.get('session').setCurrentUser({ timeZone: 'Asia/Shanghai' });
        httpGet.mockReset().mockResolvedValue({ data: { date: '2026-11-09', today: {}, previous: {}, hours: [] } });
    });

    it('loads one today summary and shares it with metrics and chart', async () => {
        const summary = { date: '2026-11-09', today: { revenue: 100, orders: 2 }, hours: [] };
        httpGet.mockResolvedValue({ data: summary });
        const wrapper = await createWrapper();
        await flushPromises();
        expect(httpGet).toHaveBeenCalledTimes(1);
        expect(httpGet.mock.calls[0][0]).toBe('/_admin/dashboard/today');
        expect(wrapper.getComponent({ name: 'sw-dashboard-metrics' }).props('summary')).toEqual(summary);
        expect(wrapper.find('select').exists()).toBe(false);
        expect(wrapper.find('sw-dashboard-loyalty-distribution-stub').exists()).toBe(false);
        expect(wrapper.findAll('.sw-dashboard-index__row')).toHaveLength(1);
        expect(wrapper.find('sw-dashboard-latest-orders-stub').exists()).toBe(true);
        expect(wrapper.find('sw-dashboard-order-states-stub').exists()).toBe(true);
    });

    it('does not query sales without order access', async () => {
        await createWrapper([]);
        await flushPromises();
        expect(httpGet).not.toHaveBeenCalled();
    });

    it('shows unavailable data after an unsuccessful load', async () => {
        httpGet.mockRejectedValue(new Error('Unavailable'));
        const wrapper = await createWrapper();
        await flushPromises();
        const metrics = wrapper.getComponent({ name: 'sw-dashboard-metrics' });
        expect(metrics.props('salesError')).toBe(true);
        expect(metrics.props('salesLoading')).toBe(false);
        expect(metrics.props('summary')).toBeNull();
    });
});
