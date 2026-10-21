/**
 * @sw-package after-sales
 */
import { defineComponent } from 'vue';
import template from './sw-order-return-decline-modal.html.twig';
import './sw-order-return-decline-modal.scss';

const REASON_LIMIT = 500;

/**
 * The refusal of a return.
 *
 * A refusal is final - the return moves to `declined` and cannot be reopened - so the reason is
 * mandatory: it is the only explanation the customer receives, and the only record the shop keeps.
 *
 * The modal does not call the server itself. It hands the reason back to the page, which owns the
 * decision and already knows how to report a refusal of the server - one decision, one path.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    emits: ['modal-close', 'decline'],

    props: {
        returnNumber: {
            type: String,
            required: true,
        },
    },

    data(): { reason: string } {
        return { reason: '' };
    },

    computed: {
        reasonLength(): number {
            return this.reason.length;
        },

        reasonLimit(): number {
            return REASON_LIMIT;
        },

        isReasonTooLong(): boolean {
            return this.reasonLength > REASON_LIMIT;
        },

        /**
         * A refusal without a reason would leave a closed case nobody can explain later, so the button
         * stays disabled until there is one.
         */
        isConfirmDisabled(): boolean {
            return this.reason.trim() === '' || this.isReasonTooLong;
        },
    },

    methods: {
        onClose(): void {
            this.$emit('modal-close');
        },

        onConfirm(): void {
            if (this.isConfirmDisabled) {
                return;
            }

            this.$emit('decline', this.reason.trim());
        },
    },
});
