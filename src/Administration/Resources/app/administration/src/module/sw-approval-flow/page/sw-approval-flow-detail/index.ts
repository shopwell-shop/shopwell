/** @sw-package framework */
import type { HttpError } from 'src/core/factory/http-client.types';
import type { ApprovalDetail, ApprovalTaskAction, FormValue } from '../../service/approval-flow.api.service';
import template from './sw-approval-flow-detail.html.twig';
import './sw-approval-flow-detail.scss';

type TaskActionTarget = { instanceId: string; taskId: string; expectedVersion: number; action: ApprovalTaskAction };

type FormRow = { path: string; value: FormValue };

function visibleFormRows(form: Record<string, FormValue>, prefix = 'form'): FormRow[] {
    return Object.entries(form).flatMap(([key, value]) => {
        const path = `${prefix}.${key}`;
        if (value !== null && typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length > 0) {
            return visibleFormRows(value, path);
        }
        return [{ path, value }];
    });
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-approval-flow-detail',
    template,
    inject: ['approvalFlowApiService', 'acl'],
    props: { requestId: { type: String, required: true } },
    data(): {
        detail: ApprovalDetail | null;
        isLoading: boolean;
        loadError: string | null;
        loadRevision: number;
        decisionTarget: TaskActionTarget | null;
        decisionSaved: boolean;
    } {
        return {
            detail: null,
            isLoading: false,
            loadError: null,
            loadRevision: 0,
            decisionTarget: null,
            decisionSaved: false,
        };
    },
    metaInfo() {
        return { title: this.$createTitle() };
    },
    computed: {
        actionableTasks() {
            const userId = Shopwell.Store.get('session').currentUser?.id;
            if (!this.detail || this.detail.request.status !== 'running' || !this.acl.can('approval_flow.editor')) {
                return [];
            }
            return this.steps.flatMap((step) =>
                step.tasks
                    .filter((task) => task.status === 'pending' && task.assigneeId === userId)
                    .map((task) => ({ id: task.id, sequence: step.sequence })),
            );
        },
        backRoute() {
            const { view, ...query } = this.$route.query;
            const inbox = view === 'done' || view === 'started' ? view : 'todo';
            return { name: `sw.approval.flow.${inbox}`, query };
        },
        formRows() {
            if (!this.detail) {
                return [];
            }
            return visibleFormRows(this.detail.form).map((row) => {
                const label = this.detail?.formLabels[row.path];
                return {
                    ...row,
                    label:
                        label?.labelSnippet && this.$te(label.labelSnippet)
                            ? this.$t(label.labelSnippet)
                            : (label?.title ?? row.path),
                };
            });
        },
        history() {
            if (!this.detail) {
                return [];
            }
            const assignees = new Map(this.detail.tasks.map((task) => [task.id, task.assigneeId]));
            const sequences = new Map(this.detail.executions.map((execution) => [execution.id, execution.sequence]));
            const opinions = this.detail.decisions.map((decision) => ({
                taskId: decision.taskId,
                assigneeId: decision.assigneeId,
                label: this.$t(`sw-approval-flow.taskStatus.${decision.decision}`),
                reason: decision.reason,
                recordedAt: decision.decidedAt,
                aggregateVersion: decision.aggregateVersion,
            }));
            const returns = this.detail.returns.map((returned) => ({
                taskId: returned.taskId,
                assigneeId: assignees.get(returned.taskId),
                label: this.$t('sw-approval-flow.detail.returnedTo', {
                    step: this.$t('sw-approval-flow.detail.step', { number: sequences.get(returned.targetExecutionId) }),
                }),
                reason: returned.reason,
                recordedAt: returned.returnedAt,
                aggregateVersion: returned.aggregateVersion,
            }));
            const transfers = this.detail.transfers.map((transfer) => ({
                taskId: transfer.taskId,
                assigneeId: assignees.get(transfer.taskId),
                label: this.$t('sw-approval-flow.detail.transferredTo', {
                    name: this.personName(assignees.get(transfer.replacementTaskId)),
                }),
                reason: transfer.reason,
                recordedAt: transfer.transferredAt,
                aggregateVersion: transfer.aggregateVersion,
            }));
            return [...opinions, ...returns, ...transfers].sort(
                (first, second) => first.aggregateVersion - second.aggregateVersion,
            );
        },
        steps() {
            if (!this.detail) {
                return [];
            }
            return this.detail.executions.map((execution) => ({
                ...execution,
                tasks: this.detail?.tasks.filter((task) => task.executionId === execution.id) ?? [],
            }));
        },
    },
    watch: {
        requestId: {
            immediate: true,
            handler() {
                this.decisionTarget = null;
                this.decisionSaved = false;
                void this.loadDetail();
            },
        },
    },
    beforeUnmount() {
        this.loadRevision += 1;
    },
    methods: {
        openDecision(taskId: string, action: ApprovalTaskAction): void {
            if (!this.detail) {
                return;
            }
            this.decisionSaved = false;
            this.decisionTarget = {
                instanceId: this.detail.request.instanceId,
                taskId,
                expectedVersion: this.detail.request.aggregateVersion,
                action,
            };
        },
        onDecisionCompleted(): void {
            this.decisionTarget = null;
            this.decisionSaved = true;
            void this.loadDetail();
        },
        refreshAfterDecision(): void {
            this.decisionTarget = null;
            void this.loadDetail();
        },
        async loadDetail(): Promise<void> {
            const revision = ++this.loadRevision;
            this.detail = null;
            this.isLoading = true;
            this.loadError = null;
            try {
                const detail = await this.approvalFlowApiService.detail(this.requestId);
                if (revision === this.loadRevision) {
                    this.detail = detail;
                }
            } catch (error) {
                if (revision !== this.loadRevision) {
                    return;
                }
                const status = (error as HttpError | null)?.response?.status;
                this.loadError = status === 404 ? 'notFound' : status === 403 ? 'forbidden' : 'loadError';
            } finally {
                if (revision === this.loadRevision) {
                    this.isLoading = false;
                }
            }
        },
        personName(id?: string): string {
            return (id ? this.detail?.participantNames[id] : null) ?? this.$t('sw-approval-flow.general.deletedUser');
        },
        date(value: string | null): string {
            return value
                ? Shopwell.Utils.format.date(value, { hour: '2-digit', minute: '2-digit' })
                : this.$t('sw-approval-flow.general.notAvailable');
        },
        formValue(value: FormValue): string {
            if (value === null) {
                return this.$t('sw-approval-flow.detail.notFilled');
            }
            if (typeof value === 'boolean') {
                return this.$t(`global.default.${value ? 'yes' : 'no'}`);
            }
            return typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value);
        },
    },
});
