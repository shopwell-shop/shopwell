/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';

import template from './sw-membership-purchase-list.html.twig';

const { Criteria } = Shopwell.Data;

const STATES = ['open', 'activated', 'failed'];

type MembershipPurchase = EntitySchema.Entity<'membership_purchase'>;

/**
 * Every membership a customer bought in the shop, one row per purchase attempt. The list is read only
 * on purpose: a membership changes through the payment or through the customer detail page, never by
 * editing the purchase row itself. The membership that is in force afterwards is an account question
 * and lives on the subscription page.
 *
 * The twig blocks are still named `sw_loyalty_order_list*`: a block name is what a plugin extends, so
 * it is not renamed along with the directory.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- the module registers the page, a plugin must not import it
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    data(): {
        isLoading: boolean;
        page: number;
        limit: number;
        total: number;
        term: string;
        purchases: EntityCollection<'membership_purchase'> | null;
        stateFilter: string | null;
    } {
        return {
            isLoading: true,
            page: 1,
            limit: 25,
            total: 0,
            term: '',
            purchases: null,
            stateFilter: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        purchaseRepository(): RepositoryType<'membership_purchase'> {
            return this.repositoryFactory.create('membership_purchase');
        },

        purchaseCriteria(): CriteriaType {
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
                    label: 'sw-membership.purchase.list.columnCustomer',
                },
                {
                    property: 'planName',
                    label: 'sw-membership.purchase.list.columnPlan',
                },
                {
                    property: 'amount',
                    label: 'sw-membership.purchase.list.columnAmount',
                },
                {
                    property: 'state',
                    label: 'sw-membership.purchase.list.columnState',
                },
                {
                    property: 'paidAt',
                    label: 'sw-membership.purchase.list.columnPaidAt',
                },
                {
                    property: 'createdAt',
                    label: 'sw-membership.purchase.list.columnCreatedAt',
                },
            ];
        },

        stateFilterOptions() {
            return [
                { value: null, label: this.$t('sw-membership.purchase.list.filterStateAll') },
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
                const result = await this.purchaseRepository.search(this.purchaseCriteria);

                this.purchases = result;
                this.total = result.total ?? result.length;
            } finally {
                this.isLoading = false;
            }
        },

        getStateLabel(state: string): string {
            return this.$t(`sw-membership.purchase.state.${state}`, {}, 0);
        },

        getStateVariant(state: string): string {
            if (state === 'activated') {
                return 'success';
            }

            return state === 'failed' ? 'error' : 'warning';
        },

        formatAmount(purchase: MembershipPurchase): string {
            return this.currencyFilter(purchase.amount, purchase.currency) as string;
        },

        /**
         * Falls back to the id when the customer row was not loaded: the row still has to say whose
         * purchase it was, and an empty cell would read like "nobody bought this".
         */
        formatCustomer(purchase: MembershipPurchase): string {
            const customer = purchase.customer;

            if (!customer) {
                return purchase.customerId;
            }

            return `${customer.firstName} ${customer.lastName} (${customer.customerNumber ?? purchase.customerId})`;
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
