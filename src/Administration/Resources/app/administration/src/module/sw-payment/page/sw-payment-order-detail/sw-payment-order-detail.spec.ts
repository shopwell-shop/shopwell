/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

/**
 * One payment that took a second attempt: the first transaction stayed pending, the retry went
 * through, the channel confirmed it, the platform told the app, and a refund followed two days later.
 */
function buildOrder(overrides: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        id: 'order-1',
        paymentNo: 'P-1',
        outTradeNo: 'T-1',
        outPaymentNo: '2026010222001',
        stateId: 'state-paid',
        amount: 1234,
        paidAmount: 1234,
        refundedAmount: 100,
        currency: 'CNY',
        channelTechnicalName: 'alipay_h5',
        providerTechnicalName: 'alipay',
        subject: '测试商品',
        idempotencyKey: 'idem-1',
        paidAt: '2026-01-02T04:05:00.000Z',
        createdAt: '2026-01-02T03:59:00.000Z',
        channelData: { trade_no: '2026010222001' },
        paymentApp: { name: '演示应用', technicalName: 'demo' },
        transactions: [
            {
                id: 'transaction-1',
                transactionNo: 'TX-1',
                type: 'capture',
                status: 'pending',
                createdAt: '2026-01-02T04:00:00.000Z',
            },
            {
                id: 'transaction-2',
                transactionNo: 'TX-2',
                type: 'capture',
                status: 'success',
                createdAt: '2026-01-02T04:05:00.000Z',
            },
        ],
        refunds: [
            {
                id: 'refund-1',
                refundNo: 'R-1',
                outRefundNo: 'OR-1',
                amount: 100,
                currency: 'CNY',
                stateId: 'state-open',
                createdAt: '2026-01-04T00:00:00.000Z',
            },
        ],
        notifications: [
            {
                id: 'notification-1',
                providerTechnicalName: 'alipay',
                processResult: 'ok',
                signatureValid: true,
                createdAt: '2026-01-02T04:06:00.000Z',
            },
        ],
        webhookDeliveries: [
            {
                id: 'webhook-1',
                event: 'payment.paid',
                url: 'https://app.test/hook',
                status: 'success',
                attemptCount: 1,
                createdAt: '2026-01-02T04:07:00.000Z',
            },
        ],
        ...overrides,
    };
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(order: Record<string, unknown> | Error = buildOrder()): Promise<{
    wrapper: VueWrapper;
    get: jest.Mock;
    push: jest.Mock;
}> {
    const get = jest.fn(() => (order instanceof Error ? Promise.reject(order) : Promise.resolve(order)));
    const push = jest.fn();

    const states = {
        order: [{ id: 'state-paid', name: 'paid', technicalName: 'paid', translated: { name: '已支付' } }],
        refund: [{ id: 'state-open', name: 'open', technicalName: 'open', translated: { name: '退款中' } }],
    };

    // the page asks for the state machine of the order first and of the refund second
    let stateCalls = 0;
    const stateSearch = jest.fn(() => {
        stateCalls += 1;

        return Promise.resolve(stateCalls === 1 ? states.order : states.refund);
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
                'sw-description-list': { template: '<dl><slot></slot></dl>' },
                'sw-payment-order-summary': {
                    props: ['order'],
                    template: '<div class="summary">{{ order.paymentNo }}</div>',
                },
                // the timeline is built by the page, so the stub only has to hand back what it was given
                'sw-payment-order-activity': {
                    props: ['activities'],
                    template: `<div>
                        <span
                            v-for="activity in activities"
                            :key="activity.id"
                            class="activity"
                            :data-kind="activity.kind"
                            :data-title="activity.title"
                            :data-detail="activity.detail"
                            :data-status="activity.statusLabel"
                            :data-variant="activity.statusVariant"
                            :data-link="activity.link ? activity.link.name : ''"
                        >{{ activity.at }}</span>
                    </div>`,
                },
                'sw-payment-identifier': {
                    props: ['value'],
                    template: '<span class="identifier">{{ value }}</span>',
                },
                'router-link': {
                    props: ['to'],
                    template: '<a class="router-link" :data-to="JSON.stringify(to)"><slot></slot></a>',
                },
                'mt-card': { template: '<div class="card"><slot></slot></div>' },
                'mt-banner': {
                    props: ['variant'],
                    template: '<div class="alert" :data-variant="variant"><slot></slot></div>',
                },
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="badge" :data-variant="variant"><slot></slot></span>',
                },
                'mt-button': true,
                'mt-icon': true,
                'mt-link': true,
                'mt-empty-state': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity: string) => {
                        return entity === 'state_machine_state' ? { search: stateSearch } : { get };
                    },
                },
                acl: { can: () => true },
            },
            mocks: {
                $router: { push },
            },
        },
    });

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, get, push };
}

