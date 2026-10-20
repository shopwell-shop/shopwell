/**
 * @sw-package framework
 */
import template from './sw-payment-config-field.html.twig';
import './sw-payment-config-field.scss';

/**
 * Renders one input of a payment interface or channel configuration.
 *
 * The administration does not know the fields of alipay, wechat or of any interface an extension
 * brings: it renders what the interface declares in its `payment-provider.xml` and turns into a
 * `PaymentConfigField`. That is why the component only knows field *types* - adding a field to an
 * interface never touches the administration.
 *
 * A field marked as secret is masked by the browser, but its value is handed over like every other one
 * and is therefore part of the form. Typing nothing keeps what is stored, which is the same rule the
 * server applies to every field of the interface.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inheritAttrs: false,

    props: {
        modelValue: {
            type: [String, Number, Boolean],
            required: false,
            default: '',
        },

        field: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['update:modelValue'],

    computed: {
        currentValue: {
            get() {
                return this.modelValue ?? '';
            },

            set(value) {
                this.$emit('update:modelValue', value);
            },
        },

        /**
         * A boolean field is stored as a string like every other value, the switch only speaks in
         * booleans.
         */
        currentBoolean: {
            get() {
                return this.currentValue === '1' || this.currentValue === 'true' || this.currentValue === true;
            },

            set(value) {
                this.currentValue = value ? '1' : '';
            },
        },

        currentNumber: {
            get() {
                return this.currentValue === '' || this.currentValue === null ? null : Number(this.currentValue);
            },

            set(value) {
                this.currentValue = value === null || value === undefined ? '' : String(value);
            },
        },

        /**
         * An interface declares its own label, either a snippet key or a plain text.
         */
        label() {
            if (this.field.label) {
                return this.$te(this.field.label) ? this.$t(this.field.label) : this.field.label;
            }

            return this.field.name;
        },

        options() {
            return Object.entries(this.field.options || {}).map(([value, label]) => {
                return { value, label };
            });
        },

        /**
         * A field may declare a default in its schema which is applied server-side when the field
         * is left empty - showing it as a placeholder makes that visible.
         */
        placeholder() {
            return this.field.default ?? undefined;
        },

        /**
         * A field may declare its own hint in the schema next to it. Nothing is added here: the value of
         * a secret field is part of the form, so "configured" would describe what the input already
         * shows.
         */
        helpText() {
            if (!this.field.helpText) {
                return null;
            }

            return this.$te(this.field.helpText) ? this.$t(this.field.helpText) : this.field.helpText;
        },
    },
};
