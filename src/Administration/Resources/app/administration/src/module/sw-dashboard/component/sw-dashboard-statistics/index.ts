import template from './sw-dashboard-statistics.html.twig';
import './sw-dashboard-statistics.scss';
import { getUserTimeZoneDate } from '../../helper/order-list-filter';

type OrderAmountBucket = {
    date: string;
    count: number;
    amount: number;
};

type DateRange = {
    label: string;
    range: number;
    interval: 'hour' | 'day';
    aggregate: 'hour' | 'day';
};

type Metric = 'turnover' | 'orderCount';

type SelectOption = {
    label: string;
    value: string;
};

/**
 * @sw-package after-sales
 *
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl'],

    data(): {
        paidBuckets: OrderAmountBucket[];
        allBuckets: OrderAmountBucket[];
        selectedRange: string;
        selectedMetric: Metric;
        isLoading: boolean;
    } {
        return {
            paidBuckets: [],
            allBuckets: [],
            selectedRange: '30Days',
            selectedMetric: 'turnover',
            isLoading: true,
        };
    },

    computed: {
        orderRepository(): unknown {
            return this.repositoryFactory.create('order');
        },

        rangesValueMap(): DateRange[] {
            return [
                {
                    label: '30Days',
                    range: 30,
                    interval: 'day',
                    aggregate: 'day',
                },
                {
                    label: '14Days',
                    range: 14,
                    interval: 'day',
                    aggregate: 'day',
                },
                {
                    label: '7Days',
                    range: 7,
                    interval: 'day',
                    aggregate: 'day',
                },
                {
                    label: '24Hours',
                    range: 24,
                    interval: 'hour',
                    aggregate: 'hour',
                },
                {
                    label: 'yesterday',
                    range: 1,
                    interval: 'day',
                    aggregate: 'hour',
                },
            ];
        },

        activeRange(): DateRange {
            return this.rangesValueMap.find((range) => range.label === this.selectedRange) ?? this.rangesValueMap[0];
        },

        rangeOptions(): SelectOption[] {
            return this.rangesValueMap.map((range) => {
                return {
                    label: this.$t(`sw-dashboard.statistics.dateRanges.${range.label}`),
                    value: range.label,
                };
            });
        },

        metricOptions(): SelectOption[] {
            return [
                {
                    label: this.$t('sw-dashboard.statistics.metricTurnover'),
                    value: 'turnover',
                },
                {
                    label: this.$t('sw-dashboard.statistics.metricOrderCount'),
                    value: 'orderCount',
                },
            ];
        },

        canViewOrders(): boolean {
            return this.acl.can('order.viewer');
        },

        currencyFilter(): (value: number, isoCode: string, decimals: number) => string {
            return Shopwell.Filter.getByName('currency');
        },

        systemCurrencyISOCode(): string {
            return Shopwell.Context.app.systemCurrencyISOCode as string;
        },

        series(): Array<{ name: string; data: Array<{ x: number; y: number }> }> {
            const buckets = this.selectedMetric === 'turnover' ? this.paidBuckets : this.allBuckets;
            const today = this.getToday().getTime();

            const data = buckets.map((bucket) => {
                return {
                    x: this.parseDate(bucket.date),
                    y: this.selectedMetric === 'turnover' ? bucket.amount : bucket.count,
                };
            });

            if (!data.some((point) => point.x === today)) {
                data.push({ x: today, y: 0 });
            }

            return [
                {
                    name:
                        this.selectedMetric === 'turnover'
                            ? this.$t('sw-dashboard.statistics.metricTurnover')
                            : this.$t('sw-dashboard.statistics.metricOrderCount'),
                    data,
                },
            ];
        },

        options(): Record<string, unknown> {
            return {
                xaxis: {
                    type: 'datetime',
                    min: this.getDateAgo(this.activeRange).getTime(),
                    labels: {
                        datetimeUTC: false,
                    },
                    tooltip: {
                        enabled: false,
                    },
                },
                yaxis: {
                    min: 0,
                    tickAmount: 3,
                    labels: {
                        formatter: (value: string) => {
                            if (this.selectedMetric === 'turnover') {
                                return this.currencyFilter(Number.parseFloat(value), this.systemCurrencyISOCode, 0);
                            }

                            return parseInt(value, 10).toString();
                        },
                    },
                },
                tooltip: {
                    x: {
                        format: 'dd MMM',
                    },
                },
            };
        },

        helpText(): string {
            return this.selectedMetric === 'turnover' ? this.$t('sw-dashboard.statistics.helpText') : '';
        },
    },

    created() {
        void this.loadData();
    },

    watch: {
        selectedRange() {
            void this.loadData();
        },
    },

    methods: {
        async loadData(): Promise<void> {
            if (!this.canViewOrders) {
                this.isLoading = false;

                return;
            }

            this.isLoading = true;

            const since = getUserTimeZoneDate(this.getDateAgo(this.activeRange));

            try {
                const [paidBuckets, allBuckets] = await Promise.all([
                    this.fetchOrderAmount(since, true),
                    this.fetchOrderAmount(since, false),
                ]);

                this.paidBuckets = this.filterUntilToday(paidBuckets);
                this.allBuckets = this.filterUntilToday(allBuckets);
            } finally {
                this.isLoading = false;
            }
        },

        filterUntilToday(buckets: OrderAmountBucket[]): OrderAmountBucket[] {
            const today = getUserTimeZoneDate(new Date());

            return buckets.filter((bucket) => bucket.date <= today);
        },

        fetchOrderAmount(since: string, paid: boolean): Promise<OrderAmountBucket[]> {
            const httpClient = Shopwell.Application.getContainer('init').httpClient as {
                get: (url: string, config: { headers: unknown }) => Promise<{ data: { statistic: OrderAmountBucket[] } }>;
            };

            const headers = (this.orderRepository as { buildHeaders: () => unknown }).buildHeaders();
            const timezone = Shopwell.Store.get('session').currentUser?.timeZone ?? 'UTC';
            const url = `/_admin/dashboard/order-amount/${since}?timezone=${timezone}&paid=${paid.toString()}`;

            return httpClient
                .get(url, { headers })
                .then((response) => response.data.statistic)
                .catch(() => [] as OrderAmountBucket[]);
        },

        getToday(): Date {
            const today = Shopwell.Utils.format.dateWithUserTimezone();
            today.setHours(0, 0, 0, 0);

            return today;
        },

        getDateAgo(range: DateRange): Date {
            const date = Shopwell.Utils.format.dateWithUserTimezone();

            if (range.interval === 'hour') {
                date.setHours(date.getHours() - range.range);

                return date;
            }

            date.setDate(date.getDate() - range.range);
            date.setHours(0, 0, 0, 0);

            return date;
        },

        parseDate(date: string): number {
            const parsedDate = new Date(
                date
                    .replace(/-/g, '/')
                    .replace('T', ' ')
                    .replace(/\..*|\+.*/, ''),
            );

            return parsedDate.valueOf();
        },
    },
});
