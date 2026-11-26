/** @sw-package framework */
import type { PropType } from 'vue';
import type { HttpError } from 'src/core/factory/http-client.types';
import type {
    ApprovalDecision,
    ApprovalReturn,
    ApprovalTaskAction,
    ApprovalTransfer,
} from '../../service/approval-flow.api.service';
import template from './sw-approval-flow-decision-modal.html.twig';

type TaskCommand = ApprovalDecision | ApprovalReturn | ApprovalTransfer;
type TaskActionInput =
    | Pick<ApprovalDecision, 'outcome' | 'reason'>
    | Pick<ApprovalReturn, 'targetExecutionId' | 'reason'>
    | Pick<ApprovalTransfer, 'assigneeId' | 'reason'>;
type TargetOption = { value: string; label: string };
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
        command: TaskCommand | null;
        targetId: string | null;
        targetOptions: TargetOption[];
        searchRevision: number;
        isLoadingTargets: boolean;
        isSubmitting: boolean;
        isActive: boolean;
        failure: SubmissionFailure | null;
    } {
        return {
            reason: '',
            command: null,
            targetId: null,
            targetOptions: [],
            searchRevision: 0,
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
        isTransfer(): boolean {
            return this.action === 'transfer';
        },
        needsTarget(): boolean {
            return this.isReturn || this.isTransfer;
        },
        actionInput(): TaskActionInput | null {
            if (this.action === 'approved' || this.action === 'rejected') {
                return { outcome: this.action, reason: this.reason };
            }

            const reason = this.reason.trim();
            if (!this.targetId || !reason) {
                return null;
            }

            return this.isReturn ? { targetExecutionId: this.targetId, reason } : { assigneeId: this.targetId, reason };
        },
        canSubmit(): boolean {
            return !this.isSubmitting && this.actionInput !== null;
        },
        canRetry(): boolean {
            return this.failure === 'retryError';
        },
    },
    mounted() {
        if (this.needsTarget) {
            void this.loadTargets();
        }
    },
    beforeUnmount() {
        this.isActive = false;
    },
    methods: {
        async loadTargets(): Promise<void> {
            this.isLoadingTargets = true;
            try {
                const options = this.isTransfer
                    ? await this.findTransferTargets('')
                    : (await this.approvalFlowApiService.returnTargets(this.instanceId, this.taskId)).map((target) => ({
                          value: target.executionId,
                          label: this.$t('sw-approval-flow.detail.step', { number: target.sequence }),
                      }));
                if (this.isActive) {
                    this.targetOptions = options;
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
        async findTransferTargets(term: string): Promise<TargetOption[]> {
            const candidates = await this.approvalFlowApiService.transferCandidates(this.instanceId, this.taskId, term);
            return candidates.map((candidate) => ({ value: candidate.id, label: candidate.name }));
        },
        async searchTransferTargets({ searchTerm }: { searchTerm: string }): Promise<TargetOption[] | undefined> {
            const revision = ++this.searchRevision;
            try {
                const options = await this.findTransferTargets(searchTerm);
                if (this.isActive && revision === this.searchRevision) {
                    return options;
                }
            } catch {
                if (this.isActive && revision === this.searchRevision) {
                    this.failure = 'unavailable';
                }
            }

            return undefined;
        },
        rememberTarget(option: TargetOption): void {
            if (!this.targetOptions.some((target) => target.value === option.value)) {
                this.targetOptions = [...this.targetOptions, option];
            }
        },
        submitCommand(command: TaskCommand): Promise<unknown> {
            if ('assigneeId' in command) {
                return this.approvalFlowApiService.transferTask(this.instanceId, this.taskId, command);
            }
            if ('targetExecutionId' in command) {
                return this.approvalFlowApiService.returnTask(this.instanceId, this.taskId, command);
            }

            return this.approvalFlowApiService.decide(this.instanceId, this.taskId, command);
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
                await this.submitCommand(this.command);
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
