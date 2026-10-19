/**
 * @sw-package framework
 */
import template from './sw-payment-order-list.html.twig';
import './sw-payment-order-list.scss';
import {
    PAYMENT_ORDER_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
} from '../../service/payment-gateway.api.service';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

const DEFAULT_FILTERS = Object.freeze([
    'payment-no-filter',
    'out-trade-no-filter',
    'payment-app-filter',
    'provider-filter',
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
            orders: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            states: null,
            defaultFilters: DEFAULT_FILTERS,
            storeKey: 'grid.filter.payment_order',
            activeFilterNumber: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('payment_order');
        },

        stateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        criteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('paymentApp');

            return criteria;
        },

        listFilterOptions() {
            return {
                'payment-no-filter': {
                    property: 'paymentNo',
                    type: 'string-filter',
                    label: this.$t('sw-payment.order.filters.paymentNoFilter.label'),
                    placeholder: this.$t('sw-payment.order.filters.paymentNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'out-trade-no-filter': {
                    property: 'outTradeNo',
                    type: 'string-filter',
                    label: this.$t('sw-payment.order.filters.outTradeNoFilter.label'),
                    placeholder: this.$t('sw-payment.order.filters.outTradeNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
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
                    criteriaFilterType: 'equalsAny',
                },
                'provider-filter': {
                    property: 'providerTechnicalName',
                    type: 'string-filter',
                    label: this.$t('sw-payment.order.filters.providerFilter.label'),
                    placeholder: this.$t('sw-payment.order.filters.providerFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
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

        listFilters() {
            return this.filterFactory.create('payment_order', this.listFilterOptions);
        },

        paymentAppCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('name'));

            return criteria;
        },

        /**
         * The state of a payment order is a plain id, the states of the machine are read once so the
         * column and the filter can render their names.
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

        orderColumns() {
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
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.states = await this.stateRepository.search(createPaymentStateCriteria(PAYMENT_ORDER_STATE_MACHINE));

            await this.getList();
        },

        async getList() {
            this.isLoading = true;

            const criteria = await Shopwell.Service('filterService').mergeWithStoredFilters(this.storeKey, this.criteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.orderRepository
                .search(criteria)
                .then((searchResult) => {
                    this.total = searchResult.total;
                    this.orders = searchResult;
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

        paymentStateLabel(stateId) {
            return findPaymentStateLabel(this.states ?? [], stateId);
        },

        paymentStateVariant(stateId) {
            return findPaymentStateVariant(this.states ?? [], stateId);
        },

        formatAmount(amount, currency) {
            return formatPaymentAmount(amount, currency);
        },

        paymentAppLabel(item) {
            if (!item.paymentApp) {
                return '-';
            }

            return item.paymentApp.name || item.paymentApp.technicalName;
        },
    },
};
