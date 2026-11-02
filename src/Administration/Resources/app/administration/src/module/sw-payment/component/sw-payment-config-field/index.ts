/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-payment-config-field.html.twig';
import './sw-payment-config-field.scss';

/**
 * One field of a payment interface or channel configuration, as the interface declares it.
 *
 * @private
 */
export interface PaymentConfigField {
    name: string;
    type: string;
    label?: string | null;
    helpText?: string | null;
    default?: string | null;
    required?: boolean;
    secret?: boolean;
    options?: Record<string, string>;
}

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
 *
 * @private
 */
export default defineComponent({
    template,

    inheritAttrs: false,

    props: {
        modelValue: {
            type: [String, Number, Boolean],
            required: false,
            default: '',
        },

        field: {
            type: Object as PropType<PaymentConfigField>,
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
            get(): string | number | boolean {
                return this.modelValue ?? '';
            },

            set(value: string | number | boolean): void {
                this.$emit('update:modelValue', value);
            },
        },

        /**
         * A boolean field is stored as a string like every other value, the switch only speaks in
         * booleans.
         */
        currentBoolean: {
            get(): boolean {
                return this.currentValue === '1' || this.currentValue === 'true' || this.currentValue === true;
            },

            set(value: boolean): void {
                this.currentValue = value ? '1' : '';
            },
        },

        currentNumber: {
            get(): number | null {
                return this.currentValue === '' || this.currentValue === null ? null : Number(this.currentValue);
            },

            set(value: number | null | undefined): void {
                this.currentValue = value === null || value === undefined ? '' : String(value);
            },
        },

        /**
         * An interface declares its own label, either a snippet key or a plain text.
         */
        label(): string {
            const declared = this.field.label;

            if (declared) {
                return this.$te(declared) ? this.$t(declared) : declared;
            }

            return this.field.name;
        },

        options(): Array<{ value: string; label: string }> {
            return Object.entries(this.field.options ?? {}).map(([value, label]) => {
                return { value, label };
            });
        },

        /**
         * A field may declare a default in its schema which is applied server-side when the field
         * is left empty - showing it as a placeholder makes that visible.
         */
        placeholder(): string | undefined {
            return this.field.default ?? undefined;
        },

        /**
         * A field may declare its own hint in the schema next to it. Nothing is added here: the value of
         * a secret field is part of the form, so "configured" would describe what the input already
         * shows.
         */
        helpText(): string | null {
            const declared = this.field.helpText;

            if (!declared) {
                return null;
            }

            return this.$te(declared) ? this.$t(declared) : declared;
        },
    },
});
