/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import 'src/module/sw-customer/page/sw-customer-detail/extension/sw-customer-detail-base';

const customer = {
    id: 'customer-1',
    isLoading: false,
};

async function createWrapper(customerEditMode = false) {
    return mount(await wrapTestComponent('sw-customer-detail-base', { sync: true }), {
        props: {
            customer,
            customerEditMode,
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({}),
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
            },
            stubs: {
                'mt-card': {
                    template: '<div class="mt-card-stub"><slot></slot><slot name="grid"></slot></div>',
                },
                'sw-customer-card': {
                    template: '<div></div>',
                },
                'sw-customer-base-info': true,
                'sw-customer-default-addresses': true,
                'sw-custom-field-set-renderer': true,
                'sw-customer-detail-loyalty-overview': {
                    name: 'sw-customer-detail-loyalty-overview-stub',
                    props: ['customer', 'customerEditMode'],
                    template: '<div class="sw-customer-detail-loyalty-overview-stub"></div>',
                },
            },
        },
    });
}

describe('module/sw-customer/page/sw-customer-detail/extension/sw-customer-detail-base', () => {
    it('appends the loyalty overview card to the base info holder', async () => {
        const wrapper = await createWrapper();

        const overview = wrapper.find('.sw-customer-detail-base__info-holder .sw-customer-detail-loyalty-overview-stub');

        expect(overview.exists()).toBe(true);
    });

    it('forwards the customer and its edit mode to the loyalty overview', async () => {
        const wrapper = await createWrapper(true);

        const overview = wrapper.findComponent({ name: 'sw-customer-detail-loyalty-overview-stub' });

        expect(overview.props('customer')).toEqual(customer);
        expect(overview.props('customerEditMode')).toBe(true);
    });
});
