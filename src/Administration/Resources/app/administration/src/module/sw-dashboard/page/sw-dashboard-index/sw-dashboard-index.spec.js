import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-dashboard-index', { sync: true }), {
        global: {
            stubs: {
                'sw-page': await wrapTestComponent('sw-page'),
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-dashboard-metrics': true,
                'sw-dashboard-statistics': true,
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
                acl: {
                    can: () => true,
                },
            },
        },
    });
}

/**
 * @sw-package after-sales
 */
describe('module/sw-dashboard/page/sw-dashboard-index', () => {
    it('renders the business overview sections', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('sw-dashboard-metrics-stub').exists()).toBe(true);
        expect(wrapper.find('sw-dashboard-statistics-stub').exists()).toBe(true);
        expect(wrapper.find('sw-dashboard-latest-orders-stub').exists()).toBe(true);
    });

    it('does not render the best-selling products section', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('sw-dashboard-top-products-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-dashboard-index__details').exists()).toBe(false);
    });

    it('keeps the extension points around the content', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const sections = wrapper.findAll('sw-extension-component-section-stub');

        expect(sections).toHaveLength(2);
        expect(sections[0].attributes('position-identifier')).toBe('sw-dashboard__before-content');
        expect(sections[1].attributes('position-identifier')).toBe('sw-dashboard__after-content');
    });

    it('does not render the removed greeting, help or feedback blocks', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        [
            '.sw-dashboard-index__welcome-text',
            '.sw-dashboard-index__welcome-title',
            '.sw-dashboard-index__welcome-message',
            '.sw-dashboard-index__card-grid',
            '.sw-dashboard-index__card',
        ].forEach((selector) => {
            expect(wrapper.find(selector).exists()).toBe(false);
        });

        expect(wrapper.find('mt-link-stub').exists()).toBe(false);
    });

    it('builds the page title from the module title', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(typeof wrapper.vm.$options.metaInfo).toBe('function');
        expect(wrapper.vm.$options.metaInfo.call({ $createTitle: () => 'Dashboard' })).toEqual({
            title: 'Dashboard',
        });
    });
});
