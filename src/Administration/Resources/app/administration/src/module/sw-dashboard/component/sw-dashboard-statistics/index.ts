import type { PropType } from 'vue';
import MtChart from '@shopwell-ag/meteor-component-library/dist/esm/MtChart';
import type { HourBucket } from '../../helper/today-statistics';
import template from './sw-dashboard-statistics.html.twig';
import './sw-dashboard-statistics.scss';

/**
 * @sw-package after-sales
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,
    components: { 'mt-chart': MtChart },
    inject: ['acl'],
    props: {
        hours: { type: Array as PropType<HourBucket[]>, default: () => [] },
        isLoading: { type: Boolean, default: false },
        salesError: { type: Boolean, default: false },
    },
    computed: {
        canViewOrders(): boolean {
            return this.acl.can('order.viewer');
        },
        hasData(): boolean {
            return this.hours.some((bucket) => (bucket.amount ?? 0) > 0 || (bucket.count ?? 0) > 0);
        },
        currencyFilter(): (value: number, isoCode: string, decimals: number) => string {
            return Shopwell.Filter.getByName('currency');
        },
        series(): Array<{ name: string; data: Array<{ x: string; y: number | null }> }> {
            return ['revenue', 'orders'].map((metric) => ({
                name: this.metricLabel(metric),
                data: this.hours.map((bucket) => ({
                    x: `${bucket.hour.toString().padStart(2, '0')}:00`,
                    y: metric === 'revenue' ? bucket.amount : bucket.count,
                })),
            }));
        },
        chartOptions(): Record<string, unknown> {
            return {
                chart: {
                    animations: { enabled: false },
                    zoom: { enabled: false },
                    toolbar: { show: false },
                    fontFamily: 'inherit',
                    foreColor: 'var(--color-text-secondary-default)',
                },
                dataLabels: { enabled: false },
                colors: ['var(--color-text-brand-default)', 'var(--color-text-positive-default)'],
                stroke: {
                    width: [3, 2],
                    dashArray: [0, 5],
                    curve: 'straight',
                    colors: ['var(--color-text-brand-default)', 'var(--color-text-positive-default)'],
                },
                markers: { size: 0, hover: { size: 4 } },
                legend: { show: false },
                grid: {
                    borderColor: 'var(--color-border-primary-default)',
                    strokeDashArray: 4,
                    padding: { left: 8, right: 8 },
                },
                xaxis: {
                    type: 'category',
                    axisBorder: { color: 'var(--color-border-primary-default)' },
                    axisTicks: { color: 'var(--color-border-primary-default)' },
                    crosshairs: { stroke: { color: 'var(--color-border-primary-default)' } },
                    tooltip: { enabled: false },
                    labels: { rotate: 0, formatter: (value: string) => (Number.parseInt(value, 10) % 4 === 0 ? value : '') },
                },
                yaxis: ['revenue', 'orders'].map((metric, index) => ({
                    seriesName: this.series[index].name,
                    opposite: metric === 'orders',
                    min: 0,
                    forceNiceScale: true,
                    decimalsInFloat: 0,
                    labels: { formatter: (value: number) => this.formatValue(value, metric) },
                })),
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: ['revenue', 'orders'].map((metric) => ({
                        formatter: (value: number) => this.formatValue(value, metric),
                    })),
                },
            };
        },
    },
    methods: {
        metricLabel(metric: string): string {
            return this.$t(
                metric === 'revenue' ? 'sw-dashboard.statistics.metricTurnover' : 'sw-dashboard.statistics.metricOrderCount',
            );
        },
        formatValue(value: number, metric: string): string {
            return metric === 'revenue'
                ? this.currencyFilter(value, Shopwell.Context.app.systemCurrencyISOCode as string, 0)
                : Math.round(value).toLocaleString();
        },
    },
});
