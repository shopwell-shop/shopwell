/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import MtCard from '@shopwell-ag/meteor-component-library/dist/esm/MtCard';
import MtIcon from '@shopwell-ag/meteor-component-library/dist/esm/MtIcon';

describe('sw-order-return-detail rendering', () => {
    it('shows accepted and restocked values with the system boolean column', async () => {
        const orderReturn = {
            id: 'return-1',
            returnNumber: 'RT-1',
            type: 'return_refund',
            orderId: 'order-1',
            stateMachineState: { technicalName: 'closed' },
            refundAmount: 100,
            currency: 'CNY',
            lineItems: [
                {
                    id: 'line-1',
                    orderLineItemId: 'order-line-1',
                    quantity: 1,
                    refundAmount: 100,
                    accepted: true,
                    restocked: false,
                },
            ],
        };
        const wrapper = mount(await wrapTestComponent('sw-order-return-detail', { sync: true }), {
            props: { orderReturnId: 'return-1' },
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: { create: () => ({ search: () => Promise.resolve({ first: () => orderReturn }) }) },
                    acl: { can: () => false },
                    orderReturnApiService: {},
                },
                mocks: { $route: { meta: { $module: { icon: 'regular-undo' } } } },
                stubs: {
                    'sw-page': { template: '<div><slot name="content"/></div>' },
                    'sw-card-view': { template: '<div><slot/></div>' },
                    'mt-card': MtCard,
                    'mt-icon': MtIcon,
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                    'sw-data-grid-column-boolean': await wrapTestComponent('sw-data-grid-column-boolean', { sync: true }),
                    'sw-data-grid-settings': true,
                    'sw-search-bar': true,
                    'mt-textarea': true,
                    'mt-button': true,
                },
            },
        });
        try {
            await flushPromises();
            expect(wrapper.findAll('.sw-data-grid-column-boolean .is--active')).toHaveLength(1);
            expect(wrapper.findAll('.sw-data-grid-column-boolean .is--inactive')).toHaveLength(1);
        } finally {
            wrapper.unmount();
        }
    });
});
