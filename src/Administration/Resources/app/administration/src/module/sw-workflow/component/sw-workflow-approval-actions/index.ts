/** @sw-package framework */
import type { PropType } from 'vue';
import { WORKFLOW_TASK_DECISIONS } from 'src/core/workflow/workflow-runtime.constants';
import template from './sw-workflow-approval-actions.html.twig';
import './sw-workflow-approval-actions.scss';

/** Require a rejection reason before submission to match the execution API. */
/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-workflow-approval-actions',

    template,

    inject: ['acl', 'workflowApprovalService'],

    emits: [
        'approve',
        'reject',
        'participate',
        'return',
        'completed',
    ],

    props: {
        approveDisabled: { type: Boolean, default: false },
        taskId: { type: String, default: '' },
        availableActions: { type: Array as PropType<string[]>, default: () => [] },
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
            participationAction: '',
            targetUserId: null as string | null,
        };
    },

    computed: {
        extensionOperations() {
            return this.workflowApprovalService
                .operations()
                .filter((operation) => operation.actionComponent && this.availableActions.includes(operation.name));
        },
        participationActions(): string[] {
            if (!this.acl.can('user:read')) {
                return [];
            }

            return [WORKFLOW_TASK_DECISIONS.TRANSFER, WORKFLOW_TASK_DECISIONS.ADD_SIGN].filter((action) =>
                this.availableActions.includes(action),
            );
        },

        participantCriteria() {
            const { Criteria } = Shopwell.Data;
            return new Criteria(1, 25).addFilter(Criteria.equals('active', true));
        },

        canParticipate(): boolean {
            return !this.isLoading && Boolean(this.targetUserId) && this.comment.trim() !== '';
        },

        canReject() {
            return !this.isLoading && this.comment.trim() !== '';
        },

        canApprove() {
            return !this.isLoading && !this.approveDisabled;
        },
    },

    methods: {
        openParticipation(action: string): void {
            if (this.isLoading || !this.participationActions.includes(action)) {
                return;
            }

            this.targetUserId = null;
            this.participationAction = action;
        },

        confirmParticipation(): void {
            if (!this.canParticipate || !this.participationActions.includes(this.participationAction)) {
                return;
            }

            this.$emit('participate', {
                action: this.participationAction,
                targetUserId: this.targetUserId,
                comment: this.comment.trim(),
            });
            this.participationAction = '';
        },

        onReturn(): void {
            if (this.canReject && this.availableActions.includes(WORKFLOW_TASK_DECISIONS.RETURN)) {
                this.$emit('return', this.comment.trim());
            }
        },

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
