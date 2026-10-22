import template from './sw-customer-base-form.html.twig';
import errorConfig from '../../error-config.json';

/**
 * @sw-package checkout
 */

const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();
const { Criteria } = Shopwell.Data;
const { CUSTOMER } = Shopwell.Constants;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature', 'dictionaryService'],

    emits: ['sales-channel-change'],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            genders: [],
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        ...mapPropertyErrors('customer', errorConfig['sw.customer.detail.base'].customer),

        genderOptions() {
            return this.genders.map((entry) => ({
                value: entry.technicalName,
                label: entry.translated.label,
            }));
        },

        accountTypeOptions() {
            return [
                {
                    value: CUSTOMER.ACCOUNT_TYPE_PRIVATE,
                    label: this.$t('sw-customer.customerType.labelPrivate'),
                },
                {
                    value: CUSTOMER.ACCOUNT_TYPE_BUSINESS,
                    label: this.$t('sw-customer.customerType.labelBusiness'),
                },
            ];
        },

        isBusinessAccountType() {
            return this.customer?.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS;
        },

        languageCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.customer?.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.customer.salesChannelId));
            }

            return criteria;
        },
    },

    watch: {
        'customer.guest'(newVal) {
            if (newVal) {
                this.customer.password = null;
            }
        },
    },

    methods: {
        createdComponent() {
            this.dictionaryService.getGenders(true, true).then((entries) => {
                this.genders = entries;
            });
        },

        onSalesChannelChange(salesChannelId) {
            this.$emit('sales-channel-change', salesChannelId);
        },
    },
};
