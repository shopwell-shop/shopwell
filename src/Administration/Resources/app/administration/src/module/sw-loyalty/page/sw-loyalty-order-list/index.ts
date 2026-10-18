/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

import template from './sw-loyalty-order-list.html.twig';

const { Criteria } = Shopwell.Data;

const STATES = ['open', 'activated', 'failed'];

interface MembershipOrder {
    id: string;
    customerId: string;
    planName: string;
    amount: number;
    currency: string;
    state: string;
    durationDays: number;
    paymentNo: string | null;
    paidAt: string | null;
    expiresAt: string | null;
    createdAt: string | null;
    customer: { id: string; firstName: string; lastName: string; customerNumber: string } | null;
}

/**
 * Every membership that was bought - or attempted - in the shop. The list is read only on purpose:
 * a membership changes through the payment or through the customer detail page, never by editing the
 * order row itself.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    data(): {
        isLoading: boolean;
        page: number;
        limit: number;
        total: number;
        term: string;
        orders: MembershipOrder[];
        stateFilter: string | null;
    } {
        return {
            isLoading: true,
            page: 1,
            limit: 25,
            total: 0,
            term: '',
            orders: [],
            stateFilter: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('membership_order');
        },

        orderCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addAssociation('customer');
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            if (this.term !== '') {
                criteria.setTerm(this.term);
            }

            if (this.stateFilter) {
                criteria.addFilter(Criteria.equals('state', this.stateFilter));
            }

            return criteria;
        },

        columns() {
            return [
                {
                    property: 'customer',
                    label: 'sw-loyalty.order.list.columnCustomer',
                },
                {
                    property: 'planName',
                    label: 'sw-loyalty.order.list.columnPlan',
                },
                {
                    property: 'amount',
                    label: 'sw-loyalty.order.list.columnAmount',
                },
                {
                    property: 'state',
                    label: 'sw-loyalty.order.list.columnState',
                },
                {
                    property: 'paidAt',
                    label: 'sw-loyalty.order.list.columnPaidAt',
                },
                {
                    property: 'createdAt',
                    label: 'sw-loyalty.order.list.columnCreatedAt',
                },
            ];
        },

        stateFilterOptions() {
            return [
                { value: null, label: this.$t('sw-loyalty.order.list.filterStateAll') },
                ...STATES.map((state) => {
                    return { value: state, label: this.getStateLabel(state) };
                }),
            ];
        },

        currencyFilter() {
            return Shopwell.Filter.getByName('currency');
        },

        dateFilter() {
            return Shopwell.Filter.getByName('date');
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        async getList(): Promise<void> {
            this.isLoading = true;

            try {
                const result = await this.orderRepository.search(this.orderCriteria);

                this.orders = result as unknown as MembershipOrder[];
                this.total = result.total ?? this.orders.length;
            } finally {
                this.isLoading = false;
            }
        },

        getStateLabel(state: string): string {
            return this.$t(`sw-loyalty.order.state.${state}`, {}, 0);
        },

        getStateVariant(state: string): string {
            if (state === 'activated') {
                return 'success';
            }

            return state === 'failed' ? 'error' : 'warning';
        },

        formatAmount(order: MembershipOrder): string {
            return this.currencyFilter(order.amount, order.currency) as string;
        },

        formatCustomer(order: MembershipOrder): string {
            if (!order.customer) {
                return order.customerId;
            }

            return `${order.customer.firstName} ${order.customer.lastName} (${order.customer.customerNumber})`;
        },

        formatDate(date: string | null): string {
            return date ? this.dateFilter(date) : '-';
        },

        onSearch(term: string): void {
            this.term = term;
            this.page = 1;

            void this.getList();
        },

        onRefresh(): void {
            void this.getList();
        },

        onFilterChange(): void {
            this.page = 1;

            void this.getList();
        },

        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;

            void this.getList();
        },
    },
});
