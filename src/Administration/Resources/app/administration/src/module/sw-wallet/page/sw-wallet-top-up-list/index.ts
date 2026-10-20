/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

import template from './sw-wallet-top-up-list.html.twig';

const { Criteria } = Shopwell.Data;

const STATES = ['open', 'paid', 'credited', 'failed'];

interface WalletTopUp {
    id: string;
    customerId: string;
    amount: number;
    currency: string;
    state: string;
    paymentNo: string | null;
    channelTechnicalName: string | null;
    paidAt: string | null;
    creditedAt: string | null;
    createdAt: string | null;
    customer: { id: string; firstName: string; lastName: string; customerNumber: string } | null;
}

/**
 * Every recharge a customer started in the storefront - whether it was paid, is still open at the
 * channel or failed. Read only: the balance is written by the settlement, never by the operator, and
 * a manual correction belongs on the customer detail page where it is booked with a comment.
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
        topUps: WalletTopUp[];
        stateFilter: string | null;
    } {
        return {
            isLoading: true,
            page: 1,
            limit: 25,
            total: 0,
            term: '',
            topUps: [],
            stateFilter: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        topUpRepository() {
            return this.repositoryFactory.create('customer_wallet_top_up');
        },

        topUpCriteria(): CriteriaType {
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
                    label: 'sw-wallet.topUp.list.columnCustomer',
                },
                {
                    property: 'amount',
                    label: 'sw-wallet.topUp.list.columnAmount',
                },
                {
                    property: 'state',
                    label: 'sw-wallet.topUp.list.columnState',
                },
                {
                    property: 'channelTechnicalName',
                    label: 'sw-wallet.topUp.list.columnChannel',
                },
                {
                    property: 'createdAt',
                    label: 'sw-wallet.topUp.list.columnCreatedAt',
                },
                {
                    property: 'creditedAt',
                    label: 'sw-wallet.topUp.list.columnCreditedAt',
                },
            ];
        },

        stateFilterOptions() {
            return [
                { value: null, label: this.$t('sw-wallet.topUp.list.filterStateAll') },
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
                const result = await this.topUpRepository.search(this.topUpCriteria);

                this.topUps = result as unknown as WalletTopUp[];
                this.total = result.total ?? this.topUps.length;
            } finally {
                this.isLoading = false;
            }
        },

        getStateLabel(state: string): string {
            return this.$t(`sw-wallet.topUp.state.${state}`, {}, 0);
        },

        getStateVariant(state: string): string {
            if (state === 'credited' || state === 'paid') {
                return 'success';
            }

            return state === 'failed' ? 'error' : 'warning';
        },

        formatAmount(topUp: WalletTopUp): string {
            return this.currencyFilter(topUp.amount, topUp.currency) as string;
        },

        formatCustomer(topUp: WalletTopUp): string {
            if (!topUp.customer) {
                return topUp.customerId;
            }

            return `${topUp.customer.firstName} ${topUp.customer.lastName} (${topUp.customer.customerNumber})`;
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
