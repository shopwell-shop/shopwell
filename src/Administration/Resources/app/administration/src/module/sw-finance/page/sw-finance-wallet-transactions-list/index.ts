/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-finance-wallet-transactions-list.html.twig';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

const DEFAULT_FILTERS: string[] = [
    'type-filter',
    'created-filter',
];

type WalletTransaction = EntitySchema.Entity<'customer_wallet_transaction'>;

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

interface TransactionFilterFactory {
    create: (entityName: string, filters: Record<string, FilterDefinition>) => FilterDefinition[];
}

/**
 * The filter factory is injected but not part of the typed service container yet.
 */
function transactionFilterFactory(vm: unknown): TransactionFilterFactory {
    return (vm as { filterFactory: TransactionFilterFactory }).filterFactory;
}

/**
 * The types of a ledger entry and the badge colour of each. A top up and a refund put money into the
 * wallet, a purchase takes it out, an adjustment is the neutral bookkeeping of both directions.
 *
 * The badge vocabulary of the component library has five values and none of them is `success` or
 * `warning` - a type mapped to a word that does not exist renders an unstyled badge, which reads like
 * "no type" instead of "top-up".
 */
const TYPE_VARIANTS: Record<string, string> = {
    top_up: 'positive',
    refund: 'positive',
    consume: 'attention',
    adjust: 'neutral',
};

