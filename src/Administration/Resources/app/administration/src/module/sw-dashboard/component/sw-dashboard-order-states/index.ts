import template from './sw-dashboard-order-states.html.twig';
import './sw-dashboard-order-states.scss';

const { Criteria } = Shopwell.Data;

type StateBucket = { key: string; orderCount?: { count?: number } };

/**
 * @sw-package after-sales
 *
 * How the orders of the last 30 days distribute over the order state machine.
 * The donut answers "where do orders sit right now" - a growing cancelled or
 * open slice is the first hint of a checkout or fulfilment problem, before any
 * single number in the KPI row would show it.
 *
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl'],

    data(): {
        buckets: Array<{ name: string; count: number }>;
        isLoading: boolean;
    } {
        return {
            buckets: [],
            isLoading: true,
        };
    },

    computed: {
        orderRepository(): unknown {
            return this.repositoryFactory.create('order');
        },

        stateMachineStateRepository(): unknown {
            return this.repositoryFactory.create('state_machine_state');
        },

        canViewOrders(): boolean {
            return this.acl.can('order.viewer');
        },

        series(): Array<{ name: string; data: Array<{ x: string; y: number }> }> {
            return this.buckets.map((bucket) => {
                return {
                    name: bucket.name,
                    data: [{ x: bucket.name, y: bucket.count }],
                };
            });
        },

        hasData(): boolean {
            return this.buckets.length > 0;
        },

        chartOptions(): Record<string, unknown> {
            return {
                legend: {
                    position: 'bottom',
                },
                dataLabels: {
                    enabled: false,
                },
            };
        },
    },

    created() {
        void this.loadStates();
    },

    methods: {
        async loadStates(): Promise<void> {
            if (!this.canViewOrders) {
                this.isLoading = false;

                return;
            }

            this.isLoading = true;

            try {
                const [buckets, states] = await Promise.all([
                    this.fetchStateBuckets(),
                    this.fetchOrderStateNames(),
                ]);

                const names = new Map(states.map((state) => [state.technicalName, state.name]));

                this.buckets = buckets.map((bucket) => {
                    return {
                        name: names.get(bucket.key) ?? bucket.key,
                        count: bucket.orderCount?.count ?? 0,
                    };
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * One terms aggregation over the order state of the last 30 days - the
         * count rides along as a sub aggregation, the buckets hydrate with the
         * same shape the warehouse service reads.
         */
        fetchStateBuckets(): Promise<StateBucket[]> {
            const since = new Date();
            since.setDate(since.getDate() - 30);
            since.setHours(0, 0, 0, 0);

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.range('orderDateTime', { gte: since.toISOString() }));
            criteria.addAggregation(Criteria.terms('orderStates', 'stateMachineState.technicalName', null, null, Criteria.count('orderCount', 'id')));

            return (this.orderRepository as {
                search: (c: unknown) => Promise<{ aggregations: Record<string, { buckets?: StateBucket[] }> }>;
            })
                .search(criteria)
                .then((result) => result.aggregations?.orderStates?.buckets ?? [])
                .catch(() => [] as StateBucket[]);
        },

        /**
         * The translated names of the order state machine, so the legend speaks
         * the language of the operator instead of technical names.
         */
        fetchOrderStateNames(): Promise<Array<{ technicalName: string; name: string }>> {
            const criteria = new Criteria(1, 50);
            criteria.addFilter(Criteria.equals('stateMachine.technicalName', 'order.state'));

            return (
                this.stateMachineStateRepository as {
                    search: (c: unknown) => Promise<Array<{ technicalName: string; translated?: { name: string }; name: string }>>;
                }
            )
                .search(criteria)
                .then((states) => {
                    return states.map((state) => {
                        return {
                            technicalName: state.technicalName,
                            name: state.translated?.name ?? state.name,
                        };
                    });
                })
                .catch(() => [] as Array<{ technicalName: string; name: string }>);
        },
    },
});
