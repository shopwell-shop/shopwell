/** @sw-package framework */
import {
    WORKFLOW_INSTANCE_STATUSES,
    WORKFLOW_TASK_STATES,
    WORKFLOW_TASK_DECISIONS,
    WORKFLOW_FORM_ACCESS,
} from 'src/core/workflow/workflow-runtime.constants';
import type { WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';
import type { RuntimeInstance, RuntimeGraph, RuntimeTask } from 'src/core/workflow/workflow-runtime.types';
import { absoluteTime, relativeTime } from '../../util/workflow-runtime-time.util';
import { formatFormValue } from '../../util/workflow-runtime-form.util';
import type { RuntimeTaskParticipation } from 'src/core/workflow/workflow-runtime.types';
import template from './sw-workflow-instance.html.twig';
import './sw-workflow-instance.scss';

/** Authorized instance responses include the form snapshot without requiring definition access. */
/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-workflow-instance',

    template,

    mixins: [Shopwell.Mixin.getByName('notification')],

    inject: ['workflowRuntimeApiService', 'acl'],

    data(): {
        instanceId: string | null;
        viewRevision: number;
        loadRevision: number;
        instance: RuntimeInstance | null;
        graph: RuntimeGraph;
        isLoading: boolean;
        isActing: boolean;
        loadError: string;
        cancelReason: string;
        showCancel: boolean;
        formPatch: Record<string, string | string[]>;
        formValid: boolean;
        reminderRefreshTimer: ReturnType<typeof setTimeout> | null;
    } {
        return {
            formPatch: {},
            formValid: false,
            reminderRefreshTimer: null,
            cancelReason: '',
            showCancel: false,
            instanceId: null,
            viewRevision: 0,
            loadRevision: 0,
            instance: null,
            graph: { nodes: [], edges: [] },
            isLoading: true,
            isActing: false,
            loadError: '',
        };
    },

    metaInfo() {
        return { title: this.$createTitle() };
    },

    computed: {
        statusVariant(): string {
            const variants: Record<string, string> = {
                [WORKFLOW_INSTANCE_STATUSES.RUNNING]: 'info',
                [WORKFLOW_INSTANCE_STATUSES.RETURNED]: 'warning',
                [WORKFLOW_INSTANCE_STATUSES.COMPLETED]: 'positive',
                [WORKFLOW_INSTANCE_STATUSES.AGREED]: 'positive',
                [WORKFLOW_INSTANCE_STATUSES.REJECTED]: 'critical',
                [WORKFLOW_INSTANCE_STATUSES.CANCELED]: 'neutral',
            };
            return variants[this.status] ?? 'neutral';
        },

        routeInstanceId(): string | null {
            return this.$route.name === 'sw.workflow.instance' ? String(this.$route.params.id) : null;
        },

        status() {
            return this.instance?.status ?? WORKFLOW_INSTANCE_STATUSES.RUNNING;
        },

        statusLabel() {
            const map: Record<string, string> = {
                [WORKFLOW_INSTANCE_STATUSES.RUNNING]: this.$t('sw-workflow.runtime.status.running'),
                [WORKFLOW_INSTANCE_STATUSES.RETURNED]: this.$t('sw-workflow.runtime.status.returned'),
                [WORKFLOW_INSTANCE_STATUSES.COMPLETED]: this.$t('sw-workflow.runtime.status.completed'),
                [WORKFLOW_INSTANCE_STATUSES.AGREED]: this.$t('sw-workflow.runtime.status.approved'),
                [WORKFLOW_INSTANCE_STATUSES.REJECTED]: this.$t('sw-workflow.runtime.status.rejected'),
                [WORKFLOW_INSTANCE_STATUSES.CANCELED]: this.$t('sw-workflow.runtime.status.canceled'),
            };

            return map[this.status] ?? this.status;
        },

        /** Preserve published field order, then include additional response fields. */
        formFields() {
            const data = this.instance?.formData ?? {};
            const schema = Array.isArray(this.instance?.formSchema) ? this.instance.formSchema : [];
            const known = new Set(schema.map((field) => field.key));

            const rows: Array<{ key: string; label: string; value: unknown; type?: string }> = schema
                .filter(
                    (field) =>
                        Object.prototype.hasOwnProperty.call(data, field.key) &&
                        (!this.activeTask ||
                            this.instance?.formPermissions?.[field.key]?.access !== WORKFLOW_FORM_ACCESS.EDITABLE),
                )
                .map((field) => ({ ...field, value: data[field.key] }));

            Object.keys(data)
                .filter((key) => !known.has(key))
                .forEach((key) => rows.push({ key, label: key, value: data[key] }));

            return rows;
        },

        executionNodeStates(): Record<string, string> {
            const states: Record<string, string> = {};
            const executionStates: Record<string, string> = {
                running: 'TODO',
                waiting: 'TODO',
                completed: 'COMPLETED',
                canceled: 'CANCELED',
            };

            for (const execution of this.instance?.executions ?? []) {
                if (execution.round === this.instance?.round) {
                    states[execution.nodeId] = executionStates[execution.status];
                }
            }

            return states;
        },

        nodeStates() {
            const states: Record<string, string> = {};
            const selected = new Set(this.instance?.selectedPath?.nodeIds ?? []);
            const planned = this.graphPath?.nodeIds;
            (this.instance?.graph?.nodes ?? []).forEach((node) => {
                if (planned && !planned.includes(node.id)) {
                    states[node.id] = 'SKIPPED';
                } else if (selected.has(node.id)) {
                    states[node.id] = 'VISITED';
                }
            });

            Object.assign(states, this.executionNodeStates);

            (this.instance?.history ?? [])
                .filter((row) => row.round === this.instance?.round)
                .forEach((row) => {
                    if (row.decision === WORKFLOW_TASK_DECISIONS.REJECT) {
                        states[row.nodeId] = 'REJECTED';
                    } else if (row.decision === WORKFLOW_TASK_DECISIONS.AGREE) {
                        states[row.nodeId] = 'AGREED';
                    }
                });

            if (this.status === WORKFLOW_INSTANCE_STATUSES.RUNNING && this.instance?.currentNodeId) {
                states[this.instance.currentNodeId] = 'TODO';
            }

            this.approvalStages.forEach((stage) => {
                const outcomes: Record<string, string> = {
                    active: 'TODO',
                    approved: 'AGREED',
                    rejected: 'REJECTED',
                    skipped: 'SKIPPED',
                };
                delete states[stage.nodeId];

                if (outcomes[stage.status]) {
                    states[stage.nodeId] = outcomes[stage.status];
                }
            });

            return states;
        },

        graphPath() {
            const path =
                this.status === WORKFLOW_INSTANCE_STATUSES.RUNNING
                    ? this.instance?.plannedPath
                    : this.instance?.selectedPath;
            return path?.nodeIds?.length ? path : null;
        },

        formChanges() {
            return (this.instance?.formChanges ?? []).map((change) => ({
                ...change,
                createdAt: absoluteTime(change.createdAt),
            }));
        },

        history() {
            return this.instance?.history ?? [];
        },

        currentNode() {
            if (this.instance?.currentNode) {
                return this.instance.currentNode;
            }

            if (!this.instance?.currentNodeId) {
                return null;
            }

            const node = this.instance.graph?.nodes?.find((item) => item.id === this.instance?.currentNodeId);

            return {
                id: this.instance.currentNodeId,
                name: node?.properties?.name ?? this.instance.currentNodeId,
                enteredAt: null,
            };
        },

        activeTask(): RuntimeTask | null {
            const userId = Shopwell.Store.get('session').currentUser?.id;
            const tasks = (this.instance?.tasks ?? []).filter(
                (task) => task.actionable && task.state === WORKFLOW_TASK_STATES.TODO && task.assignee?.id === userId,
            );
            const requested = this.$route.query.taskId;

            if (requested) {
                const match = tasks.find((task) => task.id === requested);

                if (match) {
                    return match;
                }
            }

            if (this.instance?.formContextId) {
                return tasks.find((task) => task.id === this.instance?.formContextId) ?? null;
            }
            return this.instance?.formContextRequired ? null : (tasks[0] ?? null);
        },

        canAct() {
            return (
                !this.isActing &&
                !this.isLoading &&
                !this.loadError &&
                this.acl.can('workflow_participant.approver') &&
                this.activeTask !== null &&
                this.status === WORKFLOW_INSTANCE_STATUSES.RUNNING
            );
        },

        hasFormChanges(): boolean {
            return Object.keys(this.formPatch).length > 0;
        },

        showRemind(): boolean {
            return this.instance?.canCancel === true && this.status === WORKFLOW_INSTANCE_STATUSES.RUNNING;
        },
        canRemind(): boolean {
            return this.instance?.canRemind === true && !this.isActing && !this.isLoading && !this.hasFormChanges;
        },
        canCancel(): boolean {
            return this.instance?.canCancel === true && !this.isActing && !this.isLoading;
        },

        approvalStages() {
            return this.instance?.approvalStages ?? [];
        },

        reminderTasks() {
            return (this.instance?.tasks ?? []).filter((task) => {
                return (
                    task.state === WORKFLOW_TASK_STATES.TODO &&
                    Boolean(task.reminder?.dueAt || task.reminder?.lastRemindedAt)
                );
            });
        },

        approvalTasks() {
            return this.instance?.approvalTasks ?? [];
        },

        startedAt() {
            return this.instance?.startedAt ? absoluteTime(this.instance.startedAt) : '—';
        },

        waitingFor() {
            return this.instance?.startedAt ? relativeTime(this.instance.startedAt) : '';
        },
    },

    watch: {
        hasFormChanges() {
            this.scheduleReminderRefresh();
        },
        '$route.query.taskId'() {
            this.formPatch = {};
            this.formValid = false;
            void this.loadInstance();
        },
        routeInstanceId: {
            immediate: true,
            handler(id: string | null) {
                this.clearReminderRefresh();
                this.viewRevision += 1;
                this.loadRevision += 1;
                this.instanceId = id;
                this.instance = null;
                this.formPatch = {};
                this.formValid = false;
                this.graph = { nodes: [], edges: [] };
                this.isActing = false;
                this.showCancel = false;
                this.cancelReason = '';

                if (!id) {
                    this.isLoading = false;
                    return;
                }

                void this.loadInstance();
            },
        },
    },

    beforeUnmount() {
        this.clearReminderRefresh();
        this.viewRevision += 1;
        this.loadRevision += 1;
    },

    methods: {
        clearReminderRefresh(): void {
            if (this.reminderRefreshTimer !== null) {
                clearTimeout(this.reminderRefreshTimer);
                this.reminderRefreshTimer = null;
            }
        },
        scheduleReminderRefresh(): void {
            this.clearReminderRefresh();
            if (!this.showRemind || this.instance?.canRemind === true || this.hasFormChanges) {
                return;
            }
            const deadlines = (this.instance?.tasks ?? [])
                .map((task) => Date.parse(task.reminder?.nextRemindAt ?? ''))
                .filter((timestamp) => Number.isFinite(timestamp) && timestamp > Date.now());
            if (this.instance?.tasks?.some((task) => task.reminder?.deliveryPending)) {
                deadlines.push(Date.now() + 60_000);
            }
            if (deadlines.length === 0) {
                return;
            }
            const delay = Math.min(...deadlines) - Date.now();
            this.reminderRefreshTimer = setTimeout(() => void this.loadInstance(), Math.max(1000, delay));
        },
        async remindInstance(): Promise<void> {
            if (!this.canRemind || !this.instanceId) {
                return;
            }
            const revision = this.viewRevision;
            this.isActing = true;
            try {
                await this.workflowRuntimeApiService.remindInstance(this.instanceId);
                if (revision !== this.viewRevision) {
                    return;
                }
                this.createNotificationSuccess({ message: this.$t('sw-workflow.reminder.sent') });
                await this.loadInstance();
            } catch (error) {
                if (revision === this.viewRevision) {
                    this.createNotificationError({ message: this.errorMessage(error) });
                }
            } finally {
                if (revision === this.viewRevision) {
                    this.isActing = false;
                }
            }
        },
        async cancelInstance(): Promise<void> {
            if (!this.canCancel || !this.instanceId || !this.cancelReason.trim()) {
                return;
            }

            const revision = this.viewRevision;
            this.isActing = true;
            try {
                await this.workflowRuntimeApiService.cancelInstance(this.instanceId, this.cancelReason.trim());

                if (revision !== this.viewRevision) {
                    return;
                }

                this.showCancel = false;
                this.createNotificationSuccess({ message: this.$t('sw-workflow.runtime.instance.cancelDone') });
                await this.loadInstance();
            } catch (error) {
                if (revision === this.viewRevision) {
                    this.createNotificationError({ message: this.errorMessage(error) });
                }
            } finally {
                if (revision === this.viewRevision) {
                    this.isActing = false;
                }
            }
        },
        loadInstance(): Promise<void> {
            if (!this.instanceId) {
                return Promise.resolve();
            }

            const loadRevision = ++this.loadRevision;
            this.isLoading = true;
            this.loadError = '';

            const taskId = this.$route.query.taskId;
            const request =
                typeof taskId === 'string'
                    ? this.workflowRuntimeApiService.getInstance(this.instanceId, taskId)
                    : this.workflowRuntimeApiService.getInstance(this.instanceId);

            return request
                .then((instance) => {
                    if (loadRevision !== this.loadRevision) {
                        return;
                    }

                    this.formPatch = {};
                    this.formValid = !Object.values(instance.formPermissions ?? {}).some(
                        (permission) => permission.access === WORKFLOW_FORM_ACCESS.EDITABLE,
                    );
                    this.instance = instance;
                    this.graph = instance.graph ?? { nodes: [], edges: [] };
                    this.scheduleReminderRefresh();
                })
                .catch((error) => {
                    if (loadRevision === this.loadRevision) {
                        this.loadError = this.errorMessage(error);
                    }
                })
                .finally(() => {
                    if (loadRevision === this.loadRevision) {
                        this.isLoading = false;
                    }
                });
        },

        onApprove(comment: string) {
            if (!this.formValid) {
                return;
            }
            const form =
                this.instance?.formVersion === undefined
                    ? undefined
                    : {
                          formData: this.formPatch,
                          formVersion: this.instance.formVersion,
                      };
            void this.act((id) =>
                form
                    ? this.workflowRuntimeApiService.approveTask(id, comment, form)
                    : this.workflowRuntimeApiService.approveTask(id, comment),
            );
        },

        selectFormContext(taskId: string) {
            if (!taskId || this.isActing) {
                return;
            }
            void this.$router.replace({ query: { ...this.$route.query, taskId } });
        },

        onReturn(comment: string) {
            void this.act((id) => this.workflowRuntimeApiService.returnTask(id, comment));
        },

        onReject(comment: string) {
            void this.act((id) => this.workflowRuntimeApiService.rejectTask(id, comment));
        },

        onParticipate(operation: RuntimeTaskParticipation) {
            void this.act((id) => this.workflowRuntimeApiService.participateTask(id, operation));
        },

        act(operation: (id: string) => Promise<void>) {
            if (!this.canAct || !this.activeTask) {
                return;
            }

            const viewRevision = this.viewRevision;
            this.isActing = true;

            return operation(this.activeTask.id)
                .then(() => {
                    if (viewRevision !== this.viewRevision) {
                        return;
                    }

                    this.createNotificationSuccess({
                        message: this.$t('sw-workflow.runtime.instance.actionDone'),
                    });

                    // Reload server-calculated voting results, node advancement and branch selection.
                    return this.loadInstance();
                })
                .catch((error) => {
                    if (viewRevision !== this.viewRevision) {
                        return;
                    }

                    this.createNotificationError({ message: this.errorMessage(error) });
                    return this.loadInstance();
                })
                .finally(() => {
                    if (viewRevision === this.viewRevision) {
                        this.isActing = false;
                    }
                });
        },

        formatValue(value: unknown, field?: { type?: string; key: string; label: string }) {
            if (field?.type) {
                const formatted = formatFormValue(
                    field as WorkflowFormField,
                    value,
                    Shopwell.Store.get('session').currentLocale || 'en',
                );

                if (formatted !== null) {
                    return formatted;
                }
            }

            if (value === null || value === undefined || value === '') {
                return '—';
            }

            if (Array.isArray(value)) {
                return value
                    .map((item: unknown) =>
                        typeof item === 'string' || typeof item === 'number' ? String(item) : JSON.stringify(item),
                    )
                    .join(', ');
            }

            if (typeof value === 'boolean') {
                return this.$t(value ? 'global.default.yes' : 'global.default.no');
            }

            if (typeof value === 'object') {
                return JSON.stringify(value);
            }

            return typeof value === 'string' || typeof value === 'number' ? String(value) : '—';
        },

        errorMessage(error: unknown): string {
            const failure = error as {
                response?: { data?: { errors?: Array<{ code?: string; detail?: string }> } };
                message?: string;
            };
            const apiError = failure.response?.data?.errors?.[0];
            const messages: Record<string, string> = {
                FRAMEWORK__WORKFLOW_FORM_CONFLICT: 'sw-workflow.formPermissions.conflict',
                FRAMEWORK__WORKFLOW_REMINDER_UNAVAILABLE: 'sw-workflow.reminder.unavailable',
            };
            if (apiError?.code && messages[apiError.code]) {
                return this.$t(messages[apiError.code]);
            }
            return apiError?.detail ?? failure.message ?? this.$t('sw-workflow.runtime.instance.loadError');
        },
    },
});