/**
 * The ledger of the customer wallets.
 *
 * Read only on purpose: the balance is written by the ledger alone, and every change of it is one row
 * here - a paid top up as much as a purchase at the checkout, a refund and a manual adjustment. A top
 * up is therefore not a page of its own any more, it is one type among the entries. What this page
 * answers is "why did this wallet move, in which direction and when" - and it answers it in the same
 * shape as every other list of the administration: full page, entity listing, sidebar filter panel and
 * two empty states.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        transactions: EntityCollection<'customer_wallet_transaction'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        defaultFilters: string[];
        storeKey: string;
        activeFilterNumber: number;
    } {
        return {
            transactions: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            defaultFilters: DEFAULT_FILTERS,
            storeKey: 'grid.filter.wallet_transaction',
            activeFilterNumber: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        transactionRepository(): RepositoryType<'customer_wallet_transaction'> {
            return this.repositoryFactory.create('customer_wallet_transaction');
        },

        currencyFilter(): (value: number, currency: string) => string {
            return Shopwell.Filter.getByName('currency') as (value: number, currency: string) => string;
        },

        dateFilter(): (value: string | Date) => string {
            return Shopwell.Filter.getByName('date') as (value: string | Date) => string;
        },

        /**
         * The search covers what an operator knows about a movement: the name and the number of the
         * customer and the external reference the entry was booked with. A plain `term` would only hit
         * the searchable fields the dal ranks, and a reference is not one of them.
         */
        transactionCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addAssociation('wallet.customer');
            criteria.addAssociation('currency');
            criteria.addAssociation('order');
            criteria.addSorting(Criteria.sort(this.sortBy ?? 'createdAt', this.sortDirection, this.naturalSorting));

            const term = (this.term ?? '').trim();

            if (term !== '') {
                criteria.addFilter(
                    Criteria.multi('OR', [
                        Criteria.contains('wallet.customer.firstName', term),
                        Criteria.contains('wallet.customer.lastName', term),
                        Criteria.contains('wallet.customer.customerNumber', term),
                        Criteria.contains('externalReference', term),
                    ]),
                );
            }

            return criteria;
        },

        listFilterOptions(): Record<string, FilterDefinition> {
            return {
                'type-filter': {
                    property: 'type',
                    type: 'multi-select-filter',
                    label: this.$t('sw-finance.walletTransactions.filters.typeFilter.label'),
                    placeholder: this.$t('sw-finance.walletTransactions.filters.typeFilter.placeholder'),
                    options: this.typeFilterOptions,
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
                'created-filter': {
                    property: 'createdAt',
                    label: this.$t('sw-finance.walletTransactions.filters.createdFilter.label'),
                    dateType: 'date',
                    fromFieldLabel: null,
                    toFieldLabel: null,
                    showTimeframe: true,
                },
            };
        },

        listFilters(): FilterDefinition[] {
            return transactionFilterFactory(this).create('customer_wallet_transaction', this.listFilterOptions);
        },

        typeFilterOptions(): Array<{ id: string; name: string }> {
            return Object.keys(TYPE_VARIANTS).map((type) => {
                return {
                    id: type,
                    name: this.typeLabel(type),
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
                    label: 'sw-finance.walletTransactions.list.columnCustomer',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'type',
                    label: 'sw-finance.walletTransactions.list.columnType',
                    allowResize: true,
                },
                {
                    property: 'amount',
                    label: 'sw-finance.walletTransactions.list.columnAmount',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'balanceAfter',
                    label: 'sw-finance.walletTransactions.list.columnBalanceAfter',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'order',
                    label: 'sw-finance.walletTransactions.list.columnOrder',
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: 'sw-finance.walletTransactions.list.columnCreatedAt',
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
            const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.transactionCriteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.transactionRepository
                .search(criteria)
                .then((searchResult) => {
                    this.total = searchResult.total ?? 0;
                    this.transactions = searchResult;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updateCriteria(filters: unknown[]): void {
            this.page = 1;
            this.transactionCriteria.filters = filters as CriteriaType['filters'];

            void this.getList();
        },

        onRefresh(): void {
            void this.getList();
        },

        /**
         * The transaction type is a plain string on the row, so the label comes from the snippet and
         * falls back to the technical value for a type a newer server may add.
         */
        typeLabel(type: string | null): string {
            if (!type) {
                return '–';
            }

            const key = `sw-finance.walletTransactions.type.${type}`;

            return this.$te(key) ? this.$t(key) : type;
        },

        typeVariant(type: string | null): string {
            return TYPE_VARIANTS[type ?? ''] ?? 'neutral';
        },

        /**
         * The amount of a transaction is a signed decimal in the main unit of its currency - not in the
         * smallest unit like the amount of a transfer, and it is never divided here. An incoming entry
         * (a top up, a refund) is marked with a leading plus, an outgoing one keeps the minus the
         * currency filter renders for a negative value.
         */
        formatAmount(transaction: WalletTransaction): string {
            if (transaction.amount === null || transaction.amount === undefined) {
                return '–';
            }

            const formatted = this.currencyFilter(transaction.amount, this.currencyIsoCode(transaction));

            return transaction.amount > 0 ? `+${formatted}` : formatted;
        },

        formatBalanceAfter(transaction: WalletTransaction): string {
            if (transaction.balanceAfter === null || transaction.balanceAfter === undefined) {
                return '–';
            }

            return this.currencyFilter(transaction.balanceAfter, this.currencyIsoCode(transaction));
        },

        currencyIsoCode(transaction: WalletTransaction): string {
            return (transaction.currency as { isoCode?: string } | null)?.isoCode ?? '';
        },

        orderNumber(transaction: WalletTransaction): string {
            const order = transaction.order as { orderNumber?: string | null } | null;

            return order?.orderNumber ?? '–';
        },

        customerLabel(transaction: WalletTransaction): string {
            const customer = transaction.wallet?.customer as {
                firstName?: string | null;
                lastName?: string | null;
                customerNumber?: string | null;
            } | null;

            if (!customer) {
                return transaction.wallet?.customerId ?? '–';
            }

            // the association is loaded as a plain entity, so the two name fields are read off a record
            const name = [customer.firstName, customer.lastName].filter(Boolean).join(' ');

            return customer.customerNumber ? `${name} (${customer.customerNumber})` : name;
        },

        formatDateOrDash(value: string | null | undefined): string {
            return value ? this.dateFilter(value) : '–';
        },
    },
});
