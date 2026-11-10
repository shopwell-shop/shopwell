import type { PropType } from 'vue';
import type { TodayStatistics, TodayTotals } from '../../helper/today-statistics';
import template from './sw-dashboard-metrics.html.twig';
import './sw-dashboard-metrics.scss';

type MetricCard = {
    key: string;
    label: string;
    value: string;
    unit: string;
    footnote: string;
    delta: { tone: string; text: string } | null;
};

/**
 * @sw-package after-sales
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['acl'],
    props: {
        summary: { type: Object as PropType<TodayStatistics | null>, default: null },
        salesLoading: { type: Boolean, default: false },
        salesError: { type: Boolean, default: false },
    },
    computed: {
        canViewOrders(): boolean {
            return this.acl.can('order.viewer');
        },
        canViewWallet(): boolean {
            return this.acl.can('wallet.viewer');
        },
        canViewMemberships(): boolean {
            return this.acl.can('loyalty.viewer');
        },
        currencyFilter(): (value: number, isoCode: string, decimals: number) => string {
            return Shopwell.Filter.getByName('currency');
        },
        metricCards(): MetricCard[] {
            const current = this.summary?.today;
            const previous = this.summary?.previous;
            const definitions: Array<{ key: string; field: keyof TodayTotals; money: boolean }> = [
                { key: 'revenue', field: 'revenue', money: true },
                { key: 'refunds', field: 'refundAmount', money: true },
            ];
            if (this.canViewWallet) {
                definitions.push({ key: 'walletTopUps', field: 'walletTopUpAmount', money: true });
            }
            if (this.canViewMemberships) {
                definitions.push({ key: 'memberships', field: 'memberships', money: false });
            }
            return definitions.map(({ key, field, money }) => {
                const value = current?.[field];
                const previousValue = previous?.[field];
                const metricAvailable =
                    this.summary !== null &&
                    !this.salesError &&
                    !this.salesLoading &&
                    value != null &&
                    previousValue != null;
                return {
                    key,
                    label: this.$t(`sw-dashboard.metrics.${key}`),
                    value: this.formatMetricValue(current?.[field], money),
                    unit: money ? '' : this.$t('sw-dashboard.metrics.customersUnit'),
                    delta:
                        metricAvailable && (value !== 0 || previousValue !== 0 || this.metricCount(key) > 0)
                            ? this.buildDelta(value, previousValue, money, key === 'refunds')
                            : null,
                    footnote: this.metricFootnote(key),
                };
            });
        },
    },
    methods: {
        formatMetricValue(value: number | null | undefined, money: boolean): string {
            if (value == null || this.salesError || this.salesLoading) {
                return '—';
            }
            return money
                ? this.currencyFilter(value, Shopwell.Context.app.systemCurrencyISOCode as string, 2)
                : value.toLocaleString();
        },
        metricFootnote(key: string): string {
            if (this.salesError) {
                return this.$t('sw-dashboard.general.loadError');
            }
            if (!this.summary || this.salesLoading) {
                return '';
            }
            const count = this.metricCount(key);
            if (count <= 0) {
                return '';
            }
            const snippet = { revenue: 'salesCount', walletTopUps: 'topUpCount', refunds: 'refundCount' }[key];
            return snippet ? this.$t(`sw-dashboard.metrics.${snippet}`, { count }) : '';
        },
        metricCount(key: string): number {
            const totals = this.summary?.today;
            if (!totals) {
                return 0;
            }
            return { revenue: totals.orders, walletTopUps: totals.walletTopUpCount, refunds: totals.refundCount }[key] ?? 0;
        },
        buildDelta(current: number, previous: number, money: boolean, reverse: boolean): MetricCard['delta'] {
            if (current === previous) {
                return { tone: 'neutral', text: '0' };
            }
            const increase = current > previous;
            const change =
                previous > 0
                    ? `${((Math.abs(current - previous) / previous) * 100).toFixed(1)}%`
                    : `${this.formatMetricValue(Math.abs(current - previous), money)}${money ? '' : this.$t('sw-dashboard.metrics.customersUnit')}`;
            return {
                tone: increase !== reverse ? 'positive' : 'critical',
                text: `${increase ? '↑' : '↓'} ${change}`,
            };
        },
    },
});
