/**
 * @sw-package after-sales
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-order-return-list.html.twig';
import './sw-order-return-list.scss';
import {
    ORDER_RETURN_STATE_MACHINE,
    formatReturnAmount,
    returnStateLabelKey,
    returnStateVariant,
    returnTypeLabelKey,
} from '../../service/order-return.api.service';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

/**
 * The stored filter set of this grid. Declared once, because the sidebar panel renders the defaults in
 * this order and the stored selection refers to the names.
 */
const DEFAULT_FILTERS: string[] = ['return-no-filter', 'order-no-filter', 'state-filter'];

type OrderReturn = EntitySchema.Entity<'order_return'>;
type StateMachineState = EntitySchema.Entity<'state_machine_state'>;

/**
 * The three fields of a customer the queue needs.
 *
 * The entity type of the administration carries the whole customer, but the name is assembled from
 * fields the generated type does not expose on every path - so the page names the shape it reads
 * instead of wandering through the generated one.
 */
type CustomerName = {
    firstName?: string | null;
    lastName?: string | null;
    email?: string | null;
};

type FilterDefinition = {
    property: string;
    type: string;
    label: string;
    placeholder: string;
    criteriaFilterType?: string;
    options?: Array<{ id: string; name: string }>;
    valueProperty?: string;
    labelProperty?: string;
};

interface FilterFactory {
    create: (entityName: string, filters: Record<string, FilterDefinition>) => FilterDefinition[];
}

/**
 * The filter factory is injected but not part of the typed service container yet, so it is looked up
 * through the component instance instead of widening the global type for one page.
 */
function orderReturnFilterFactory(vm: unknown): FilterFactory {
    return (vm as { filterFactory: FilterFactory }).filterFactory;
}

