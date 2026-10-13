/**
 * @sw-package framework
 */
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

const { Criteria } = Shopwell.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        paymentRefundId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            refund: null,
            isLoading: false,
            activeTab: 'general',
            refundStates: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.refund?.refundNo),
        };
    },

    computed: {
        refundRepository() {
            return this.repositoryFactory.create('payment_refund');
        },

        stateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        dateFilter() {
            return Shopwell.Filter.getByName('date');
        },

        tabs() {
            return [
                { label: this.$t('sw-payment.refund.detail.tabGeneral'), name: 'general' },
                { label: this.$t('sw-payment.refund.detail.tabPayload'), name: 'payload' },
            ];
        },

        order() {
            return this.refund?.paymentOrder || null;
        },

        requestPayloadJson() {
            return formatPayload(this.refund?.requestPayload);
        },

        responsePayloadJson() {
            return formatPayload(this.refund?.responsePayload);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            const [states] = await Promise.all([
                this.stateRepository.search(createPaymentStateCriteria(PAYMENT_REFUND_STATE_MACHINE)),
                this.loadRefund(),
            ]);

            this.refundStates = states;
        },

        /**
         * The refund carries its payment order, so the page can show what was refunded without a
         * second request.
         */
        loadRefund() {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('paymentOrder');
            criteria.addAssociation('paymentOrder.paymentApp');

            this.isLoading = true;

            return this.refundRepository
                .get(this.paymentRefundId, Shopwell.Context.api, criteria)
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

        onRefresh() {
            this.loadRefund();
        },

        onTabChange(name) {
            this.activeTab = name;
        },

        refundStateLabel(stateId) {
            return findPaymentStateLabel(this.refundStates, stateId);
        },

        refundStateVariant(stateId) {
            return findPaymentStateVariant(this.refundStates, stateId);
        },

        formatAmount(amount, currency) {
            return formatPaymentAmount(amount, currency);
        },
    },
};
