import template from './sw-dashboard-top-products.html.twig';
import './sw-dashboard-top-products.scss';

const { Criteria } = Shopwell.Data;

type ProductBucket = { key: string; qtySum?: { sum?: number } };

/**
 * @sw-package after-sales
 *
 * The five products that sold the most units in the last 30 days, aggregated
 * from the order line items - the sales side of "what should never be out of
 * stock", next to the empty shelf counter in the attention list.
 *
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl'],

    data(): {
        products: Array<{ name: string; quantity: number }>;
        isLoading: boolean;
    } {
        return {
            products: [],
            isLoading: true,
        };
    },

    computed: {
        orderLineItemRepository(): unknown {
            return this.repositoryFactory.create('order_line_item');
        },

        productRepository(): unknown {
            return this.repositoryFactory.create('product');
        },

        canViewOrders(): boolean {
            return this.acl.can('order.viewer');
        },

        series(): Array<{ name: string; data: Array<{ x: string; y: number }> }> {
            return [
                {
                    name: this.$t('sw-dashboard.topProducts.seriesName'),
                    data: this.products.map((product) => {
                        return { x: product.name, y: product.quantity };
                    }),
                },
            ];
        },

        hasData(): boolean {
            return this.products.length > 0;
        },

        chartOptions(): Record<string, unknown> {
            return {
                chart: {
                    type: 'bar',
                },
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
        void this.loadProducts();
    },

    methods: {
        async loadProducts(): Promise<void> {
            if (!this.canViewOrders) {
                this.isLoading = false;

                return;
            }

            this.isLoading = true;

            try {
                const buckets = await this.fetchQuantityBuckets();

                if (buckets.length === 0) {
                    this.products = [];

                    return;
                }

                const names = await this.fetchProductNames(buckets.map((bucket) => bucket.key));

                this.products = buckets.map((bucket) => {
                    return {
                        name: names.get(bucket.key) ?? bucket.key,
                        quantity: bucket.qtySum?.sum ?? 0,
                    };
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Units sold per product over the last 30 days: one terms aggregation on
         * the line item's product with the quantity folded into the bucket, cut
         * to the top five client-side.
         */
        fetchQuantityBuckets(): Promise<ProductBucket[]> {
            const since = new Date();
            since.setDate(since.getDate() - 30);
            since.setHours(0, 0, 0, 0);

            const criteria = new Criteria(1, 5);
            criteria.addFilter(Criteria.equals('type', 'product'));
            criteria.addFilter(Criteria.range('order.orderDateTime', { gte: since.toISOString() }));
            criteria.addAggregation(Criteria.terms('topProducts', 'productId', 'qtySum', null, Criteria.sum('qtySum', 'quantity')));

            return (this.orderLineItemRepository as {
                search: (c: unknown) => Promise<{ aggregations: Record<string, { buckets?: ProductBucket[] }> }>;
            })
                .search(criteria)
                .then((result) => {
                    const buckets = result.aggregations?.topProducts?.buckets ?? [];

                    return buckets
                        .map((bucket) => {
                            return { key: bucket.key, quantity: bucket.qtySum?.sum ?? 0 };
                        })
                        .filter((bucket) => bucket.quantity > 0 && bucket.key !== 'null' && bucket.key !== '')
                        .sort((a, b) => b.quantity - a.quantity)
                        .slice(0, 5)
                        .map((bucket) => {
                            return { key: bucket.key, qtySum: { sum: bucket.quantity } };
                        });
                })
                .catch(() => [] as ProductBucket[]);
        },

        /**
         * The display names of the aggregated products, batched into one read -
         * a top list that shows raw uuids would be a chart nobody uses.
         */
        fetchProductNames(ids: string[]): Promise<Map<string, string>> {
            const criteria = new Criteria(1, ids.length);
            criteria.addFilter(Criteria.equalsAny('id', ids));

            return (this.productRepository as {
                search: (c: unknown) => Promise<Array<{ id: string; translated?: { name?: string }; name?: string }>>;
            })
                .search(criteria)
                .then((products) => {
                    return new Map(
                        products.map((product) => {
                            return [product.id, product.translated?.name ?? product.name ?? product.id];
                        }),
                    );
                })
                .catch(() => new Map<string, string>());
        },
    },
});
