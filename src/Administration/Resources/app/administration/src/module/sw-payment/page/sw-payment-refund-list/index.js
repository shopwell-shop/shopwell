/**
 * @sw-package framework
 */
import template from './sw-payment-refund-list.html.twig';
import './sw-payment-refund-list.scss';
import {
    PAYMENT_REFUND_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
} from '../../service/payment-gateway.api.service';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

const DEFAULT_FILTERS = Object.freeze([
    'refund-no-filter',
    'out-refund-no-filter',
    'channel-filter',
    'state-filter',
]);

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    data() {
        return {
            refunds: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            states: null,
            defaultFilters: DEFAULT_FILTERS,
            storeKey: 'grid.filter.payment_refund',
            activeFilterNumber: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        refundRepository() {
            return this.repositoryFactory.create('payment_refund');
        },

        stateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        criteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('paymentOrder');

            return criteria;
        },

        listFilterOptions() {
            return {
                'refund-no-filter': {
                    property: 'refundNo',
                    type: 'string-filter',
                    label: this.$t('sw-payment.refund.filters.refundNoFilter.label'),
                    placeholder: this.$t('sw-payment.refund.filters.refundNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'out-refund-no-filter': {
                    property: 'outRefundNo',
                    type: 'string-filter',
                    label: this.$t('sw-payment.refund.filters.outRefundNoFilter.label'),
                    placeholder: this.$t('sw-payment.refund.filters.outRefundNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'channel-filter': {
                    property: 'paymentOrder.channelTechnicalName',
                    type: 'string-filter',
                    label: this.$t('sw-payment.refund.filters.channelFilter.label'),
                    placeholder: this.$t('sw-payment.refund.filters.channelFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
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

        listFilters() {
            return this.filterFactory.create('payment_refund', this.listFilterOptions);
        },

        /**
         * The state of a refund is a plain id, the states of the machine are read once so the column
         * and the filter can render their names.
         */
        stateFilterOptions() {
            return [...(this.states ?? [])].map((state) => {
                return {
                    id: state.id,
                    name: state.translated?.name || state.name || state.technicalName,
                };
            });
        },

        hasActiveSearchOrFilter() {
            return this.activeFilterNumber > 0 || this.isValidTerm(this.term);
        },

        refundColumns() {
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
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.states = await this.stateRepository.search(createPaymentStateCriteria(PAYMENT_REFUND_STATE_MACHINE));

            await this.getList();
        },

        async getList() {
            this.isLoading = true;

            const criteria = await Shopwell.Service('filterService').mergeWithStoredFilters(this.storeKey, this.criteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.refundRepository
                .search(criteria)
                .then((searchResult) => {
                    this.total = searchResult.total;
                    this.refunds = searchResult;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updateCriteria(criteria) {
            this.page = 1;
            this.criteria.filters = criteria;

            return this.getList();
        },

        onRefresh() {
            return this.getList();
        },

        refundStateLabel(stateId) {
            return findPaymentStateLabel(this.states ?? [], stateId);
        },

        refundStateVariant(stateId) {
            return findPaymentStateVariant(this.states ?? [], stateId);
        },

        formatAmount(amount, currency) {
            return formatPaymentAmount(amount, currency);
        },

        paymentOrderLabel(item) {
            if (!item.paymentOrder) {
                return '-';
            }

            return item.paymentOrder.paymentNo || item.paymentOrder.outTradeNo;
        },
    },
};
