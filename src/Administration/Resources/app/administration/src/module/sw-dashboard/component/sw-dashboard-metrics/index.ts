import template from './sw-dashboard-metrics.html.twig';
import './sw-dashboard-metrics.scss';
import {
    ORDER_PAYMENT_FILTER,
    ORDER_PAYMENT_PROPERTY,
    buildFilteredOrderListLink,
    getDayBounds,
    getDayKeys,
    getPreviousDay,
    type StateSelection,
} from '../../helper/order-list-filter';

const { Criteria } = Shopwell.Data;

type OrderAmountBucket = {
    date: string;
    count: number;
    amount: number;
};

type Metrics = {
    revenue: number;
    paidOrders: number;
    orders: number;
    newCustomers: number;
};

type Delta = {
    direction: 'up' | 'down' | 'flat';
    text: string;
};

type OrderListLink = {
    name: string;
    query: Record<string, string>;
};

type MetricCard = {
    key: string;
    label: string;
    value: string;
    unit: string;
    delta: Delta | null;
    link: OrderListLink | null;
};

const EMPTY_METRICS: Metrics = {
    revenue: 0,
    paidOrders: 0,
    orders: 0,
    newCustomers: 0,
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
        currentMetrics: Metrics;
        previousMetrics: Metrics;
        paidState: StateSelection | null;
        isLoading: boolean;
    } {
        return {
            currentMetrics: { ...EMPTY_METRICS },
            previousMetrics: { ...EMPTY_METRICS },
            paidState: null,
            isLoading: true,
        };
    },

    computed: {
        orderRepository(): unknown {
            return this.repositoryFactory.create('order');
        },

        customerRepository(): unknown {
            return this.repositoryFactory.create('customer');
        },

        stateMachineStateRepository(): unknown {
            return this.repositoryFactory.create('state_machine_state');
        },

        canViewOrders(): boolean {
            return this.acl.can('order.viewer');
        },

        canViewCustomers(): boolean {
            return this.acl.can('customer.viewer');
        },

        currencyFilter(): (value: number, isoCode: string, decimals: number) => string {
            return Shopwell.Filter.getByName('currency');
        },

        systemCurrencyISOCode(): string {
            return Shopwell.Context.app.systemCurrencyISOCode as string;
        },

        averageOrderValue(): number {
            return this.calculateAverageOrderValue(this.currentMetrics);
        },

        previousAverageOrderValue(): number {
            return this.calculateAverageOrderValue(this.previousMetrics);
        },

        metricCards(): MetricCard[] {
            const cards: MetricCard[] = [
                {
                    key: 'revenue',
                    label: this.$t('sw-dashboard.metrics.revenue'),
                    value: this.currencyFilter(this.currentMetrics.revenue, this.systemCurrencyISOCode, 2),
                    unit: '',
                    delta: this.buildDelta(this.currentMetrics.revenue, this.previousMetrics.revenue),
                    link: this.buildTodayLink(true),
                },
                {
                    key: 'orders',
                    label: this.$t('sw-dashboard.metrics.orders'),
                    value: this.currentMetrics.orders.toString(),
                    unit: this.$t('sw-dashboard.metrics.ordersUnit'),
                    delta: this.buildDelta(this.currentMetrics.orders, this.previousMetrics.orders),
                    link: this.buildTodayLink(false),
                },
                {
                    key: 'averageOrderValue',
                    label: this.$t('sw-dashboard.metrics.averageOrderValue'),
                    value: this.currencyFilter(this.averageOrderValue, this.systemCurrencyISOCode, 2),
                    unit: '',
                    delta: this.buildDelta(this.averageOrderValue, this.previousAverageOrderValue),
                    link: this.buildTodayLink(true),
                },
            ];

            if (this.canViewCustomers) {
                cards.push({
                    key: 'newCustomers',
                    label: this.$t('sw-dashboard.metrics.newCustomers'),
                    value: this.currentMetrics.newCustomers.toString(),
                    unit: this.$t('sw-dashboard.metrics.customersUnit'),
                    delta: this.buildDelta(this.currentMetrics.newCustomers, this.previousMetrics.newCustomers),
                    link: null,
                });
            }

            return cards;
        },

        helpText(): string {
            return this.$t('sw-dashboard.metrics.paidOnlyHint');
        },
    },

    created() {
        void this.loadMetrics();
    },

    methods: {
        async loadMetrics(): Promise<void> {
            if (!this.canViewOrders) {
                this.isLoading = false;

                return;
            }

            this.isLoading = true;

            const dayKeys = getDayKeys();

            try {
                const [
                    paidBuckets,
                    allBuckets,
                    newCustomers,
                    previousNewCustomers,
                    paidState,
                ] = await Promise.all([
                    this.fetchOrderAmount(dayKeys.yesterday, true),
                    this.fetchOrderAmount(dayKeys.yesterday, false),
                    this.fetchNewCustomerCount(getDayBounds(new Date()).from),
                    // Yesterday only: bounded by the start of today, otherwise the
                    // comparison would include today's customers as well.
                    this.fetchNewCustomerCount(getDayBounds(getPreviousDay()).from, getDayBounds(new Date()).from),
                    this.fetchPaidState(),
                ]);

                this.paidState = paidState;

                this.currentMetrics = {
                    revenue: this.getBucket(paidBuckets, dayKeys.today).amount,
                    paidOrders: this.getBucket(paidBuckets, dayKeys.today).count,
                    orders: this.getBucket(allBuckets, dayKeys.today).count,
                    newCustomers,
                };

                this.previousMetrics = {
                    revenue: this.getBucket(paidBuckets, dayKeys.yesterday).amount,
                    paidOrders: this.getBucket(paidBuckets, dayKeys.yesterday).count,
                    orders: this.getBucket(allBuckets, dayKeys.yesterday).count,
                    newCustomers: previousNewCustomers,
                };
            } finally {
                this.isLoading = false;
            }
        },

        getBucket(buckets: OrderAmountBucket[], dayKey: string): { amount: number; count: number } {
            const bucket = buckets.find((item) => item.date === dayKey);

            return {
                amount: bucket?.amount ?? 0,
                count: bucket?.count ?? 0,
            };
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

        fetchNewCustomerCount(since: string, until?: string): Promise<number> {
            if (!this.canViewCustomers) {
                return Promise.resolve(0);
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.range('createdAt', until ? { gte: since, lte: until } : { gte: since }));
            criteria.setTotalCountMode(1);

            return (this.customerRepository as { search: (c: unknown) => Promise<{ total: number }> })
                .search(criteria)
                .then((result) => result.total)
                .catch(() => 0);
        },

        calculateAverageOrderValue(metrics: Metrics): number {
            if (metrics.paidOrders <= 0) {
                return 0;
            }

            return metrics.revenue / metrics.paidOrders;
        },

        /**
         * Deep link into the order list for the current day. Paid metrics also
         * narrow the list down to the paid transaction state, so the orders
         * behind the number can be inspected directly.
         */
        buildTodayLink(paidOnly: boolean): OrderListLink {
            return buildFilteredOrderListLink({
                date: new Date(),
                state: {
                    filterName: ORDER_PAYMENT_FILTER,
                    property: ORDER_PAYMENT_PROPERTY,
                    state: paidOnly ? this.paidState : null,
                },
            });
        },

        fetchPaidState(): Promise<StateSelection | null> {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('stateMachine.technicalName', 'order_transaction.state'));
            criteria.addFilter(Criteria.equals('technicalName', 'paid'));

            return (
                this.stateMachineStateRepository as {
                    search: (c: unknown) => Promise<Array<{ id: string; name: string; translated?: { name: string } }>>;
                }
            )
                .search(criteria)
                .then((result) => {
                    const state = result?.[0];

                    if (!state) {
                        return null;
                    }

                    return {
                        id: state.id,
                        name: state.translated?.name ?? state.name,
                    };
                })
                .catch(() => null);
        },

        buildDelta(current: number, previous: number): Delta | null {
            if (previous <= 0) {
                return null;
            }

            const percentage = ((current - previous) / previous) * 100;
            const direction = percentage > 0 ? 'up' : percentage < 0 ? 'down' : 'flat';
            const sign = percentage > 0 ? '↑' : percentage < 0 ? '↓' : '';

            return {
                direction,
                text: `${sign} ${Math.abs(percentage).toFixed(1)}% ${this.$t('sw-dashboard.metrics.comparedToYesterday')}`,
            };
        },
    },
});
