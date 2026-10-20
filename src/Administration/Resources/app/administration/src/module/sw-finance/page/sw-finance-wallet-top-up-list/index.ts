/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-finance-wallet-top-up-list.html.twig';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

const DEFAULT_FILTERS: string[] = [
    'payment-no-filter',
    'state-filter',
    'channel-filter',
    'created-filter',
];

type WalletTopUp = EntitySchema.Entity<'customer_wallet_top_up'>;

type FilterDefinition = {
    property: string;
    type?: string;
    label: string;
    placeholder?: string;
    criteriaFilterType?: string;
    options?: Array<{ id: string; name: string }>;
    valueProperty?: string;
    labelProperty?: string;
    dateType?: string;
    fromFieldLabel?: string | null;
    toFieldLabel?: string | null;
    showTimeframe?: boolean;
};

interface TopUpFilterFactory {
    create: (entityName: string, filters: Record<string, FilterDefinition>) => FilterDefinition[];
}

/**
 * The filter factory is injected but not part of the typed service container yet.
 */
function topUpFilterFactory(vm: unknown): TopUpFilterFactory {
    return (vm as { filterFactory: TopUpFilterFactory }).filterFactory;
}

/**
 * The states of a top up and the badge colour of each.
 *
 * The badge vocabulary of the component library has five values and none of them is `success` or
 * `warning` - a state mapped to a word that does not exist renders an unstyled badge, which reads like
 * "no state" instead of "paid".
 */
const STATE_VARIANTS: Record<string, string> = {
    open: 'attention',
    paid: 'positive',
    credited: 'positive',
    failed: 'critical',
};