/**
 * The timeline entries in the order the reader sees them.
 */
function activities(wrapper: VueWrapper): Array<{ kind: string; title: string; detail: string; status: string }> {
    return wrapper.findAll('.activity').map((entry) => {
        return {
            kind: entry.attributes('data-kind') ?? '',
            title: entry.attributes('data-title') ?? '',
            detail: entry.attributes('data-detail') ?? '',
            status: entry.attributes('data-status') ?? '',
        };
    });
}

describe('module/sw-payment/page/sw-payment-order-detail', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should load the order with its app and its bookkeeping in one request', async () => {
        const { wrapper, get } = await createWrapper();

        expect(get).toHaveBeenCalledWith('order-1', Shopwell.Context.api, expect.anything());
        expect(wrapper.find('.summary').text()).toBe('P-1');
    });

    /**
     * The four tables are one story: the refund of january the fourth comes before the retry of
     * january the second, no matter which table either of them lives in.
     */
    it('should merge transactions, notifications, webhooks and refunds into one timeline', async () => {
        const { wrapper } = await createWrapper();

        expect(activities(wrapper).map((entry) => entry.kind)).toEqual([
            'refund',
            'webhook',
            'notification',
            'transaction',
            'transaction',
        ]);
    });

    it('should title every entry and name the record behind it', async () => {
        const { wrapper } = await createWrapper();

        const [
            refund,
            webhook,
            notification,
            retry,
        ] = activities(wrapper);

        expect(refund.title).toBe('sw-payment.order.activity.titleRefund');
        expect(refund.detail).toContain('OR-1');
        expect(webhook.title).toBe('sw-payment.order.activity.titleWebhook');
        expect(webhook.detail).toContain('https://app.test/hook');
        expect(notification.title).toBe('sw-payment.order.activity.titleNotification');
        expect(retry.detail).toContain('sw-payment.transactionType.capture');
    });

    it('should read the status of every entry in the words of its own machine', async () => {
        const { wrapper } = await createWrapper();

        expect(activities(wrapper).map((entry) => entry.status)).toEqual([
            '退款中',
            'sw-payment.webhookStatus.success',
            'sw-payment.notificationResult.ok',
            'sw-payment.transactionStatus.success',
            'sw-payment.transactionStatus.pending',
        ]);
    });

    /**
     * Only a refund is a record of its own - it has a page, the rest of the entries do not.
     */
    it('should link the refund of the timeline and nothing else', async () => {
        const { wrapper } = await createWrapper();

        const entries = wrapper.findAll('.activity');

        expect(entries[0].attributes('data-link')).toBe('sw.payment.refundDetail');
        expect(entries[1].attributes('data-link')).toBe('');
    });

    it('should stay quiet about a payment nobody complained about', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.findAll('.alert')).toHaveLength(0);
    });

    /**
     * A signature that did not verify decides whether the payment is credited at all, so it is the
     * first thing on the page and not an entry somewhere down the timeline.
     */
    it('should point at a notification that failed its signature', async () => {
        const { wrapper } = await createWrapper(
            buildOrder({
                notifications: [
                    {
                        id: 'notification-1',
                        providerTechnicalName: 'alipay',
                        processResult: 'invalid_signature',
                        signatureValid: false,
                        createdAt: '2026-01-02T04:06:00.000Z',
                    },
                ],
            }),
        );

        const alerts = wrapper.findAll('.alert');

        expect(alerts).toHaveLength(1);
        expect(alerts[0].attributes('data-variant')).toBe('critical');
        expect(alerts[0].text()).toBe('sw-payment.order.alert.invalidSignature');
    });

    it('should point at a rejected amount and at a webhook that gave up', async () => {
        const { wrapper } = await createWrapper(
            buildOrder({
                notifications: [
                    {
                        id: 'notification-1',
                        providerTechnicalName: 'alipay',
                        processResult: 'amount_mismatch',
                        signatureValid: true,
                        createdAt: '2026-01-02T04:06:00.000Z',
                    },
                ],
                webhookDeliveries: [
                    {
                        id: 'webhook-1',
                        event: 'payment.paid',
                        url: 'https://app.test/hook',
                        status: 'giving_up',
                        attemptCount: 9,
                        createdAt: '2026-01-02T04:07:00.000Z',
                    },
                ],
            }),
        );

        const alerts = wrapper.findAll('.alert');

        expect(alerts.map((alert) => alert.text())).toEqual([
            'sw-payment.order.alert.notificationMismatch',
            'sw-payment.order.alert.webhookFailed',
        ]);
        expect(alerts[1].attributes('data-variant')).toBe('attention');
    });

    it('should render the raw answer of the channel as json', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-payment-order-detail__payload').text()).toBe(
            JSON.stringify({ trade_no: '2026010222001' }, null, 2),
        );
    });

    it('should not draw a channel payload card for an order without one', async () => {
        const { wrapper } = await createWrapper(buildOrder({ channelData: null }));

        expect(wrapper.find('.sw-payment-order-detail__payload').exists()).toBe(false);
    });

    /**
     * The identifiers of the rail are what an operator copies into a support ticket, so every one of
     * them is on the page as it is stored.
     */
    it('should list the identifiers of the order in the rail', async () => {
        const { wrapper } = await createWrapper();

        const identifiers = wrapper.findAll('.identifier').map((identifier) => identifier.text());

        expect(identifiers).toEqual([
            'P-1',
            'T-1',
            '2026010222001',
            'idem-1',
        ]);
    });

    it('should link a refund of the rail to its own page', async () => {
        const { wrapper } = await createWrapper();

        const link = wrapper.find('.sw-payment-order-detail__refund-link');

        expect(link.text()).toBe('R-1');
        expect(link.attributes('data-to')).toBe('{"name":"sw.payment.refundDetail","params":{"id":"refund-1"}}');
        expect(wrapper.find('.sw-payment-order-detail__refund-amount').text()).toBe(
            Shopwell.Utils.format.currency(1, 'CNY', 2),
        );
    });

    /**
     * The shop order behind the payment is what an operator jumps to when the question is about the
     * goods rather than the money.
     */
    it('should offer the shop order only when the app named one', async () => {
        const withOrder = await createWrapper(buildOrder({ orderId: 'shop-order-1' }));

        await withOrder.wrapper.find('.sw-payment-order-detail__button-order').trigger('click');

        expect(withOrder.push).toHaveBeenCalledWith({ name: 'sw.order.detail', params: { id: 'shop-order-1' } });

        const withoutOrder = await createWrapper(buildOrder({ orderId: null }));
        expect(withoutOrder.wrapper.find('.sw-payment-order-detail__button-order').exists()).toBe(false);
    });

    it('should tell the state of the order in the header', async () => {
        const { wrapper } = await createWrapper();

        const badge = wrapper.find('.sw-payment-order-detail__header-state');

        expect(badge.text()).toBe('已支付');
        expect(badge.attributes('data-variant')).toBe('positive');
    });

    it('should read a missing timestamp as a dash', async () => {
        const { wrapper } = await createWrapper(buildOrder({ paidAt: null, closedAt: null }));

        expect(wrapper.find('.sw-payment-order-detail__dates').text()).toContain('–');
    });

    /**
     * A payment id that does not resolve is a page that has to say so, not a page that renders empty
     * cards forever.
     */
    it('should fall back to the empty state when the order cannot be read', async () => {
        const { wrapper } = await createWrapper(new Error('not found'));

        expect(wrapper.find('.sw-payment-order-detail__not-found').exists()).toBe(true);
        expect(wrapper.find('.summary').exists()).toBe(false);
    });

    it('should read the order again when the operator refreshes', async () => {
        const { wrapper, get } = await createWrapper();

        await wrapper.find('.sw-payment-order-detail__button-refresh').trigger('click');
        await flushPromises();

        expect(get).toHaveBeenCalledTimes(2);
    });
});
