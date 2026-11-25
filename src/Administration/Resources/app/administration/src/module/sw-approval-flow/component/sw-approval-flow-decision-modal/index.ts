/** @sw-package framework */
import type { PropType } from 'vue';
import type { HttpError } from 'src/core/factory/http-client.types';
import type { ApprovalDecision, ApprovalOutcome } from '../../service/approval-flow.api.service';
import template from './sw-approval-flow-decision-modal.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-approval-flow-decision-modal',
    template,
    inject: ['approvalFlowApiService'],
    emits: ['close', 'completed', 'refresh'],
    props: {
        instanceId: { type: String, required: true },
        taskId: { type: String, required: true },
        expectedVersion: { type: Number, required: true },
        outcome: { type: String as PropType<ApprovalOutcome>, required: true },
    },
    data(): {
        reason: string;
        command: ApprovalDecision | null;
        isSubmitting: boolean;
        isActive: boolean;
        failure: 'conflict' | 'forbidden' | 'unavailable' | 'retryError' | null;
    } {
        return { reason: '', command: null, isSubmitting: false, isActive: true, failure: null };
    },
    computed: {
        canRetry(): boolean {
            return this.failure === 'retryError';
        },
    },
    beforeUnmount() {
        this.isActive = false;
    },
    methods: {
        async submit(): Promise<void> {
            if (this.isSubmitting) {
                return;
            }
            // A lost response must replay the original command, including its version and opinion.
            this.command ??= {
                commandId: Shopwell.Utils.createId(),
                expectedVersion: this.expectedVersion,
                outcome: this.outcome,
                reason: this.reason,
            };
            this.isSubmitting = true;
            this.failure = null;
            try {
                await this.approvalFlowApiService.decide(this.instanceId, this.taskId, this.command);
                if (this.isActive) {
                    this.$emit('completed');
                }
            } catch (error) {
                if (!this.isActive) {
                    return;
                }
                const status = (error as HttpError | null)?.response?.status;
                if (status === 409) {
                    this.failure = 'conflict';
                } else if (status === 403 || status === 401) {
                    this.failure = 'forbidden';
                } else if (status === 400 || status === 404) {
                    this.failure = 'unavailable';
                } else {
                    this.failure = 'retryError';
                }
            } finally {
                if (this.isActive) {
                    this.isSubmitting = false;
                }
            }
        },
        close(): void {
            if (!this.isSubmitting) {
                this.$emit(this.command ? 'refresh' : 'close');
            }
        },
    },
});
