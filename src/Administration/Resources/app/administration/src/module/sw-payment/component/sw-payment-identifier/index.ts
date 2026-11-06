/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-payment-identifier.html.twig';
import './sw-payment-identifier.scss';

/**
 * A technical identifier of a payment: the number an operator copies into a ticket, a mail to the
 * channel or the merchant dashboard of the provider.
 *
 * The value stays selectable text and is never shortened - copying must not be the only way to read
 * it, and a truncated number is a number that gets retyped wrong.
 *
 * @private
 */
export default defineComponent({
    template,

    props: {
        value: {
            type: String as PropType<string | null>,
            required: false,
            default: null,
        },
    },

    data(): { copied: boolean } {
        return {
            copied: false,
        };
    },

    computed: {
        hasValue(): boolean {
            return Boolean(this.value);
        },

        displayValue(): string {
            return this.value ?? '–';
        },
    },

    methods: {
        /**
         * Copying is a convenience, never the only path to the value, so a browser that refuses the
         * clipboard (http origin, denied permission) simply leaves the text as it is.
         */
        async onCopy(): Promise<void> {
            if (!this.value) {
                return;
            }

            try {
                await navigator.clipboard.writeText(this.value);
                this.copied = true;

                window.setTimeout(() => {
                    this.copied = false;
                }, 1500);
            } catch {
                this.copied = false;
            }
        },
    },
});
