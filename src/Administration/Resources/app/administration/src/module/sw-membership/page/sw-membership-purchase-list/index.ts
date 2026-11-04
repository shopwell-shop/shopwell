/**
 * @sw-package checkout
 *
 * Every membership a customer bought, newest first.
 *
 * The list answers "which membership is the customer talking about": the customer, the plan they
 * paid for and the state of the money. It is read-only on purpose - a membership changes through
 * the payment or through the customer detail page, never by editing the purchase row itself.
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';

import template from './sw-membership-purchase-list.html.twig';
import './sw-membership-purchase-list.scss';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

type MembershipPurchase = EntitySchema.Entity<'membership_purchase'>;

const STATES = ['open', 'activated', 'failed'] as const;

/**
 * The badge colour of a purchase state, everything unknown falls back to `neutral`.
 */
const STATE_VARIANTS: Record<string, string> = {
    open: 'info',
    activated: 'positive',
    failed: 'critical',
};

type FilterOption = { id: string; name: string };

type FilterDefinition = {
    property: string;
    type?: string;
    label: string;
    placeholder: string;
    options?: FilterOption[];
    valueProperty?: string;
    labelProperty?: string;
};

interface FilterFactory {
    create: (entityName: string, filters: Record<string, FilterDefinition>) => FilterDefinition[];
}

/**
 * The service that merges the filters a merchant stored on the server into a criteria.
 */
interface FilterService {
    mergeWithStoredFilters: (storeKey: string, criteria: CriteriaType) => Promise<CriteriaType>;
}

/**
 * The stored filter set of this grid: the sidebar renders the defaults in this order, and the stored
 * selection of a merchant refers to their names.
 */
const DEFAULT_FILTERS: string[] = [
    'plan-filter',
    'state-filter',
];

/**
 * The filter factory is injected but not part of the typed service container, so it is read through
 * the component instance instead of widening the global type for one page.
 */
function purchaseFilterFactory(vm: unknown): FilterFactory {
    return (vm as { filterFactory: FilterFactory }).filterFactory;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- page component, rendered through the module route
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        purchases: EntityCollection<'membership_purchase'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        defaultFilters: string[];
        storeKey: string;
        activeFilterNumber: number;
        activeFilters: CriteriaType['filters'];
    } {
        return {
            purchases: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            defaultFilters: DEFAULT_FILTERS,
            storeKey: 'grid.filter.membership_purchase',
            activeFilterNumber: 0,
            activeFilters: [],
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

        criteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term ?? '');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('customer');

            // the filters of the panel are part of the criteria instead of being written into a
            // cached instance: this one is rebuilt per search, and a value written elsewhere would
            // not survive the next rebuild
            if (this.activeFilters.length > 0) {
                criteria.filters = this.activeFilters;
            }

            return criteria;
        },

        listFilterOptions(): Record<string, FilterDefinition> {
            return {
                'plan-filter': {
                    property: 'planName',
                    type: 'string-filter',
                    label: this.$t('sw-membership.purchase.list.filterPlanLabel'),
                    placeholder: this.$t('sw-membership.purchase.list.filterPlanPlaceholder'),
                },
                'state-filter': {
                    property: 'state',
                    type: 'multi-select-filter',
                    label: this.$t('sw-membership.purchase.list.filterStateLabel'),
                    placeholder: this.$t('sw-membership.purchase.list.filterStatePlaceholder'),
                    options: this.stateFilterOptions,
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
            };
        },

        listFilters(): FilterDefinition[] {
            return purchaseFilterFactory(this).create('membership_purchase', this.listFilterOptions);
        },

        stateFilterOptions(): FilterOption[] {
            return STATES.map((state) => {
                return { id: state, name: this.getStateLabel(state) };
            });
        },

        hasActiveSearchOrFilter(): boolean {
            return this.activeFilterNumber > 0 || Boolean(this.isValidTerm(this.term ?? ''));
        },

        columns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'customer',
                    label: 'sw-membership.purchase.list.columnCustomer',
                    // an association: the DAL cannot sort the queue by it
                    sortable: false,
                    allowResize: true,
                },
                {
                    property: 'planName',
                    label: 'sw-membership.purchase.list.columnPlan',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'amount',
                    label: 'sw-membership.purchase.list.columnAmount',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'state',
                    label: 'sw-membership.purchase.list.columnState',
                    allowResize: true,
                },
                {
                    property: 'paidAt',
                    label: 'sw-membership.purchase.list.columnPaidAt',
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: 'sw-membership.purchase.list.columnCreatedAt',
                    allowResize: true,
                },
            ];
        },
    },

    // the `listing` mixin loads the list in its own `created` hook: no load here, or the queue is
    // fetched twice on every page open

    methods: {
        async getList(): Promise<void> {
            this.isLoading = true;

            // the filter service is not part of the typed service container
            const filterService = Shopwell.Service('filterService') as FilterService;

            const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.criteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.purchaseRepository
                .search(criteria)
                .then((searchResult) => {
                    this.total = searchResult.total ?? 0;
                    this.purchases = searchResult;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updateCriteria(filters: CriteriaType['filters']): void {
            this.page = 1;
            this.activeFilters = filters;
            this.activeFilterNumber = filters.length;

            void this.getList();
        },

        getStateLabel(state: string): string {
            return this.$t(`sw-membership.purchase.state.${state}`, {}, 0);
        },

        getStateVariant(state: string): string {
            return STATE_VARIANTS[state] ?? 'neutral';
        },

        formatAmount(purchase: MembershipPurchase): string {
            return Shopwell.Filter.getByName('currency')(purchase.amount, purchase.currency) as string;
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
            return date ? Shopwell.Filter.getByName('date')(date) : '-';
        },
    },
});
