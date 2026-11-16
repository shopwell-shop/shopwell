/** @sw-package framework */
import template from './sw-workflow-approval-actions.html.twig';
import './sw-workflow-approval-actions.scss';

/** Require a rejection reason before submission to match the execution API. */
/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-workflow-approval-actions',

    template,

    emits: ['approve', 'reject'],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        progress: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            comment: '',
        };
    },

    computed: {
        canReject() {
            return !this.isLoading && this.comment.trim() !== '';
        },

        canApprove() {
            return !this.isLoading;
        },
    },

    methods: {
        onApprove() {
            if (!this.canApprove) {
                return;
            }

            this.$emit('approve', this.comment.trim());
        },

        onReject() {
            if (!this.canReject) {
                return;
            }

            this.$emit('reject', this.comment.trim());
        },
    },
});