/**
 * The top up ledger of the customer wallets.
 *
 * Read only on purpose: a balance is written by the settlement of a paid top up and by the manual
 * adjustment on the customer detail page, never by an operator in a table. What this page answers is
 * "which top up did a customer start, was it paid, and was it credited" - and it answers it in the same
 * shape as every other list of the administration: full page, entity listing, sidebar filter panel and
 * two empty states.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        topUps: EntityCollection<'customer_wallet_top_up'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        defaultFilters: string[];
        storeKey: string;
        activeFilterNumber: number;
    } {
        return {
            topUps: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            defaultFilters: DEFAULT_FILTERS,
            storeKey: 'grid.filter.wallet_top_up',
            activeFilterNumber: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        topUpRepository(): RepositoryType<'customer_wallet_top_up'> {
            return this.repositoryFactory.create('customer_wallet_top_up');
        },

        currencyFilter(): (value: number, currency: string) => string {
            return Shopwell.Filter.getByName('currency') as (value: number, currency: string) => string;
        },

        dateFilter(): (value: string | Date) => string {
            return Shopwell.Filter.getByName('date') as (value: string | Date) => string;
        },

        /**
         * The search covers what an operator knows about a top up: the name and the number of the customer
         * and the number of the top up itself. A plain `term` would only hit the searchable fields the dal
         * ranks, and a payment number is not one of them.
         */
        topUpCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addAssociation('customer');
            criteria.addSorting(Criteria.sort(this.sortBy ?? 'createdAt', this.sortDirection, this.naturalSorting));

            const term = (this.term ?? '').trim();

            if (term !== '') {
                criteria.addFilter(
                    Criteria.multi('OR', [
                        Criteria.contains('customer.firstName', term),
                        Criteria.contains('customer.lastName', term),
                        Criteria.contains('customer.customerNumber', term),
                        Criteria.contains('paymentNo', term),
                    ]),
                );
            }

            return criteria;
        },

        listFilterOptions(): Record<string, FilterDefinition> {
            return {
                'payment-no-filter': {
                    property: 'paymentNo',
                    type: 'string-filter',
                    label: this.$t('sw-finance.walletTopUp.filters.paymentNoFilter.label'),
                    placeholder: this.$t('sw-finance.walletTopUp.filters.paymentNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'state-filter': {
                    property: 'state',
                    type: 'multi-select-filter',
                    label: this.$t('sw-finance.walletTopUp.filters.stateFilter.label'),
                    placeholder: this.$t('sw-finance.walletTopUp.filters.stateFilter.placeholder'),
                    options: this.stateFilterOptions,
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
                'channel-filter': {
                    property: 'channelTechnicalName',
                    type: 'string-filter',
                    label: this.$t('sw-finance.walletTopUp.filters.channelFilter.label'),
                    placeholder: this.$t('sw-finance.walletTopUp.filters.channelFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'created-filter': {
                    property: 'createdAt',
                    label: this.$t('sw-finance.walletTopUp.filters.createdFilter.label'),
                    dateType: 'date',
                    fromFieldLabel: null,
                    toFieldLabel: null,
                    showTimeframe: true,
                },
            };
        },

        listFilters(): FilterDefinition[] {
            return topUpFilterFactory(this).create('customer_wallet_top_up', this.listFilterOptions);
        },

        stateFilterOptions(): Array<{ id: string; name: string }> {
            return Object.keys(STATE_VARIANTS).map((state) => {
                return {
                    id: state,
                    name: this.stateLabel(state),
                };
            });
        },

        hasActiveSearchOrFilter(): boolean {
            return this.activeFilterNumber > 0 || Boolean(this.isValidTerm(this.term ?? ''));
        },

        columns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'customer',
                    label: 'sw-finance.walletTopUp.list.columnCustomer',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'paymentNo',
                    label: 'sw-finance.walletTopUp.list.columnPaymentNo',
                    allowResize: true,
                },
                {
                    property: 'amount',
                    label: 'sw-finance.walletTopUp.list.columnAmount',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'state',
                    label: 'sw-finance.walletTopUp.list.columnState',
                    allowResize: true,
                },
                {
                    property: 'channelTechnicalName',
                    label: 'sw-finance.walletTopUp.list.columnChannel',
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: 'sw-finance.walletTopUp.list.columnCreatedAt',
                    allowResize: true,
                },
                {
                    property: 'paidAt',
                    label: 'sw-finance.walletTopUp.list.columnPaidAt',
                    allowResize: true,
                },
                {
                    property: 'creditedAt',
                    label: 'sw-finance.walletTopUp.list.columnCreditedAt',
                    allowResize: true,
                },
            ];
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        async getList(): Promise<void> {
            this.isLoading = true;

            const filterService = Shopwell.Service('filterService') as unknown as {
                mergeWithStoredFilters: (storeKey: string, criteria: CriteriaType) => Promise<CriteriaType>;
            };

            // the service is async - without the await the criteria is a pending promise and reading its
            // filters throws, which leaves the grid permanently empty
            const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.topUpCriteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.topUpRepository
                .search(criteria)
                .then((searchResult) => {
                    this.total = searchResult.total ?? 0;
                    this.topUps = searchResult;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updateCriteria(filters: unknown[]): void {
            this.page = 1;
            this.topUpCriteria.filters = filters as CriteriaType['filters'];

            void this.getList();
        },

        onRefresh(): void {
            void this.getList();
        },

        /**
         * The wallet state is a plain string on the row, so the label comes from the snippet and falls back
         * to the technical value for a state a newer server may add.
         */
        stateLabel(state: string | null): string {
            if (!state) {
                return '–';
            }

            const key = `sw-finance.walletTopUp.state.${state}`;

            return this.$te(key) ? (this.$t(key)) : state;
        },

        stateVariant(state: string | null): string {
            return STATE_VARIANTS[state ?? ''] ?? 'neutral';
        },

        /**
         * The amount of a top up is a decimal in the main unit of its currency - not in the smallest unit
         * like the amount of a transfer. The currency filter therefore receives the value as it is.
         */
        formatAmount(topUp: WalletTopUp): string {
            if (topUp.amount === null || topUp.amount === undefined) {
                return '–';
            }

            return this.currencyFilter(topUp.amount, topUp.currency);
        },

        customerLabel(topUp: WalletTopUp): string {
            const customer = topUp.customer;

            if (!customer) {
                return topUp.customerId ?? '–';
            }

            // the association is loaded as a plain entity, so the two name fields are read off a record
            const { firstName, lastName } = customer as { firstName?: string | null; lastName?: string | null };
            const name = [firstName, lastName].filter(Boolean).join(' ');

            return customer.customerNumber ? `${name} (${customer.customerNumber})` : name;
        },

        formatDateOrDash(value: string | null | undefined): string {
            return value ? this.dateFilter(value) : '–';
        },
    },
});
