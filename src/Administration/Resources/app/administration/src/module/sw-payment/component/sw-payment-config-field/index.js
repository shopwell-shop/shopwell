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
 * A field marked as secret is never handed over with its value, so an empty input does not mean "no
 * value": it means "keep what is stored". The help text says so, which is what makes it safe to save
 * a form without retyping a key.
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
         * The value of a secret is never sent to the browser, so an empty input is not an error - it
         * keeps the stored value. Only a field that already has one says so. A field may declare its
         * own hint in the schema next to it.
         */
        helpText() {
            const hints = [];

            if (this.field.helpText) {
                hints.push(this.$te(this.field.helpText) ? this.$t(this.field.helpText) : this.field.helpText);
            }

            if (this.field.secret && this.field.configured) {
                hints.push(this.$t('sw-payment.configField.configuredHint'));
            }

            return hints.length > 0 ? hints.join(' ') : null;
        },
    },
};
