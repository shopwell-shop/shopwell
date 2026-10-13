/**
 * @sw-package framework
 */
import template from './sw-payment-order-detail.html.twig';
import './sw-payment-order-detail.scss';
import {
    PAYMENT_ORDER_STATE_MACHINE,
    PAYMENT_REFUND_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
    formatPayload,
    statusVariant,
} from '../../service/payment-gateway.api.service';

const { Criteria } = Shopwell.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        paymentOrderId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            order: null,
            isLoading: false,
            activeTab: 'general',
            orderStates: [],
            refundStates: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.order?.paymentNo),
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('payment_order');
        },

        stateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        dateFilter() {
            return Shopwell.Filter.getByName('date');
        },

        tabs() {
            return [
                { label: this.$t('sw-payment.order.detail.tabGeneral'), name: 'general' },
                { label: this.$t('sw-payment.order.detail.tabTransactions'), name: 'transactions' },
                { label: this.$t('sw-payment.order.detail.tabRefunds'), name: 'refunds' },
                { label: this.$t('sw-payment.order.detail.tabNotifications'), name: 'notifications' },
                { label: this.$t('sw-payment.order.detail.tabWebhookDeliveries'), name: 'webhookDeliveries' },
                { label: this.$t('sw-payment.order.detail.tabChannelData'), name: 'channelData' },
            ];
        },

        /**
         * The associated records are ordered as the api delivers them, the newest first for the grids.
         */
        transactions() {
            return this.sortByCreatedAt(this.order?.transactions);
        },

        refunds() {
            return this.sortByCreatedAt(this.order?.refunds);
        },

        notifications() {
            return this.sortByCreatedAt(this.order?.notifications);
        },

        webhookDeliveries() {
            return this.sortByCreatedAt(this.order?.webhookDeliveries);
        },

        channelDataJson() {
            return formatPayload(this.order?.channelData);
        },

        transactionColumns() {
            return [
                {
                    property: 'transactionNo',
                    label: 'sw-payment.order.transaction.columnTransactionNo',
                    primary: true,
                },
                {
                    property: 'type',
                    label: 'sw-payment.order.transaction.columnType',
                },
                {
                    property: 'providerTechnicalName',
                    label: 'sw-payment.order.transaction.columnProvider',
                },
                {
                    property: 'channelTechnicalName',
                    label: 'sw-payment.order.transaction.columnChannel',
                },
                {
                    property: 'status',
                    label: 'sw-payment.order.transaction.columnStatus',
                },
                {
                    property: 'outTransactionId',
                    label: 'sw-payment.order.transaction.columnOutTransactionId',
                },
                {
                    property: 'errorMessage',
                    label: 'global.default.error',
                },
                {
                    property: 'createdAt',
                    label: 'sw-payment.order.transaction.columnCreatedAt',
                },
            ];
        },

        refundColumns() {
            return [
                {
                    property: 'refundNo',
                    label: 'sw-payment.refund.list.columnRefundNo',
                    routerLink: 'sw.payment.refundDetail',
                    primary: true,
                },
                {
                    property: 'outRefundNo',
                    label: 'sw-payment.refund.list.columnOutRefundNo',
                    primary: true,
                },
                {
                    property: 'amount',
                    label: 'sw-payment.refund.list.columnAmount',
                    align: 'right',
                },
                {
                    property: 'currency',
                    label: 'sw-payment.refund.list.columnCurrency',
                },
                {
                    property: 'stateId',
                    label: 'sw-payment.refund.list.columnState',
                },
                {
                    property: 'refundedAt',
                    label: 'sw-payment.refund.list.columnRefundedAt',
                },
                {
                    property: 'createdAt',
                    label: 'sw-payment.refund.list.columnCreatedAt',
                },
            ];
        },

        notificationColumns() {
            return [
                {
                    property: 'createdAt',
                    label: 'sw-payment.order.notification.columnCreatedAt',
                    primary: true,
                },
                {
                    property: 'providerTechnicalName',
                    label: 'sw-payment.order.notification.columnProvider',
                },
                {
                    property: 'channelTechnicalName',
                    label: 'sw-payment.order.notification.columnChannel',
                },
                {
                    property: 'signatureValid',
                    label: 'sw-payment.order.notification.columnSignature',
                },
                {
                    property: 'processResult',
                    label: 'sw-payment.order.notification.columnProcessResult',
                },
                {
                    property: 'remoteIp',
                    label: 'sw-payment.order.notification.columnRemoteIp',
                },
            ];
        },

        webhookColumns() {
            return [
                {
                    property: 'event',
                    label: 'sw-payment.order.webhook.columnEvent',
                    primary: true,
                },
                {
                    property: 'url',
                    label: 'sw-payment.order.webhook.columnUrl',
                },
                {
                    property: 'status',
                    label: 'sw-payment.order.webhook.columnStatus',
                },
                {
                    property: 'attemptCount',
                    label: 'sw-payment.order.webhook.columnAttemptCount',
                    align: 'right',
                },
                {
                    property: 'lastHttpStatus',
                    label: 'sw-payment.order.webhook.columnLastHttpStatus',
                    align: 'right',
                },
                {
                    property: 'nextRetryAt',
                    label: 'sw-payment.order.webhook.columnNextRetryAt',
                },
                {
                    property: 'createdAt',
                    label: 'sw-payment.order.webhook.columnCreatedAt',
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            const [states] = await Promise.all([
                this.stateRepository.search(createPaymentStateCriteria(PAYMENT_ORDER_STATE_MACHINE)),
                this.loadOrder(),
            ]);

            this.orderStates = states;

            const refundStates = await this.stateRepository.search(createPaymentStateCriteria(PAYMENT_REFUND_STATE_MACHINE));

            this.refundStates = refundStates;
        },

        /**
         * The order carries all of its bookkeeping, so a single request is enough for every tab.
         */
        loadOrder() {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('paymentApp');
            criteria.addAssociation('transactions');
            criteria.addAssociation('refunds');
            criteria.addAssociation('notifications');
            criteria.addAssociation('webhookDeliveries');

            this.isLoading = true;

            return this.orderRepository
                .get(this.paymentOrderId, Shopwell.Context.api, criteria)
                .then((order) => {
                    this.order = order;
                })
                .catch(() => {
                    this.order = null;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onRefresh() {
            this.loadOrder();
        },

        onTabChange(name) {
            this.activeTab = name;
        },

        sortByCreatedAt(collection) {
            if (!collection) {
                return [];
            }

            return [...collection].sort((a, b) => {
                return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
            });
        },

        orderStateLabel(stateId) {
            return findPaymentStateLabel(this.orderStates, stateId);
        },

        orderStateVariant(stateId) {
            return findPaymentStateVariant(this.orderStates, stateId);
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

        statusVariant,

        transactionTypeLabel(type) {
            return type ? this.$t(`sw-payment.transactionType.${type}`) : '';
        },

        transactionStatusLabel(status) {
            return status ? this.$t(`sw-payment.transactionStatus.${status}`) : '';
        },

        notificationResultLabel(result) {
            return result ? this.$t(`sw-payment.notificationResult.${result}`) : '';
        },

        webhookStatusLabel(status) {
            return status ? this.$t(`sw-payment.webhookStatus.${status}`) : '';
        },

        paymentAppLabel() {
            if (!this.order?.paymentApp) {
                return '-';
            }

            return this.order.paymentApp.name || this.order.paymentApp.technicalName;
        },
    },
};
