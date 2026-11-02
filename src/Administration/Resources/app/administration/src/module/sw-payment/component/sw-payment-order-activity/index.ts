/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-payment-order-activity.html.twig';
import './sw-payment-order-activity.scss';
import type { PaymentActivity, PaymentActivityKind } from '../../payment.types';

const KINDS: PaymentActivityKind[] = [
    'transaction',
    'notification',
    'webhook',
    'refund',
];

const ICONS: Record<PaymentActivityKind, string> = {
    transaction: 'regular-credit-card',
    notification: 'regular-bell',
    webhook: 'regular-link',
    refund: 'regular-reply',
};

/**
 * The story of one payment order, newest first.
 *
 * Transactions, incoming notifications, outgoing webhooks and refunds are four tables that answer one
 * question: what happened to this money, and in which order. Reading them as one list means the
 * notification that explains a failed transaction sits directly below it instead of two tabs away.
 *
 * The entries arrive translated - this component only groups and renders them.
 *
 * @private
 */
export default defineComponent({
    template,

    props: {
        activities: {
            type: Array as PropType<PaymentActivity[]>,
            required: true,
        },
    },

    data(): { activeKind: PaymentActivityKind | 'all' } {
        return {
            activeKind: 'all',
        };
    },

    computed: {
        filters(): Array<{ value: PaymentActivityKind | 'all'; label: string; count: number }> {
            const filters: Array<{ value: PaymentActivityKind | 'all'; label: string; count: number }> = [
                {
                    value: 'all',
                    label: this.$t('sw-payment.order.activity.filterAll'),
                    count: this.activities.length,
                },
            ];

            KINDS.forEach((kind) => {
                const count = this.activities.filter((activity) => activity.kind === kind).length;

                // a kind this payment never produced is noise, not a filter
                if (count === 0) {
                    return;
                }

                filters.push({ value: kind, label: this.kindLabel(kind), count });
            });

            return filters;
        },

        visibleActivities(): PaymentActivity[] {
            if (this.activeKind === 'all') {
                return this.activities;
            }

            return this.activities.filter((activity) => activity.kind === this.activeKind);
        },
    },

    methods: {
        kindLabel(kind: PaymentActivityKind): string {
            return this.$t(`sw-payment.order.activity.kind.${kind}`);
        },

        kindIcon(kind: PaymentActivityKind): string {
            return ICONS[kind];
        },

        onFilter(value: PaymentActivityKind | 'all'): void {
            this.activeKind = value;
        },
    },
});
