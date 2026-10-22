import template from './sw-dashboard-loyalty-distribution.html.twig';
import './sw-dashboard-loyalty-distribution.scss';

const { Criteria } = Shopwell.Data;

type TierBucket = { key: string; tierCount?: { count?: number } };

/**
 * @sw-package checkout
 *
 * How many members sit in each loyalty tier - the retention side of the
 * dashboard. A healthy shop shows a pyramid, a flat top means the top tier
 * never converts, an empty base means acquisition dried up.
 *
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl'],

    data(): {
        tiers: Array<{ name: string; count: number }>;
        isLoading: boolean;
    } {
        return {
            tiers: [],
            isLoading: true,
        };
    },

    computed: {
        customerLoyaltyRepository(): unknown {
            return this.repositoryFactory.create('customer_loyalty');
        },

        loyaltyTierRepository(): unknown {
            return this.repositoryFactory.create('loyalty_tier');
        },

        canViewLoyalty(): boolean {
            return this.acl.can('loyalty.viewer');
        },

        series(): Array<{ name: string; data: Array<{ x: string; y: number }> }> {
            return [
                {
                    name: this.$t('sw-dashboard.loyaltyDistribution.seriesName'),
                    data: this.tiers.map((tier) => {
                        return { x: tier.name, y: tier.count };
                    }),
                },
            ];
        },

        hasData(): boolean {
            return this.tiers.length > 0;
        },

        chartOptions(): Record<string, unknown> {
            return {
                plotOptions: {
                    bar: {
                        horizontal: true,
                        barHeight: '60%',
                    },
                },
                dataLabels: {
                    enabled: true,
                    formatter: (value: string) => parseInt(value, 10).toString(),
                },
                xaxis: {
                    labels: {
                        formatter: (value: string) => parseInt(value, 10).toString(),
                    },
                },
                legend: {
                    show: false,
                },
            };
        },
    },

    created() {
        void this.loadDistribution();
    },

    methods: {
        async loadDistribution(): Promise<void> {
            if (!this.canViewLoyalty) {
                this.isLoading = false;

                return;
            }

            this.isLoading = true;

            try {
                const [buckets, tiers] = await Promise.all([
                    this.fetchTierBuckets(),
                    this.fetchTierNames(),
                ]);

                const names = new Map(tiers.map((tier) => [tier.id, tier.name]));

                this.tiers = buckets.map((bucket) => {
                    return {
                        name: names.get(bucket.key) ?? bucket.key,
                        count: bucket.tierCount?.count ?? 0,
                    };
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * One terms aggregation over the membership tier of every customer
         * loyalty account, the member count folded into each bucket.
         */
        fetchTierBuckets(): Promise<TierBucket[]> {
            const criteria = new Criteria(1, 1);
            criteria.addAggregation(Criteria.terms('tierDistribution', 'membershipTierId', null, null, Criteria.count('tierCount', 'id')));

            return (this.customerLoyaltyRepository as {
                search: (c: unknown) => Promise<{ aggregations: Record<string, { buckets?: TierBucket[] }> }>;
            })
                .search(criteria)
                .then((result) => result.aggregations?.tierDistribution?.buckets ?? [])
                .catch(() => [] as TierBucket[]);
        },

        /**
         * The tier list itself, so every bucket key resolves to the tier name
         * the operator configured instead of a raw uuid.
         */
        fetchTierNames(): Promise<Array<{ id: string; name: string }>> {
            const criteria = new Criteria(1, 50);

            return (
                this.loyaltyTierRepository as {
                    search: (c: unknown) => Promise<Array<{ id: string; translated?: { name: string }; name: string }>>;
                }
            )
                .search(criteria)
                .then((tiers) => {
                    return tiers.map((tier) => {
                        return {
                            id: tier.id,
                            name: tier.translated?.name ?? tier.name,
                        };
                    });
                })
                .catch(() => [] as Array<{ id: string; name: string }>);
        },
    },
});
