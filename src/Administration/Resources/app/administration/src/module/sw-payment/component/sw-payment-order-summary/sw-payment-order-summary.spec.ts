/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

function buildOrder(overrides: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        id: 'order-1',
        amount: 1234,
        paidAmount: 0,
        refundedAmount: 0,
        currency: 'CNY',
        providerTechnicalName: 'alipay',
        channelTechnicalName: 'alipay_h5',
        paidAt: '2026-01-02T04:00:00.000Z',
        paymentApp: { name: '演示应用', technicalName: 'demo' },
        ...overrides,
    };
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(order: Record<string, unknown> = buildOrder()): Promise<VueWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-payment-order-summary', { sync: true }), {
        props: {
            order,
        },
        global: {
            stubs: {
                'mt-progress-bar': { template: '<div class="progress"><slot></slot></div>' },
            },
        },
    });

    activeWrapper = wrapper;

    await flushPromises();

    return wrapper;
}

function fact(wrapper: VueWrapper, index: number): string {
    return wrapper.findAll('.sw-payment-order-summary__fact')[index].text().replace(/\s+/g, ' ').trim();
}

describe('module/sw-payment/component/sw-payment-order-summary', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    /**
     * An order that nobody paid yet has no paid amount - the page must still show a number, and it
     * has to say which number it is.
     */
    it('should headline the order amount while nothing is paid', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-payment-order-summary__amount-label').text()).toBe('sw-payment.order.detail.labelAmount');
        expect(wrapper.find('.sw-payment-order-summary__amount-value').text()).toBe(
            Shopwell.Utils.format.currency(12.34, 'CNY', 2),
        );
    });

    it('should headline what arrived once the channel collected it', async () => {
        const wrapper = await createWrapper(buildOrder({ paidAmount: 1200 }));

        expect(wrapper.find('.sw-payment-order-summary__amount-label').text()).toBe(
            'sw-payment.order.detail.labelPaidAmount',
        );
        expect(wrapper.find('.sw-payment-order-summary__amount-value').text()).toBe(
            Shopwell.Utils.format.currency(12, 'CNY', 2),
        );
    });

    it('should only report a refund when some money came back', async () => {
        const withoutRefund = await createWrapper(buildOrder({ paidAmount: 1234 }));

        expect(withoutRefund.find('.sw-payment-order-summary__amount-refunded').exists()).toBe(false);
        expect(withoutRefund.find('.progress').exists()).toBe(false);

        const withRefund = await createWrapper(buildOrder({ paidAmount: 1234, refundedAmount: 400 }));

        expect(withRefund.find('.sw-payment-order-summary__amount-refunded').text()).toBe(
            'sw-payment.order.detail.textRefundedOf',
        );
        expect(withRefund.find('.progress').exists()).toBe(true);
    });

    it('should qualify the headline with the facts an operator asks for next', async () => {
        const wrapper = await createWrapper();

        expect(fact(wrapper, 0)).toBe('sw-payment.order.detail.labelAmount CN¥12.34');
        expect(fact(wrapper, 1)).toBe('sw-payment.order.detail.labelRefundedAmount CN¥0.00');
        expect(fact(wrapper, 2)).toBe('sw-payment.order.detail.labelProvider alipay');
        expect(fact(wrapper, 3)).toBe('sw-payment.order.detail.labelChannel alipay_h5');
        expect(fact(wrapper, 4)).toBe('sw-payment.order.detail.labelPaymentApp 演示应用');
    });

    it('should name the app of the order and fall back to a dash', async () => {
        const wrapper = await createWrapper(buildOrder({ paymentApp: null }));

        expect(fact(wrapper, 4)).toBe('sw-payment.order.detail.labelPaymentApp –');
    });

    /**
     * A missing timestamp is an empty cell, not the first of january 1970.
     */
    it('should render a missing timestamp as a dash', async () => {
        const wrapper = await createWrapper(buildOrder({ paidAt: null }));

        expect(fact(wrapper, 5)).toBe('sw-payment.order.detail.labelPaidAt –');
    });
});
