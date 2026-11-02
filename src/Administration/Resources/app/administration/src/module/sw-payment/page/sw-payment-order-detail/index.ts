/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
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
import type {
    PaymentActivity,
    PaymentAlert,
    PaymentNotification,
    PaymentOrder,
    PaymentRefund,
    PaymentTransaction,
    PaymentWebhookDelivery,
} from '../../payment.types';

const { Criteria } = Shopwell.Data;

/**
 * The results of a notification that reported a problem instead of a payment.
 */
const PROBLEM_RESULTS = ['amount_mismatch', 'currency_mismatch'];

/**
 * A delivery that will not arrive again without an operator.
 */
const DEAD_DELIVERY_STATUSES = ['failed', 'giving_up'];

/**
 * Newest first, for every list of the page.
 */
function sortByCreatedAtDesc<T extends { createdAt: string }>(items: ArrayLike<T>): T[] {
    return Array.from(items).sort((a, b) => {
        return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
    });
}

/**
 * A notification whose signature did not verify. The column is `false` for a rejected signature and
 * `null` for a row that predates the check, and only the first one is an alert.
 */
function hasFailedSignature(notification: PaymentNotification): boolean {
    return !notification.signatureValid && notification.signatureValid !== null;
}

/**
 * One payment order and everything the gateway recorded about it.
 *
 * The page is read the way an incident is read: what happened - a timeline of transactions,
 * notifications, webhooks and refunds -, what it means - amount, state, channel -, and only then the
 * identifiers an operator has to quote. The four record kinds used to live in four tabs, which made
 * the reader rebuild the order of events from memory.
 *
 * @private
 */
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        paymentOrderId: {
            type: String,
            required: true,
        },
    },

    data(): {
        order: PaymentOrder | null;
        isLoading: boolean;
        orderStates: EntityCollection<'state_machine_state'> | null;
        refundStates: EntityCollection<'state_machine_state'> | null;
    } {
        return {
            order: null,
            isLoading: false,
            orderStates: null,
            refundStates: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.order?.paymentNo),
        };
    },

    computed: {
        orderRepository(): RepositoryType<'payment_order'> {
            return this.repositoryFactory.create('payment_order');
        },

        stateRepository(): RepositoryType<'state_machine_state'> {
            return this.repositoryFactory.create('state_machine_state');
        },

        dateFilter(): (value: string | Date) => string {
            return Shopwell.Filter.getByName('date') as (value: string | Date) => string;
        },

        transactions(): PaymentTransaction[] {
            return sortByCreatedAtDesc(this.order?.transactions ?? []);
        },

        refunds(): PaymentRefund[] {
            return sortByCreatedAtDesc(this.order?.refunds ?? []);
        },

        notifications(): PaymentNotification[] {
            return sortByCreatedAtDesc(this.order?.notifications ?? []);
        },

        webhookDeliveries(): PaymentWebhookDelivery[] {
            return sortByCreatedAtDesc(this.order?.webhookDeliveries ?? []);
        },

        /**
         * The four record kinds as one story, ordered by the moment they happened.
         *
         * The sort key is the timestamp and not the date the entry displays: a formatted date is not
         * orderable, and sorting the rendered string would leave the timeline in the order the tables
         * happen to be concatenated in.
         */
        activities(): PaymentActivity[] {
            if (!this.order) {
                return [];
            }

            const entries: Array<{ timestamp: number; activity: PaymentActivity }> = [];

            const add = (happenedAt: string | null | undefined, activity: PaymentActivity): void => {
                if (!happenedAt) {
                    return;
                }

                entries.push({ timestamp: new Date(happenedAt).getTime(), activity });
            };

            this.transactions.forEach((transaction) => {
                add(transaction.createdAt, {
                    id: `transaction-${transaction.id}`,
                    kind: 'transaction',
                    at: this.dateFilter(transaction.createdAt),
                    title: this.$t('sw-payment.order.activity.titleTransaction', { id: transaction.transactionNo }),
                    detail: this.detailOf([
                        this.transactionTypeLabel(transaction.type),
                        transaction.outTransactionId,
                        transaction.errorMessage,
                    ]),
                    statusLabel: this.transactionStatusLabel(transaction.status),
                    statusVariant: statusVariant(transaction.status),
                    link: null,
                });
            });

            this.notifications.forEach((notification) => {
                add(notification.createdAt, {
                    id: `notification-${notification.id}`,
                    kind: 'notification',
                    at: this.dateFilter(notification.createdAt),
                    title: this.$t('sw-payment.order.activity.titleNotification', {
                        provider: notification.providerTechnicalName,
                    }),
                    detail: this.detailOf([notification.remoteIp, notification.errorMessage]),
                    statusLabel: this.notificationResultLabel(notification.processResult),
                    statusVariant: statusVariant(notification.processResult),
                    link: null,
                });
            });

            this.webhookDeliveries.forEach((delivery) => {
                add(delivery.createdAt, {
                    id: `webhook-${delivery.id}`,
                    kind: 'webhook',
                    at: this.dateFilter(delivery.createdAt),
                    title: this.$t('sw-payment.order.activity.titleWebhook', { event: delivery.event }),
                    detail: this.detailOf([
                        delivery.url,
                        delivery.lastHttpStatus ? `HTTP ${delivery.lastHttpStatus}` : null,
                    ]),
                    statusLabel: this.webhookStatusLabel(delivery.status),
                    statusVariant: statusVariant(delivery.status),
                    link: null,
                });
            });

            this.refunds.forEach((refund) => {
                add(refund.refundedAt ?? refund.createdAt, {
                    id: `refund-${refund.id}`,
                    kind: 'refund',
                    at: this.dateFilter(refund.refundedAt ?? refund.createdAt),
                    title: this.$t('sw-payment.order.activity.titleRefund', { id: refund.refundNo }),
                    detail: this.detailOf([this.formatAmount(refund.amount, refund.currency), refund.outRefundNo]),
                    statusLabel: this.refundStateLabel(refund.stateId),
                    statusVariant: this.refundStateVariant(refund.stateId),
                    link: { name: 'sw.payment.refundDetail', params: { id: refund.id } },
                });
            });

            return entries.sort((a, b) => b.timestamp - a.timestamp).map((entry) => entry.activity);
        },

        /**
         * What the page has to say before it shows anything else. An operator who opens a payment
         * because a customer complained needs the broken signature first, not on tab four.
         */
        alerts(): PaymentAlert[] {
            const alerts: PaymentAlert[] = [];

            const invalidSignatures = this.notifications.filter(hasFailedSignature).length;
            if (invalidSignatures > 0) {
                alerts.push({
                    id: 'invalid-signature',
                    variant: 'critical',
                    message: this.$t('sw-payment.order.alert.invalidSignature', { count: invalidSignatures }),
                });
            }

            const mismatches = this.notifications.filter((notification) => {
                return PROBLEM_RESULTS.includes(notification.processResult ?? '');
            }).length;
            if (mismatches > 0) {
                alerts.push({
                    id: 'amount-mismatch',
                    variant: 'critical',
                    message: this.$t('sw-payment.order.alert.notificationMismatch', { count: mismatches }),
                });
            }

            const deadDeliveries = this.webhookDeliveries.filter((delivery) => {
                return DEAD_DELIVERY_STATUSES.includes(delivery.status);
            }).length;
            if (deadDeliveries > 0) {
                alerts.push({
                    id: 'webhook-failed',
                    variant: 'attention',
                    message: this.$t('sw-payment.order.alert.webhookFailed', { count: deadDeliveries }),
                });
            }

            return alerts;
        },

        hasChannelData(): boolean {
            return this.channelDataJson !== '';
        },

        channelDataJson(): string {
            return formatPayload(this.order?.channelData);
        },

        /**
         * The shop order this payment collects for, when the app that created it named one.
         */
        orderLink(): { name: string; params: { id: string } } | null {
            const orderId = this.order?.orderId;

            return orderId ? { name: 'sw.order.detail', params: { id: orderId } } : null;
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            const [orderStates] = await Promise.all([
                this.stateRepository.search(createPaymentStateCriteria(PAYMENT_ORDER_STATE_MACHINE)),
                this.loadOrder(),
            ]);

            this.orderStates = orderStates;
            this.refundStates = await this.stateRepository.search(createPaymentStateCriteria(PAYMENT_REFUND_STATE_MACHINE));
        },

        /**
         * The order carries all of its bookkeeping, so a single request fills every section.
         */
        loadOrder(): Promise<void> {
            const criteria: CriteriaType = new Criteria(1, 1);
            criteria.addAssociation('paymentApp');
            criteria.addAssociation('transactions');
            criteria.addAssociation('refunds');
            criteria.addAssociation('notifications');
            criteria.addAssociation('webhookDeliveries');

            this.isLoading = true;

            return this.orderRepository
                .get(this.paymentOrderId as EntitySchema.EntityKey<'payment_order'>, Shopwell.Context.api, criteria)
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

        onRefresh(): Promise<void> {
            return this.loadOrder();
        },

        /**
         * The second line of an entry: whatever of the record is worth reading at a glance, joined so
         * the timeline stays a timeline and not a table.
         */
        detailOf(parts: Array<string | null | undefined>): string {
            return parts.filter((part): part is string => Boolean(part)).join(' · ');
        },

        orderStateLabel(stateId: string): string {
            return findPaymentStateLabel(this.orderStates ?? [], stateId);
        },

        orderStateVariant(stateId: string): string {
            return findPaymentStateVariant(this.orderStates ?? [], stateId);
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

        transactionTypeLabel(type: string | null | undefined): string {
            return type ? this.$t(`sw-payment.transactionType.${type}`) : '';
        },

        transactionStatusLabel(status: string | null | undefined): string {
            return status ? this.$t(`sw-payment.transactionStatus.${status}`) : '';
        },

        notificationResultLabel(result: string | null | undefined): string {
            return result ? this.$t(`sw-payment.notificationResult.${result}`) : '';
        },

        webhookStatusLabel(status: string | null | undefined): string {
            return status ? this.$t(`sw-payment.webhookStatus.${status}`) : '';
        },
    },
});
