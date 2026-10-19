/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(active = 'gateway', routerPush = jest.fn()) {
    return mount(await wrapTestComponent('sw-settings-sms-section-tabs', { sync: true }), {
        props: {
            active,
        },
        global: {
            mocks: {
                $router: {
                    push: routerPush,
                },
            },
            stubs: {
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs"></div>',
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                    },
                },
            },
        },
    });
}

function tabItems(wrapper) {
    return wrapper.getComponent({ name: 'mt-tabs' }).props('items');
}

describe('src/module/sw-settings-sms/component/sw-settings-sms-section-tabs', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should offer the three screens of the module', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-settings-sms-section-tabs');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({ name: 'sw.settings.sms.gateway' }),
            expect.objectContaining({ name: 'sw.settings.sms.template' }),
            expect.objectContaining({ name: 'sw.settings.sms.delivery' }),
        ]);
        expect(tabItems(wrapper).map((item) => item.label)).toEqual([
            'sw-settings-sms.general.tabGateway',
            'sw-settings-sms.general.tabTemplate',
            'sw-settings-sms.general.tabDelivery',
        ]);
    });

    it('should mark the route of the section it was given as active', async () => {
        wrapper = await createWrapper('template');
        await flushPromises();

        expect(wrapper.getComponent({ name: 'mt-tabs' }).props('defaultItem')).toBe('sw.settings.sms.template');
    });

    it('should navigate when a tab is clicked', async () => {
        const routerPush = jest.fn();
        wrapper = await createWrapper('gateway', routerPush);
        await flushPromises();

        const deliveryTab = tabItems(wrapper).find((item) => item.name === 'sw.settings.sms.delivery');

        expect(deliveryTab).toBeDefined();

        deliveryTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.settings.sms.delivery' });
    });
});
