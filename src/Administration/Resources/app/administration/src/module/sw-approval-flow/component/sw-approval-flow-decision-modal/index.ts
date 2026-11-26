/** @sw-package framework */
import type { PropType } from 'vue';
import type { HttpError } from 'src/core/factory/http-client.types';
import type {
    ApprovalDecision,
    ApprovalReturn,
    ApprovalReturnTarget,
    ApprovalTaskAction,
} from '../../service/approval-flow.api.service';
import template from './sw-approval-flow-decision-modal.html.twig';

type TaskActionInput = Pick<ApprovalDecision, 'outcome' | 'reason'> | Pick<ApprovalReturn, 'targetExecutionId' | 'reason'>;
type SubmissionFailure = 'conflict' | 'forbidden' | 'unavailable' | 'retryError';

const submissionFailures: Partial<Record<number, SubmissionFailure>> = {
    400: 'unavailable',
    401: 'forbidden',
    403: 'forbidden',
    404: 'unavailable',
    409: 'conflict',
    422: 'unavailable',
};

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
        action: { type: String as PropType<ApprovalTaskAction>, required: true },
    },
    data(): {
        reason: string;
        command: ApprovalDecision | ApprovalReturn | null;
        targetExecutionId: string | null;
        targets: ApprovalReturnTarget[];
        isLoadingTargets: boolean;
        isSubmitting: boolean;
        isActive: boolean;
        failure: SubmissionFailure | null;
    } {
        return {
            reason: '',
            command: null,
            targetExecutionId: null,
            targets: [],
            isLoadingTargets: false,
            isSubmitting: false,
            isActive: true,
            failure: null,
        };
    },
    computed: {
        isReturn(): boolean {
            return this.action === 'return';
        },
        returnOptions() {
            return this.targets.map((target) => ({
                value: target.executionId,
                label: this.$t('sw-approval-flow.detail.step', { number: target.sequence }),
            }));
        },
        actionInput(): TaskActionInput | null {
            if (this.action !== 'return') {
                return { outcome: this.action, reason: this.reason };
            }

            const reason = this.reason.trim();
            return this.targetExecutionId && reason ? { targetExecutionId: this.targetExecutionId, reason } : null;
        },
        canSubmit(): boolean {
            return !this.isSubmitting && this.actionInput !== null;
        },
        canRetry(): boolean {
            return this.failure === 'retryError';
        },
    },
    mounted() {
        if (this.isReturn) {
            void this.loadReturnTargets();
        }
    },
    beforeUnmount() {
        this.isActive = false;
    },
    methods: {
        async loadReturnTargets(): Promise<void> {
            this.isLoadingTargets = true;
            try {
                const targets = await this.approvalFlowApiService.returnTargets(this.instanceId, this.taskId);
                if (this.isActive) {
                    this.targets = targets;
                }
            } catch {
                if (this.isActive) {
                    this.failure = 'unavailable';
                }
            } finally {
                if (this.isActive) {
                    this.isLoadingTargets = false;
                }
            }
        },
        async submit(): Promise<void> {
            const input = this.actionInput;
            if (this.isSubmitting || input === null) {
                return;
            }
            // A lost response must replay the original command, including its version and inputs.
            this.command ??= {
                commandId: Shopwell.Utils.createId(),
                expectedVersion: this.expectedVersion,
                ...input,
            };
            this.isSubmitting = true;
            this.failure = null;
            try {
                if ('targetExecutionId' in this.command) {
                    await this.approvalFlowApiService.returnTask(this.instanceId, this.taskId, this.command);
                } else {
                    await this.approvalFlowApiService.decide(this.instanceId, this.taskId, this.command);
                }
                if (this.isActive) {
                    this.$emit('completed');
                }
            } catch (error) {
                if (!this.isActive) {
                    return;
                }
                const status = (error as HttpError | null)?.response?.status;
                this.failure = submissionFailures[status ?? 0] ?? 'retryError';
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