/**
 * The queue of returns.
 *
 * Three facts shape the page:
 *
 *  1. **The queue is sorted by deadline, not by arrival.** A return that ran out of time is a customer
 *     who is about to ask again, so the row says how much time is left instead of when it came in.
 *  2. **The state decides which actions exist.** Nothing on this page offers a decision the server
 *     would refuse - the row menu is derived from the state and from the role.
 *  3. **The money is read, not entered.** The refund amount is computed by the server from the line
 *     items it accepted; the operator never types an amount here.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        orderReturns: EntityCollection<'order_return'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        states: EntityCollection<'state_machine_state'> | null;
        defaultFilters: string[];
        storeKey: string;
        activeFilterNumber: number;
    } {
        return {
            orderReturns: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            states: null,
            defaultFilters: DEFAULT_FILTERS,
            storeKey: 'grid.filter.order_return',
            activeFilterNumber: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        orderReturnRepository(): RepositoryType<'order_return'> {
            return this.repositoryFactory.create('order_return');
        },

        stateRepository(): RepositoryType<'state_machine_state'> {
            return this.repositoryFactory.create('state_machine_state');
        },

        dateFilter(): (value: string | Date) => string {
            return Shopwell.Filter.getByName('date') as (value: string | Date) => string;
        },

        /**
         * The order number is part of the listing, the customer too: an operator works a queue of
         * people, and a row without either of them can not be decided.
         */
        criteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term ?? '');
            criteria.addSorting(Criteria.sort(this.sortBy ?? 'createdAt', this.sortDirection, this.naturalSorting));
            criteria.addAssociation('order');
            criteria.addAssociation('customer');
            criteria.addAssociation('reason');
            criteria.addAssociation('stateMachineState');

            return criteria;
        },

        listFilterOptions(): Record<string, FilterDefinition> {
            return {
                'return-no-filter': {
                    property: 'returnNumber',
                    type: 'string-filter',
                    label: this.$t('sw-order-return.list.filters.returnNoFilter.label'),
                    placeholder: this.$t('sw-order-return.list.filters.returnNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'order-no-filter': {
                    property: 'order.orderNumber',
                    type: 'string-filter',
                    label: this.$t('sw-order-return.list.filters.orderNoFilter.label'),
                    placeholder: this.$t('sw-order-return.list.filters.orderNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'state-filter': {
                    property: 'stateId',
                    type: 'multi-select-filter',
                    label: this.$t('sw-order-return.list.filters.stateFilter.label'),
                    placeholder: this.$t('sw-order-return.list.filters.stateFilter.placeholder'),
                    options: this.stateFilterOptions,
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
            };
        },

        listFilters(): FilterDefinition[] {
            return orderReturnFilterFactory(this).create('order_return', this.listFilterOptions);
        },

        /**
         * The state of a return is a plain id, the states of the machine are read once so the filter
         * and the column can render their names.
         */
        stateFilterOptions(): Array<{ id: string; name: string }> {
            return [...(this.states ?? [])].map((state: StateMachineState) => {
                return {
                    id: state.id,
                    name: this.stateLabelOf(
                        state.technicalName,
                        state.translated?.name || state.name || state.technicalName,
                    ),
                };
            });
        },

        hasActiveSearchOrFilter(): boolean {
            return this.activeFilterNumber > 0 || Boolean(this.isValidTerm(this.term ?? ''));
        },

        /**
         * The columns of the queue in the order an operator reads them: which case, whose case, what
         * kind, how far, how much, how much time.
         */
        orderReturnColumns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'returnNumber',
                    label: 'sw-order-return.list.columnReturnNumber',
                    routerLink: 'sw.order.return.detail',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'order.orderNumber',
                    label: 'sw-order-return.list.columnOrder',
                    allowResize: true,
                },
                {
                    property: 'customerId',
                    label: 'sw-order-return.list.columnCustomer',
                    allowResize: true,
                },
                {
                    property: 'type',
                    label: 'sw-order-return.list.columnType',
                    allowResize: true,
                },
                {
                    property: 'stateId',
                    label: 'sw-order-return.list.columnState',
                    allowResize: true,
                },
                {
                    property: 'refundAmount',
                    label: 'sw-order-return.list.columnRefundAmount',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'deadlineAt',
                    label: 'sw-order-return.list.columnDeadline',
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: 'sw-order-return.list.columnCreatedAt',
                    allowResize: true,
                },
            ];
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            this.states = await this.stateRepository.search(this.criteriaForStates());

            return this.getList();
        },

        /**
         * The states of `order_return.state` - all of them, because the filter offers the whole
         * machine and not only the ones a row may currently be in.
         */
        criteriaForStates(): CriteriaType {
            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('stateMachine.technicalName', ORDER_RETURN_STATE_MACHINE));
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            const filterService = Shopwell.Service('filterService') as unknown as {
                mergeWithStoredFilters: (storeKey: string, criteria: CriteriaType) => Promise<CriteriaType>;
            };

            // the service is async - without the await the criteria is a pending promise and reading
            // its filters throws, which leaves the grid permanently empty
            const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.criteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.orderReturnRepository
                .search(criteria)
                .then((searchResult) => {
                    this.total = searchResult.total ?? 0;
                    this.orderReturns = searchResult;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updateCriteria(filters: unknown[]): void {
            this.page = 1;
            this.criteria.filters = filters as CriteriaType['filters'];

            void this.getList();
        },

        onRefresh(): void {
            void this.getList();
        },

        /**
         * The name of a return state.
         *
         * The snippet of this module wins over the name the state machine carries: that translation was
         * written for the customer and calls a `requested` return `等待商家处理`, while the queue asks the
         * operator to do something. A state the administration does not translate falls back to the name
         * of the machine instead of showing a key.
         */
        stateLabelOf(technicalName: string | null | undefined, fallback: string): string {
            if (!technicalName) {
                return fallback;
            }

            const key = returnStateLabelKey(technicalName);

            return this.$te(key) ? this.$t(key) : fallback;
        },

        stateLabel(stateId: string | null): string {
            const state = this.stateById(stateId);

            return this.stateLabelOf(state?.technicalName, state?.translated?.name || state?.name || '–');
        },

        stateVariant(stateId: string | null): string {
            return returnStateVariant(this.stateById(stateId)?.technicalName);
        },

        stateById(stateId: string | null): StateMachineState | undefined {
            if (!stateId) {
                return undefined;
            }

            return [...(this.states ?? [])].find((entry) => entry.id === stateId) as StateMachineState | undefined;
        },

        typeLabel(type: string | null | undefined): string {
            if (!type) {
                return '–';
            }

            const key = returnTypeLabelKey(type);

            return this.$te(key) ? this.$t(key) : type;
        },

        /**
         * The holder of the case. A guest return has no customer row, only the address it was opened
         * with - the email is the only identity the queue can show for it.
         */
        customerLabel(item: OrderReturn): string {
            const customer = item.customer as unknown as CustomerName | null | undefined;

            if (customer) {
                const name = [customer.firstName, customer.lastName].filter(Boolean).join(' ').trim();

                if (name) {
                    return name;
                }

                return customer.email || '–';
            }

            return item.guestEmail || '–';
        },

        formatAmount(amount: number | null, currency: string | null): string {
            return formatReturnAmount(amount, currency);
        },

        formatDateOrDash(value: string | null): string {
            return value ? this.dateFilter(value) : '–';
        },

        /**
         * A deadline that has passed is the one number on this page an operator must not miss, so it is
         * rendered as overdue rather than as a date.
         */
        deadlineLabel(item: OrderReturn): string {
            if (!item.deadlineAt) {
                return '–';
            }

            if (new Date(item.deadlineAt).getTime() < Date.now()) {
                return this.$t('sw-order-return.list.textOverdue');
            }

            return this.dateFilter(item.deadlineAt);
        },

        isOverdue(item: OrderReturn): boolean {
            return Boolean(item.deadlineAt) && new Date(item.deadlineAt as string).getTime() < Date.now();
        },
    },
});
