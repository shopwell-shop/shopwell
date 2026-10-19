/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

const orderStates = [
    { id: 'state-paid', name: 'paid', technicalName: 'paid', translated: { name: '已支付' } },
];

const refundStates = [
    { id: 'state-open', name: 'open', technicalName: 'open', translated: { name: '退款中' } },
];

const order = {
    id: 'order-1',
    paymentNo: 'P-1',
    outTradeNo: 'T-1',
    stateId: 'state-paid',
    amount: 1234,
    paidAmount: 1234,
    refundedAmount: 0,
    currency: 'CNY',
    channelTechnicalName: 'alipay_h5',
    subject: '测试商品',
    channelData: { trade_no: '2026010222001' },
    paymentApp: { name: '演示应用', technicalName: 'demo' },
    transactions: [
        { id: 'transaction-1', transactionNo: 'TX-1', status: 'success', createdAt: '2026-01-02T04:00:00.000Z' },
        { id: 'transaction-2', transactionNo: 'TX-2', status: 'pending', createdAt: '2026-01-02T05:00:00.000Z' },
    ],
    refunds: [{ id: 'refund-1', refundNo: 'R-1', stateId: 'state-open', createdAt: '2026-01-04T00:00:00.000Z' }],
    notifications: [{ id: 'notification-1', processResult: 'ok', createdAt: '2026-01-02T04:01:00.000Z' }],
    webhookDeliveries: [{ id: 'webhook-1', status: 'success', createdAt: '2026-01-02T04:02:00.000Z' }],
};

async function createWrapper() {
    const get = jest.fn(() => Promise.resolve(order));

    // the page asks for the states of the order machine first and of the refund machine second
    const stateSearch = jest.fn(() => {
        const states = stateSearch.mock.calls.length === 1 ? orderStates : refundStates;

        return Promise.resolve(new EntityCollection(null, null, null, new Criteria(1, 50), states, states.length));
    });

    const wrapper = mount(await wrapTestComponent('sw-payment-order-detail', { sync: true }), {
        props: {
            paymentOrderId: 'order-1',
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
                'sw-data-grid': true,
                'sw-pagination': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
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

    return { wrapper, get, stateSearch };
}

describe('module/sw-payment/page/sw-payment-order-detail', () => {
    beforeEach(() => {
        global.activeAclRoles = ['payment_order.viewer'];
    });

    it('should load the order with all of its bookkeeping', async () => {
        const { wrapper, get, stateSearch } = await createWrapper();

        expect(get).toHaveBeenCalledWith('order-1', Shopwell.Context.api, expect.anything());
        expect(wrapper.vm.order).toEqual(order);
        expect(wrapper.vm.orderStates).toHaveLength(1);
        expect(wrapper.vm.refundStates).toHaveLength(1);
        expect(stateSearch).toHaveBeenCalledTimes(2);
    });

    it('should sort the associated records of every tab by their creation date', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.transactions.map((transaction) => transaction.id)).toEqual(['transaction-2', 'transaction-1']);
        expect(wrapper.vm.refunds.map((refund) => refund.id)).toEqual(['refund-1']);
        expect(wrapper.vm.notifications.map((notification) => notification.id)).toEqual(['notification-1']);
        expect(wrapper.vm.webhookDeliveries.map((delivery) => delivery.id)).toEqual(['webhook-1']);
    });

    it('should render the raw answer of the channel as json', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.channelDataJson).toBe(JSON.stringify({ trade_no: '2026010222001' }, null, 2));
    });

    it('should tell the state of the order and of a refund', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.orderStateLabel('state-paid')).toBe('已支付');
        expect(wrapper.vm.orderStateVariant('state-paid')).toBe('positive');
        expect(wrapper.vm.refundStateLabel('state-open')).toBe('退款中');
        // a state of the other machine is not part of the loaded one
        expect(wrapper.vm.orderStateLabel('state-open')).toBe('');
        expect(wrapper.vm.formatAmount(1234, 'CNY')).toBe(Shopwell.Utils.format.currency(12.34, 'CNY', 2));
    });

    it('should map the status of a transaction, a notification and a webhook onto its snippet', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.transactionTypeLabel('capture')).toBe('sw-payment.transactionType.capture');
        expect(wrapper.vm.transactionStatusLabel('success')).toBe('sw-payment.transactionStatus.success');
        expect(wrapper.vm.notificationResultLabel('ok')).toBe('sw-payment.notificationResult.ok');
        expect(wrapper.vm.webhookStatusLabel('giving_up')).toBe('sw-payment.webhookStatus.giving_up');

        expect(wrapper.vm.transactionTypeLabel(null)).toBe('');
        expect(wrapper.vm.transactionStatusLabel(undefined)).toBe('');
        expect(wrapper.vm.statusVariant('success')).toBe('positive');
        expect(wrapper.vm.statusVariant('giving_up')).toBe('critical');
    });

    it('should keep the active tab and expose the app of the order', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.activeTab).toBe('general');
        wrapper.vm.onTabChange('refunds');
        expect(wrapper.vm.activeTab).toBe('refunds');

        expect(wrapper.vm.paymentAppLabel()).toBe('演示应用');
        expect(wrapper.vm.tabs).toHaveLength(6);
    });
});
