/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

function buildRefund(overrides: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        id: 'refund-1',
        refundNo: 'R-1',
        outRefundNo: 'OR-1',
        outRefundId: 'ORID-1',
        stateId: 'state-finished',
        amount: 500,
        currency: 'CNY',
        reason: '买家申请退款',
        refundedAt: '2026-01-05T02:00:00.000Z',
        createdAt: '2026-01-05T01:00:00.000Z',
        updatedAt: '2026-01-05T02:00:00.000Z',
        requestPayload: { out_request_no: 'OR-1' },
        responsePayload: { code: '10000' },
        paymentOrder: {
            id: 'order-1',
            paymentNo: 'P-1',
            outPaymentNo: '2026010222001',
            amount: 1234,
            paidAmount: 1234,
            refundedAmount: 500,
            currency: 'CNY',
            channelTechnicalName: 'alipay_h5',
            paidAt: '2026-01-02T04:00:00.000Z',
            paymentApp: { name: '演示应用', technicalName: 'demo' },
        },
        ...overrides,
    };
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(refund: Record<string, unknown> | Error = buildRefund()): Promise<{
    wrapper: VueWrapper;
    get: jest.Mock;
    push: jest.Mock;
}> {
    const get = jest.fn(() => (refund instanceof Error ? Promise.reject(refund) : Promise.resolve(refund)));
    const push = jest.fn();

    const stateSearch = jest.fn(() =>
        Promise.resolve([
            { id: 'state-finished', name: 'finished', technicalName: 'finished', translated: { name: '已退款' } },
        ]),
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
                'sw-description-list': { template: '<dl><slot></slot></dl>' },
                'sw-payment-identifier': {
                    props: ['value'],
                    template: '<span class="identifier">{{ value }}</span>',
                },
                'router-link': {
                    props: ['to'],
                    template: '<a class="router-link" :data-to="JSON.stringify(to)"><slot></slot></a>',
                },
                'mt-card': { template: '<div class="card"><slot></slot></div>' },
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

describe('module/sw-payment/page/sw-payment-refund-detail', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    /**
     * The refund carries its payment order, so the numbers on the page - refunded here, paid and
     * refunded in total there - never come from two responses that could disagree.
     */
    it('should load the refund together with the payment it belongs to', async () => {
        const { wrapper, get } = await createWrapper();

        expect(get).toHaveBeenCalledWith('refund-1', Shopwell.Context.api, expect.anything());
        expect(wrapper.find('.sw-payment-refund-detail__header').text()).toContain('R-1');
        expect(wrapper.find('.sw-payment-refund-detail__amount-value').text()).toBe(
            Shopwell.Utils.format.currency(5, 'CNY', 2),
        );
    });

    it('should name the payment order the money comes back from', async () => {
        const { wrapper, push } = await createWrapper();

        const link = wrapper.find('.sw-payment-refund-detail__order-link');

        expect(link.text()).toBe('P-1');
        expect(link.attributes('data-to')).toBe('{"name":"sw.payment.orderDetail","params":{"id":"order-1"}}');

        await wrapper.find('.sw-payment-refund-detail__button-order').trigger('click');

        expect(push).toHaveBeenCalledWith({ name: 'sw.payment.orderDetail', params: { id: 'order-1' } });
    });

    it('should offer no link for a refund the gateway booked without a payment', async () => {
        const { wrapper } = await createWrapper(buildRefund({ paymentOrder: null }));

        expect(wrapper.find('.sw-payment-refund-detail__order-link').exists()).toBe(false);
        expect(wrapper.find('.sw-payment-refund-detail__button-order').exists()).toBe(false);
        expect(wrapper.find('.sw-payment-refund-detail__order').text()).toContain('sw-payment.refund.list.messageEmpty');
    });

    it('should render the request and the answer of the channel as json', async () => {
        const { wrapper } = await createWrapper();

        const payloads = wrapper.findAll('.sw-payment-refund-detail__payload');

        expect(payloads).toHaveLength(2);
        expect(payloads[0].text()).toBe(JSON.stringify({ out_request_no: 'OR-1' }, null, 2));
        expect(payloads[1].text()).toBe(JSON.stringify({ code: '10000' }, null, 2));
    });

    /**
     * A refund the channel rejected has an empty answer - a card of two empty blocks is noise.
     */
    it('should not draw a payload card for a refund without a request or a response', async () => {
        const { wrapper } = await createWrapper(buildRefund({ requestPayload: null, responsePayload: null }));

        expect(wrapper.find('.sw-payment-refund-detail__payload-card').exists()).toBe(false);
    });

    it('should tell the state of the refund in the badge of the refund machine', async () => {
        const { wrapper } = await createWrapper();

        const badge = wrapper.find('.sw-payment-refund-detail__header-state');

        expect(badge.text()).toBe('已退款');
        expect(badge.attributes('data-variant')).toBe('positive');
    });

    it('should name the app that processed the refund and fall back to a dash', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.find('.sw-payment-refund-detail__order').text()).toContain('演示应用');

        const withoutName = await createWrapper(
            buildRefund({ paymentOrder: { id: 'order-1', paymentApp: { technicalName: 'demo' } } }),
        );
        expect(withoutName.wrapper.find('.sw-payment-refund-detail__order').text()).toContain('demo');

        const withoutApp = await createWrapper(buildRefund({ paymentOrder: { id: 'order-1', paymentApp: null } }));
        expect(withoutApp.wrapper.find('.sw-payment-refund-detail__order').text()).toContain('–');
    });

    it('should show a dash for a date the refund does not have yet', async () => {
        const { wrapper } = await createWrapper(buildRefund({ refundedAt: null }));

        expect(wrapper.find('.sw-payment-refund-detail__summary').text()).toContain('–');
    });

    it('should list the three numbers of the refund in the rail', async () => {
        const { wrapper } = await createWrapper();

        expect(
            wrapper
                .find('.sw-payment-refund-detail__identifiers')
                .findAll('.identifier')
                .map((identifier) => identifier.text()),
        ).toEqual(['R-1', 'OR-1', 'ORID-1']);
    });

    /**
     * A refund id that does not resolve is a page that has to say so, not a page that renders empty
     * cards forever.
     */
    it('should fall back to the empty state when the refund cannot be read', async () => {
        const { wrapper } = await createWrapper(new Error('not found'));

        expect(wrapper.find('.sw-payment-refund-detail__not-found').exists()).toBe(true);
        expect(wrapper.find('.sw-payment-refund-detail__layout').exists()).toBe(false);
    });

    it('should read the refund again when the operator refreshes', async () => {
        const { wrapper, get } = await createWrapper();

        await wrapper.find('.sw-payment-refund-detail__button-refresh').trigger('click');
        await flushPromises();

        expect(get).toHaveBeenCalledTimes(2);
    });
});
