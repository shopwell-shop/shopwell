/**
 * @sw-package fundamentals@discovery
 */
import template from './sw-country-state-detail.html.twig';

const { Mixin } = Shopwell;

/**
 * Edits a single region of a country. The level decides which fields are used: a state keeps its
 * ISO short code, cities and districts keep the administrative division code.
 *
 * @sw-package fundamentals@discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    emits: ['attribute-edit-cancel', 'attribute-edit-save'],

    mixins: [Mixin.getByName('placeholder')],

    props: {
        countryState: {
            type: Object,
            required: true,
        },
        level: {
            type: String,
            required: false,
            default: 'state',
            validator: (value) => ['state', 'city', 'district'].includes(value),
        },
    },

    computed: {
        isNew() {
            return typeof this.countryState?.isNew === 'function' && this.countryState.isNew();
        },

        isStateLevel() {
            return this.level === 'state';
        },

        /**
         * States are identified by their ISO 3166-2 short code, cities and districts by their
         * administrative division code.
         */
        codeProperty() {
            return this.isStateLevel ? 'shortCode' : 'code';
        },

        newTitles() {
            return {
                state: this.$t('sw-country-state-detail.titleNewState'),
                city: this.$t('sw-country-state-detail.titleNewCity'),
                district: this.$t('sw-country-state-detail.titleNewDistrict'),
            };
        },

        editTitles() {
            return {
                state: this.$t('sw-country-state-detail.titleEditState'),
                city: this.$t('sw-country-state-detail.titleEditCity'),
                district: this.$t('sw-country-state-detail.titleEditDistrict'),
            };
        },

        modalTitle() {
            if (this.isNew) {
                return this.newTitles[this.level];
            }

            return this.editTitles[this.level];
        },

        codeLabel() {
            return this.isStateLevel
                ? this.$t('sw-country-state-detail.labelShortCode')
                : this.$t('sw-country-state-detail.labelCode');
        },

        tooltipSave() {
            if (!this.acl.can('country.editor')) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('country.editor'),
                    showOnDisabledElements: true,
                };
            }

            return {
                message: '',
                disabled: true,
            };
        },
    },

    methods: {
        onCancel() {
            this.$emit('attribute-edit-cancel', this.countryState);
        },

        onSave() {
            this.$emit('attribute-edit-save', this.countryState);
        },
    },
};
