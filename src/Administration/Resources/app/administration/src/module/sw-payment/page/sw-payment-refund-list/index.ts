/**
 * @sw-package framework
 *
 * Every refund the gateway took back, newest first.
 *
 * A refund is read through the payment it belongs to - which of the two numbers a customer quotes is
 * never certain -, so the association is part of every request and the column shows the number of the
 * payment instead of its id.
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-payment-refund-list.html.twig';
import './sw-payment-refund-list.scss';
import {
    PAYMENT_REFUND_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
} from '../../service/payment-gateway.api.service';
import type { PaymentRefund } from '../../payment.types';

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
    'refund-no-filter',
    'out-refund-no-filter',
    'channel-filter',
    'state-filter',
];

/**
 * The filter factory is injected but not part of the typed service container, so it is read through
 * the component instance instead of widening the global type for one page.
 */
function refundFilterFactory(vm: unknown): FilterFactory {
    return (vm as { filterFactory: FilterFactory }).filterFactory;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- page component, rendered through the module route
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        refunds: EntityCollection<'payment_refund'> | null;
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
            refunds: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            states: null,
            defaultFilters: DEFAULT_FILTERS,
            storeKey: 'grid.filter.payment_refund',
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
        refundRepository(): RepositoryType<'payment_refund'> {
            return this.repositoryFactory.create('payment_refund');
        },

        stateRepository(): RepositoryType<'state_machine_state'> {
            return this.repositoryFactory.create('state_machine_state');
        },

        criteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term ?? '');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('paymentOrder');

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
                'refund-no-filter': {
                    property: 'refundNo',
                    type: 'string-filter',
                    label: this.$t('sw-payment.refund.filters.refundNoFilter.label'),
                    placeholder: this.$t('sw-payment.refund.filters.refundNoFilter.placeholder'),
                },
                'out-refund-no-filter': {
                    property: 'outRefundNo',
                    type: 'string-filter',
                    label: this.$t('sw-payment.refund.filters.outRefundNoFilter.label'),
                    placeholder: this.$t('sw-payment.refund.filters.outRefundNoFilter.placeholder'),
                },
                'channel-filter': {
                    property: 'paymentOrder.channelTechnicalName',
                    type: 'string-filter',
                    label: this.$t('sw-payment.refund.filters.channelFilter.label'),
                    placeholder: this.$t('sw-payment.refund.filters.channelFilter.placeholder'),
                },
                'state-filter': {
                    property: 'stateId',
                    type: 'multi-select-filter',
                    label: this.$t('sw-payment.refund.filters.stateFilter.label'),
                    placeholder: this.$t('sw-payment.refund.filters.stateFilter.placeholder'),
                    options: this.stateFilterOptions,
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
            };
        },

        listFilters(): FilterDefinition[] {
            return refundFilterFactory(this).create('payment_refund', this.listFilterOptions);
        },

        /**
         * The state of a refund is a plain id, the states of the machine are read once so the column
         * and the filter can render their names.
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

        refundColumns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'refundNo',
                    label: 'sw-payment.refund.list.columnRefundNo',
                    routerLink: 'sw.payment.refundDetail',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'paymentOrderId',
                    label: 'sw-payment.refund.list.columnPaymentOrder',
                    allowResize: true,
                },
                {
                    property: 'outRefundNo',
                    label: 'sw-payment.refund.list.columnOutRefundNo',
                    allowResize: true,
                },
                {
                    property: 'stateId',
                    label: 'sw-payment.refund.list.columnState',
                    allowResize: true,
                },
                {
                    property: 'amount',
                    label: 'sw-payment.refund.list.columnAmount',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'currency',
                    label: 'sw-payment.refund.list.columnCurrency',
                    allowResize: true,
                },
                {
                    property: 'refundedAt',
                    label: 'sw-payment.refund.list.columnRefundedAt',
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: 'sw-payment.refund.list.columnCreatedAt',
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
            this.states = await this.stateRepository.search(createPaymentStateCriteria(PAYMENT_REFUND_STATE_MACHINE));

            void this.getList();
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            // the filter service is not part of the typed service container
            const filterService = Shopwell.Service('filterService') as FilterService;

            const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.criteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.refundRepository
                .search(criteria)
                .then((searchResult) => {
                    this.total = searchResult.total ?? 0;
                    this.refunds = searchResult;
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

        refundStateLabel(stateId: string): string {
            return findPaymentStateLabel(this.states ?? [], stateId);
        },

        refundStateVariant(stateId: string): string {
            return findPaymentStateVariant(this.states ?? [], stateId);
        },

        formatAmount(amount: number, currency: string): string {
            return formatPaymentAmount(amount, currency);
        },

        paymentOrderLabel(item: PaymentRefund): string {
            if (!item.paymentOrder) {
                return '–';
            }

            return item.paymentOrder.paymentNo || item.paymentOrder.outTradeNo || '–';
        },
    },
});
