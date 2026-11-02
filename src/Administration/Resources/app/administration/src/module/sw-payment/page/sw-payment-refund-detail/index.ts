/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-payment-refund-detail.html.twig';
import './sw-payment-refund-detail.scss';
import {
    PAYMENT_REFUND_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
    formatPayload,
} from '../../service/payment-gateway.api.service';
import type { PaymentOrder, PaymentRefund } from '../../payment.types';

const { Criteria } = Shopwell.Data;

/**
 * One refund and the payment it takes the money back from.
 *
 * A refund is never read on its own: what an operator has to know is how much of which payment came
 * back and whether the channel confirmed it. The payment is therefore a card of this page and not a
 * link the reader has to follow to make sense of the numbers above it.
 *
 * @private
 */
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        paymentRefundId: {
            type: String,
            required: true,
        },
    },

    data(): {
        refund: PaymentRefund | null;
        isLoading: boolean;
        refundStates: EntityCollection<'state_machine_state'> | null;
    } {
        return {
            refund: null,
            isLoading: false,
            refundStates: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.refund?.refundNo),
        };
    },

    computed: {
        refundRepository(): RepositoryType<'payment_refund'> {
            return this.repositoryFactory.create('payment_refund');
        },

        stateRepository(): RepositoryType<'state_machine_state'> {
            return this.repositoryFactory.create('state_machine_state');
        },

        dateFilter(): (value: string | Date) => string {
            return Shopwell.Filter.getByName('date') as (value: string | Date) => string;
        },

        /**
         * The order the refund belongs to, delivered with the refund itself.
         */
        order(): PaymentOrder | null {
            return this.refund?.paymentOrder ?? null;
        },

        orderLink(): { name: string; params: { id: string } } | null {
            const orderId = this.order?.id;

            return orderId ? { name: 'sw.payment.orderDetail', params: { id: orderId } } : null;
        },

        requestPayloadJson(): string {
            return formatPayload(this.refund?.requestPayload);
        },

        responsePayloadJson(): string {
            return formatPayload(this.refund?.responsePayload);
        },

        hasPayload(): boolean {
            return this.requestPayloadJson !== '' || this.responsePayloadJson !== '';
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            const [refundStates] = await Promise.all([
                this.stateRepository.search(createPaymentStateCriteria(PAYMENT_REFUND_STATE_MACHINE)),
                this.loadRefund(),
            ]);

            this.refundStates = refundStates;
        },

        /**
         * The refund carries its payment order, so the page can show what was refunded without a
         * second request.
         */
        loadRefund(): Promise<void> {
            const criteria: CriteriaType = new Criteria(1, 1);
            criteria.addAssociation('paymentOrder');
            criteria.addAssociation('paymentOrder.paymentApp');

            this.isLoading = true;

            return this.refundRepository
                .get(this.paymentRefundId as EntitySchema.EntityKey<'payment_refund'>, Shopwell.Context.api, criteria)
                .then((refund) => {
                    this.refund = refund;
                })
                .catch(() => {
                    this.refund = null;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onRefresh(): Promise<void> {
            return this.loadRefund();
        },

        refundStateLabel(stateId: string): string {
            return findPaymentStateLabel(this.refundStates ?? [], stateId);
        },

        refundStateVariant(stateId: string): string {
            return findPaymentStateVariant(this.refundStates ?? [], stateId);
        },

        formatAmount(amount: number, currency: string): string {
            return formatPaymentAmount(amount, currency);
        },

        formatDateOrDash(value: string | null | undefined): string {
            return value ? this.dateFilter(value) : '–';
        },

        paymentAppLabel(): string {
            const paymentApp = this.order?.paymentApp;

            if (!paymentApp) {
                return '–';
            }

            return paymentApp.name || paymentApp.technicalName || '–';
        },
    },
});
