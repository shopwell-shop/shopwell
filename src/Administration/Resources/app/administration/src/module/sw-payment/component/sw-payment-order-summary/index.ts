/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-payment-order-summary.html.twig';
import './sw-payment-order-summary.scss';
import { formatPaymentAmount } from '../../service/payment-gateway.api.service';
import type { PaymentOrder } from '../../payment.types';

/**
 * The head of a payment order: how much was collected, how much came back, and through which
 * interface.
 *
 * The amount is the first thing an operator looks for, so it is the first thing on the page and it is
 * set in the size of a headline. Everything that decides what may happen next - the paid amount, the
 * refunded one, the channel - sits beside it instead of in a separate card that has to be found.
 *
 * @private
 */
export default defineComponent({
    template,

    props: {
        order: {
            type: Object as PropType<PaymentOrder>,
            required: true,
        },
    },

    computed: {
        dateFilter(): (value: string | Date) => string {
            return Shopwell.Filter.getByName('date') as (value: string | Date) => string;
        },

        /**
         * An order that was never paid shows what it asks for, a paid one shows what arrived - the
         * two numbers differ for a channel that collects less than the order states.
         */
        headlineAmount(): number {
            return this.order.paidAmount > 0 ? this.order.paidAmount : this.order.amount;
        },

        headlineLabel(): string {
            return this.order.paidAmount > 0
                ? this.$t('sw-payment.order.detail.labelPaidAmount')
                : this.$t('sw-payment.order.detail.labelAmount');
        },

        refundedAmount(): number {
            return this.order.refundedAmount ?? 0;
        },

        hasRefund(): boolean {
            return this.refundedAmount > 0;
        },

        /**
         * A translated sentence with a value in it, built here and not in the template: an object as
         * the second argument of `$t` breaks the compilation of the template.
         */
        refundedText(): string {
            return this.$t('sw-payment.order.detail.textRefundedOf', {
                refunded: this.formatAmount(this.refundedAmount, this.order.currency),
            });
        },

        paymentAppLabel(): string {
            const paymentApp = this.order.paymentApp;

            if (!paymentApp) {
                return '–';
            }

            return paymentApp.name || paymentApp.technicalName || '–';
        },
    },

    methods: {
        formatAmount(amount: number, currency: string): string {
            return formatPaymentAmount(amount, currency);
        },

        formatDateOrDash(value: string | null | undefined): string {
            return value ? this.dateFilter(value) : '–';
        },
    },
});
