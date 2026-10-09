import template from './sw-dashboard-latest-orders.html.twig';
import './sw-dashboard-latest-orders.scss';

const { Criteria } = Shopwell.Data;

type StateMachineState = {
    technicalName: string;
    name: string;
    translated?: { name: string };
};

type OrderEntity = {
    id: string;
    orderNumber: string;
    orderDateTime: string;
    amountTotal: number;
    orderCustomer?: { firstName: string; lastName: string } | null;
    currency?: { isoCode: string } | null;
    stateMachineState?: StateMachineState | null;
};

type OrderCollection = { length: number } & Iterable<OrderEntity>;

// The dashboard only previews the most recent orders, it is not a second order list.
const ORDER_LIMIT = 5;

/**
 * @sw-package after-sales
 *
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'stateStyleDataProviderService', 'acl'],

    data(): {
        orders: OrderCollection | null;
        sortBy: string;
        sortDirection: 'DESC' | 'ASC';
        isLoading: boolean;
    } {
        return {
            orders: null,
            sortBy: 'orderDateTime',
            sortDirection: 'DESC',
            isLoading: true,
        };
    },

    computed: {
        orderRepository(): unknown {
            return this.repositoryFactory.create('order');
        },

        canViewOrders(): boolean {
            return this.acl.can('order.viewer');
        },

        currencyFilter(): (value: number, isoCode?: string, decimals?: number) => string {
            return Shopwell.Filter.getByName('currency');
        },
    },

    created() {
        void this.loadOrders();
    },

    methods: {
        async loadOrders(): Promise<void> {
            if (!this.canViewOrders) {
                this.isLoading = false;

                return;
            }

            this.isLoading = true;

            const criteria = new Criteria(1, ORDER_LIMIT);

            // The preview is not paged: skipping the total count avoids an
            // unnecessary COUNT query and keeps the listing's pager hidden.
            criteria.setTotalCountMode(0);

            criteria.addAssociation('orderCustomer');
            criteria.addAssociation('currency');
            criteria.addAssociation('stateMachineState');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            try {
                this.orders = await (
                    this.orderRepository as {
                        search: (criteria: unknown) => Promise<OrderCollection>;
                    }
                ).search(criteria);
            } catch {
                this.orders = null;
            } finally {
                this.isLoading = false;
            }
        },

        orderGridColumns() {
            return [
                {
                    property: 'orderNumber',
                    label: 'sw-order.list.columnOrderNumber',
                    routerLink: 'sw.order.detail',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'orderDateTime',
                    dataIndex: 'orderDateTime',
                    label: 'sw-dashboard.latestOrders.columnOrderTime',
                    allowResize: true,
                    primary: false,
                },
                {
                    property: 'orderCustomer.firstName',
                    dataIndex: 'orderCustomer.firstName,orderCustomer.lastName',
                    label: 'sw-order.list.columnCustomerName',
                    allowResize: true,
                },
                {
                    property: 'stateMachineState.name',
                    label: 'sw-order.list.columnState',
                    allowResize: true,
                },
                {
                    property: 'amountTotal',
                    label: 'sw-order.list.columnAmount',
                    align: 'right',
                    allowResize: true,
                },
            ];
        },

        getVariantFromOrderState(order: OrderEntity): string {
            const state = order.stateMachineState?.technicalName;

            if (!state) {
                return 'neutral';
            }

            return this.stateStyleDataProviderService.getStyle('order.state', state).meteorVariant;
        },

        getOrderStateLabel(order: OrderEntity): string {
            return order.stateMachineState?.translated?.name ?? order.stateMachineState?.name ?? '';
        },

        getCustomerName(order: OrderEntity): string {
            const customer = order.orderCustomer;

            if (!customer) {
                return '';
            }

            return `${customer.firstName} ${customer.lastName}`.trim();
        },
    },
});
