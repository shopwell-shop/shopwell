/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

const refundStates = [
    { id: 'state-finished', name: 'finished', technicalName: 'finished', translated: { name: '已退款' } },
];

const refund = {
    id: 'refund-1',
    refundNo: 'R-1',
    outRefundNo: 'OR-1',
    stateId: 'state-finished',
    amount: 500,
    currency: 'CNY',
    reason: '买家申请退款',
    requestPayload: { out_request_no: 'OR-1' },
    responsePayload: { code: '10000' },
    paymentOrder: {
        id: 'order-1',
        paymentNo: 'P-1',
        outTradeNo: 'T-1',
        channelTechnicalName: 'alipay_h5',
        paymentApp: { name: '演示应用', technicalName: 'demo' },
    },
};

async function createWrapper() {
    const get = jest.fn(() => Promise.resolve(refund));

    const stateSearch = jest.fn(() =>
        Promise.resolve(new EntityCollection(null, null, null, new Criteria(1, 50), refundStates, refundStates.length)),
    );

    const wrapper = mount(await wrapTestComponent('sw-payment-refund-detail', { sync: true }), {
        props: {
            paymentRefundId: 'refund-1',
        },
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-description-list': { template: '<dl><slot></slot></dl>' },
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-tabs': true,
                'mt-card': { template: '<div><slot></slot></div>' },
                'mt-button': true,
                'mt-badge': true,
                'mt-icon': true,
                'mt-link': true,
                'mt-empty-state': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => {
                        return entity === 'state_machine_state' ? { search: stateSearch } : { get };
                    },
                },
            },
        },
    });

    await flushPromises();

    return { wrapper, get };
}

describe('module/sw-payment/page/sw-payment-refund-detail', () => {
    beforeEach(() => {
        global.activeAclRoles = ['payment_refund.viewer'];
    });

    it('should load the refund with its payment order', async () => {
        const { wrapper, get } = await createWrapper();

        expect(get).toHaveBeenCalledWith('refund-1', Shopwell.Context.api, expect.anything());
        expect(wrapper.vm.refund).toEqual(refund);
        expect(wrapper.vm.order).toEqual(refund.paymentOrder);
        expect(wrapper.vm.order.paymentNo).toBe('P-1');
        expect(wrapper.vm.refundStates).toHaveLength(1);
    });

    it('should render the payloads of the refund as json', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.requestPayloadJson).toBe(JSON.stringify({ out_request_no: 'OR-1' }, null, 2));
        expect(wrapper.vm.responsePayloadJson).toBe(JSON.stringify({ code: '10000' }, null, 2));
    });

    it('should tell the state of the refund in the badge of the refund machine', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.refundStateLabel('state-finished')).toBe('已退款');
        expect(wrapper.vm.refundStateVariant('state-finished')).toBe('positive');
        expect(wrapper.vm.formatAmount(500, 'CNY')).toBe(Shopwell.Utils.format.currency(5, 'CNY', 2));
    });

    it('should keep the active tab', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.tabs).toHaveLength(2);
        wrapper.vm.onTabChange('payload');
        expect(wrapper.vm.activeTab).toBe('payload');
    });
});
