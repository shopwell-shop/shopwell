/**
 * @sw-package framework
 *
 * Every payment order the gateway booked, newest first.
 *
 * The list answers "which payment is the customer talking about": the number the shop gave it, the
 * number the merchant's own system gave it, and the state of the money. It is read-only - money is
 * moved by the app that called the gateway, not by an operator.
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-payment-order-list.html.twig';
import './sw-payment-order-list.scss';
import {
    PAYMENT_ORDER_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
} from '../../service/payment-gateway.api.service';
import type { PaymentOrder } from '../../payment.types';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

type FilterOption = { id: string; name: string };

type FilterDefinition = {
    property: string;
    type?: string;
    label: string;
    placeholder: string;
    criteria?: CriteriaType;
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
    'payment-no-filter',
    'out-trade-no-filter',
    'payment-app-filter',
    'provider-filter',
    'channel-filter',
    'state-filter',
];

/**
 * The filter factory is injected but not part of the typed service container, so it is read through
 * the component instance instead of widening the global type for one page.
 */
function paymentFilterFactory(vm: unknown): FilterFactory {
    return (vm as { filterFactory: FilterFactory }).filterFactory;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- page component, rendered through the module route
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        orders: EntityCollection<'payment_order'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        states: EntityCollection<'state_machine_state'> | null;
        defaultFilters: string[];
        storeKey: string;
        activeFilterNumber: number;
        activeFilters: CriteriaType['filters'];
    } {
        return {
            orders: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            states: null,
            defaultFilters: DEFAULT_FILTERS,
            storeKey: 'grid.filter.payment_order',
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
        orderRepository(): RepositoryType<'payment_order'> {
            return this.repositoryFactory.create('payment_order');
        },

        stateRepository(): RepositoryType<'state_machine_state'> {
            return this.repositoryFactory.create('state_machine_state');
        },

        criteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term ?? '');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('paymentApp');

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
                'payment-no-filter': {
                    property: 'paymentNo',
                    type: 'string-filter',
                    label: this.$t('sw-payment.order.filters.paymentNoFilter.label'),
                    placeholder: this.$t('sw-payment.order.filters.paymentNoFilter.placeholder'),
                },
                'out-trade-no-filter': {
                    property: 'outTradeNo',
                    type: 'string-filter',
                    label: this.$t('sw-payment.order.filters.outTradeNoFilter.label'),
                    placeholder: this.$t('sw-payment.order.filters.outTradeNoFilter.placeholder'),
                },
                'payment-app-filter': {
                    property: 'paymentAppId',
                    label: this.$t('sw-payment.order.filters.paymentAppFilter.label'),
                    placeholder: this.$t('sw-payment.order.filters.paymentAppFilter.placeholder'),
                    criteria: this.paymentAppCriteria,
                    labelProperty: 'name',
                },
                'channel-filter': {
                    property: 'channelTechnicalName',
                    type: 'string-filter',
                    label: this.$t('sw-payment.order.filters.channelFilter.label'),
                    placeholder: this.$t('sw-payment.order.filters.channelFilter.placeholder'),
                },
                'provider-filter': {
                    property: 'providerTechnicalName',
                    type: 'string-filter',
                    label: this.$t('sw-payment.order.filters.providerFilter.label'),
                    placeholder: this.$t('sw-payment.order.filters.providerFilter.placeholder'),
                },
                'state-filter': {
                    property: 'stateId',
                    type: 'multi-select-filter',
                    label: this.$t('sw-payment.order.filters.stateFilter.label'),
                    placeholder: this.$t('sw-payment.order.filters.stateFilter.placeholder'),
                    options: this.stateFilterOptions,
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
            };
        },

        listFilters(): FilterDefinition[] {
            return paymentFilterFactory(this).create('payment_order', this.listFilterOptions);
        },

        /**
         * The apps the shop has registered, so the filter can offer them by name instead of by id.
         */
        paymentAppCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('name'));

            return criteria;
        },

        /**
         * The state of a payment order is a plain id, the states of the machine are read once so the
         * column and the filter can render their names.
         */
        stateFilterOptions(): FilterOption[] {
            return Array.from(this.states ?? []).map((state) => {
                return {
                    id: state.id,
                    name: state.translated?.name || state.name || state.technicalName || state.id,
                };
            });
        },

        hasActiveSearchOrFilter(): boolean {
            return this.activeFilterNumber > 0 || Boolean(this.isValidTerm(this.term ?? ''));
        },

        orderColumns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'paymentNo',
                    label: 'sw-payment.order.list.columnPaymentNo',
                    routerLink: 'sw.payment.orderDetail',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'outTradeNo',
                    label: 'sw-payment.order.list.columnOutTradeNo',
                    allowResize: true,
                },
                {
                    property: 'paymentAppId',
                    label: 'sw-payment.order.list.columnPaymentApp',
                    allowResize: true,
                },
                {
                    property: 'providerTechnicalName',
                    label: 'sw-payment.order.list.columnProvider',
                    allowResize: true,
                },
                {
                    property: 'channelTechnicalName',
                    label: 'sw-payment.order.list.columnChannel',
                    allowResize: true,
                },
                {
                    property: 'stateId',
                    label: 'sw-payment.order.list.columnState',
                    allowResize: true,
                },
                {
                    property: 'amount',
                    label: 'sw-payment.order.list.columnAmount',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'currency',
                    label: 'sw-payment.order.list.columnCurrency',
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: 'sw-payment.order.list.columnCreatedAt',
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
            this.states = await this.stateRepository.search(createPaymentStateCriteria(PAYMENT_ORDER_STATE_MACHINE));

            void this.getList();
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            // the filter service is not part of the typed service container
            const filterService = Shopwell.Service('filterService') as FilterService;

            const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.criteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.orderRepository
                .search(criteria)
                .then((searchResult) => {
                    this.total = searchResult.total ?? 0;
                    this.orders = searchResult;
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

        onRefresh(): void {
            void this.getList();
        },

        paymentStateLabel(stateId: string): string {
            return findPaymentStateLabel(this.states ?? [], stateId);
        },

        paymentStateVariant(stateId: string): string {
            return findPaymentStateVariant(this.states ?? [], stateId);
        },

        formatAmount(amount: number, currency: string): string {
            return formatPaymentAmount(amount, currency);
        },

        paymentAppLabel(item: PaymentOrder): string {
            if (!item.paymentApp) {
                return '–';
            }

            return item.paymentApp.name || item.paymentApp.technicalName || '–';
        },
    },
});
