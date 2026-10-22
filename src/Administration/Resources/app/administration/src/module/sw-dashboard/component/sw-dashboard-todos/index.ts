import template from './sw-dashboard-todos.html.twig';
import './sw-dashboard-todos.scss';
import {
    ORDER_DELIVERY_FILTER,
    ORDER_DELIVERY_PROPERTY,
    buildFilteredOrderListLink,
    type StateSelection,
} from '../../helper/order-list-filter';

const { Criteria } = Shopwell.Data;

type TodoCount = {
    returnRequests: number;
    ordersToShip: number;
    reviewsWaiting: number;
    outOfStock: number;
};

type TodoLink = { name: string; query: Record<string, string> } | null;

type TodoItem = {
    key: string;
    icon: string;
    label: string;
    count: number;
    variant: 'critical' | 'attention' | 'neutral';
    link: TodoLink;
};

const EMPTY_COUNTS: TodoCount = {
    returnRequests: 0,
    ordersToShip: 0,
    reviewsWaiting: 0,
    outOfStock: 0,
};

/**
 * @sw-package after-sales
 *
 * The radar part of the dashboard: every entry is a non-zero counter of work
 * that waits for an operator, ordered by how much it can hurt - open return
 * requests hold back refunds, unshipped orders hold back the customer promise,
 * unseen reviews hold back trust, empty shelves hold back revenue. A counter
 * of zero never renders: an empty dashboard is a good dashboard.
 *
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl'],

    data(): {
        counts: TodoCount;
        deliveryState: StateSelection | null;
        isLoading: boolean;
    } {
        return {
            counts: { ...EMPTY_COUNTS },
            deliveryState: null,
            isLoading: true,
        };
    },

    computed: {
        orderRepository(): unknown {
            return this.repositoryFactory.create('order');
        },

        orderReturnRepository(): unknown {
            return this.repositoryFactory.create('order_return');
        },

        reviewRepository(): unknown {
            return this.repositoryFactory.create('product_review');
        },

        productWarehouseRepository(): unknown {
            return this.repositoryFactory.create('product_warehouse');
        },

        stateMachineStateRepository(): unknown {
            return this.repositoryFactory.create('state_machine_state');
        },

        canViewOrders(): boolean {
            return this.acl.can('order.viewer');
        },

        canViewOrderReturns(): boolean {
            return this.acl.can('order_return.viewer');
        },

        canViewReviews(): boolean {
            return this.acl.can('review.viewer');
        },

        canViewInventory(): boolean {
            return this.acl.can('inventory.viewer');
        },

        items(): TodoItem[] {
            const items: TodoItem[] = [];

            if (this.canViewOrders && this.canViewOrderReturns && this.counts.returnRequests > 0) {
                items.push({
                    key: 'returnRequests',
                    icon: 'regular-undo',
                    label: this.$t('sw-dashboard.todos.returnRequests'),
                    count: this.counts.returnRequests,
                    variant: 'critical',
                    link: { name: 'sw.order.return.list', query: {} },
                });
            }

            if (this.canViewOrders && this.counts.ordersToShip > 0) {
                items.push({
                    key: 'ordersToShip',
                    icon: 'regular-truck',
                    label: this.$t('sw-dashboard.todos.ordersToShip'),
                    count: this.counts.ordersToShip,
                    variant: 'attention',
                    link: this.buildShipLink(),
                });
            }

            if (this.canViewReviews && this.counts.reviewsWaiting > 0) {
                items.push({
                    key: 'reviewsWaiting',
                    icon: 'regular-comments',
                    label: this.$t('sw-dashboard.todos.reviewsWaiting'),
                    count: this.counts.reviewsWaiting,
                    variant: 'attention',
                    link: { name: 'sw.review.index', query: {} },
                });
            }

            if (this.canViewInventory && this.counts.outOfStock > 0) {
                items.push({
                    key: 'outOfStock',
                    icon: 'regular-box',
                    label: this.$t('sw-dashboard.todos.outOfStock'),
                    count: this.counts.outOfStock,
                    variant: 'attention',
                    link: { name: 'sw.inventory.warehouse.list', query: {} },
                });
            }

            return items;
        },

        hasItems(): boolean {
            return this.items.length > 0;
        },
    },

    created() {
        void this.loadCounts();
    },

    methods: {
        async loadCounts(): Promise<void> {
            this.isLoading = true;

            try {
                const [returnRequests, deliveryState, ordersToShip, reviewsWaiting, outOfStock] = await Promise.all([
                    this.fetchReturnRequestCount(),
                    this.fetchDeliveryOpenState(),
                    this.fetchOrdersToShipCount(),
                    this.fetchReviewsWaitingCount(),
                    this.fetchOutOfStockCount(),
                ]);

                this.deliveryState = deliveryState;
                this.counts = {
                    returnRequests,
                    ordersToShip: deliveryState === null ? 0 : ordersToShip,
                    reviewsWaiting,
                    outOfStock,
                };
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Returns the customer asked for and the shop has not decided yet - the
         * `requested` state of the return state machine.
         */
        fetchReturnRequestCount(): Promise<number> {
            if (!this.canViewOrderReturns) {
                return Promise.resolve(0);
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('stateMachineState.technicalName', 'requested'));
            criteria.setTotalCountMode(1);

            return (this.orderReturnRepository as { search: (c: unknown) => Promise<{ total: number }> })
                .search(criteria)
                .then((result) => result.total)
                .catch(() => 0);
        },

        /**
         * The `open` delivery state, so the count and the deep link describe the
         * same thing: parcels the shop still has to hand over.
         */
        fetchDeliveryOpenState(): Promise<StateSelection | null> {
            if (!this.canViewOrders) {
                return Promise.resolve(null);
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('stateMachine.technicalName', 'order_delivery.state'));
            criteria.addFilter(Criteria.equals('technicalName', 'open'));

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

        fetchOrdersToShipCount(): Promise<number> {
            if (!this.canViewOrders) {
                return Promise.resolve(0);
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('primaryOrderDelivery.stateMachineState.technicalName', 'open'));
            criteria.setTotalCountMode(1);

            return (this.orderRepository as { search: (c: unknown) => Promise<{ total: number }> })
                .search(criteria)
                .then((result) => result.total)
                .catch(() => 0);
        },

        /**
         * A review waits while it has not been made visible yet - `status` is the
         * visibility flag of the review, false means still unseen by the shop.
         */
        fetchReviewsWaitingCount(): Promise<number> {
            if (!this.canViewReviews) {
                return Promise.resolve(0);
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('status', false));
            criteria.setTotalCountMode(1);

            return (this.reviewRepository as { search: (c: unknown) => Promise<{ total: number }> })
                .search(criteria)
                .then((result) => result.total)
                .catch(() => 0);
        },

        /**
         * Stock rows live per warehouse, so an SKU out in one warehouse counts
         * once per location - the operator has to fix the location, not the SKU.
         */
        fetchOutOfStockCount(): Promise<number> {
            if (!this.canViewInventory) {
                return Promise.resolve(0);
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.range('stock', { lte: 0 }));
            criteria.setTotalCountMode(1);

            return (this.productWarehouseRepository as { search: (c: unknown) => Promise<{ total: number }> })
                .search(criteria)
                .then((result) => result.total)
                .catch(() => 0);
        },

        /**
         * Deep link into the order list pre-filtered to open deliveries, mirroring
         * what the count above aggregates.
         */
        buildShipLink(): TodoLink {
            if (!this.deliveryState) {
                return null;
            }

            return buildFilteredOrderListLink({
                state: {
                    filterName: ORDER_DELIVERY_FILTER,
                    property: ORDER_DELIVERY_PROPERTY,
                    state: this.deliveryState,
                },
            });
        },
    },
});
