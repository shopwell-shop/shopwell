import type { WorkflowFormField } from '../../util/workflow-runtime-form.util';
import type { RuntimeInstance, RuntimeGraph, RuntimeTask } from 'src/core/workflow/workflow-runtime.types';
/** @sw-package framework */
import { absoluteTime, relativeTime } from '../../util/workflow-runtime-time.util';
import { formatFormValue } from '../../util/workflow-runtime-form.util';
import template from './sw-workflow-instance.html.twig';
import './sw-workflow-instance.scss';

/**
 * 实例详情：左边看单子与流程图，右边看流转历史并做决定。
 *
 * 表单标签来自已授权的实例响应，参与者无需读取流程定义。
 *
 */
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
    } {
        return {
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
                RUNNING: 'info',
                COMPLETED: 'positive',
                AGREE: 'positive',
                REJECT: 'critical',
                CANCEL: 'neutral',
            };
            return variants[this.status] ?? 'neutral';
        },

        routeInstanceId(): string | null {
            return this.$route.name === 'sw.workflow.instance' ? String(this.$route.params.id) : null;
        },

        status() {
            return this.instance?.status ?? 'RUNNING';
        },

        statusLabel() {
            const map: Record<string, string> = {
                RUNNING: this.$t('sw-workflow.runtime.status.running'),
                COMPLETED: this.$t('sw-workflow.runtime.status.completed'),
                AGREE: this.$t('sw-workflow.runtime.status.approved'),
                REJECT: this.$t('sw-workflow.runtime.status.rejected'),
                CANCEL: this.$t('sw-workflow.runtime.status.canceled'),
            };

            return map[this.status] ?? this.status;
        },

        /** 表单快照按模板顺序展示；模板字段之外的数据（如后端补的字段）接在后面 */
        formFields() {
            const data = this.instance?.formData ?? {};
            const schema = Array.isArray(this.instance?.formSchema) ? this.instance.formSchema : [];
            const known = new Set(schema.map((field) => field.key));

            const rows: Array<{ key: string; label: string; value: unknown; type?: string }> = schema
                .filter((field) => Object.prototype.hasOwnProperty.call(data, field.key))
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
                states[execution.nodeId] = executionStates[execution.status];
            }

            return states;
        },

        /** 图上每个节点的状态：办过的按决定着色，当前节点标"处理中" */
        nodeStates() {
            const states: Record<string, string> = {};
            const selected = new Set(this.instance?.selectedPath?.nodeIds ?? []);
            const planned = this.graphPath?.nodeIds;
            (this.instance?.graph?.nodes ?? []).forEach((node) => {
                if (planned && !planned.includes(node.id)) states[node.id] = 'SKIPPED';
                else if (selected.has(node.id)) states[node.id] = 'VISITED';
            });

            Object.assign(states, this.executionNodeStates);

            (this.instance?.history ?? []).forEach((row) => {
                states[row.nodeId] = row.decision === 'reject' ? 'REJECTED' : 'AGREED';
            });

            if (this.status === 'RUNNING' && this.instance?.currentNodeId) {
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
                if (outcomes[stage.status]) states[stage.nodeId] = outcomes[stage.status];
            });

            return states;
        },

        graphPath() {
            const path = this.status === 'RUNNING' ? this.instance?.plannedPath : this.instance?.selectedPath;
            return path?.nodeIds?.length ? path : null;
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

        /** 需要我处理的那条任务：优先路由里指定的 taskId，否则取第一条待办 */
        activeTask(): RuntimeTask | null {
            const userId = Shopwell.Store.get('session').currentUser?.id;
            const tasks = (this.instance?.tasks ?? []).filter(
                (task) => task.actionable && task.state === 'todo' && task.assignee?.id === userId,
            );
            const requested = this.$route.query.taskId;

            if (requested) {
                const match = tasks.find((task) => task.id === requested);

                if (match) {
                    return match;
                }
            }

            return tasks[0] ?? null;
        },

        canAct() {
            return (
                !this.isActing &&
                !this.isLoading &&
                !this.loadError &&
                this.acl.can('workflow_participant.approver') &&
                this.activeTask !== null &&
                this.status === 'RUNNING'
            );
        },

        canCancel(): boolean {
            return this.instance?.canCancel === true && !this.isActing && !this.isLoading;
        },

        approvalStages() {
            return this.instance?.approvalStages ?? [];
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
        routeInstanceId: {
            immediate: true,
            handler(id: string | null) {
                this.viewRevision += 1;
                this.loadRevision += 1;
                this.instanceId = id;
                this.instance = null;
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
        this.viewRevision += 1;
        this.loadRevision += 1;
    },

    methods: {
        async cancelInstance(): Promise<void> {
            if (!this.canCancel || !this.instanceId || !this.cancelReason.trim()) return;
            const revision = this.viewRevision;
            this.isActing = true;
            try {
                await this.workflowRuntimeApiService.cancelInstance(this.instanceId, this.cancelReason.trim());
                if (revision !== this.viewRevision) return;
                this.showCancel = false;
                this.createNotificationSuccess({ message: this.$t('sw-workflow.runtime.instance.cancelDone') });
                await this.loadInstance();
            } catch (error) {
                if (revision === this.viewRevision) this.createNotificationError({ message: this.errorMessage(error) });
            } finally {
                if (revision === this.viewRevision) this.isActing = false;
            }
        },
        loadInstance(): Promise<void> {
            if (!this.instanceId) return Promise.resolve();
            const loadRevision = ++this.loadRevision;
            this.isLoading = true;
            this.loadError = '';

            return this.workflowRuntimeApiService
                .getInstance(this.instanceId)
                .then((instance) => {
                    if (loadRevision !== this.loadRevision) return;
                    this.instance = instance;
                    this.graph = instance.graph ?? { nodes: [], edges: [] };
                })
                .catch((error) => {
                    if (loadRevision === this.loadRevision) this.loadError = this.errorMessage(error);
                })
                .finally(() => {
                    if (loadRevision === this.loadRevision) this.isLoading = false;
                });
        },

        onApprove(comment: string) {
            void this.act('approveTask', comment);
        },

        onReject(comment: string) {
            void this.act('rejectTask', comment);
        },

        act(method: 'approveTask' | 'rejectTask', comment: string) {
            if (!this.canAct || !this.activeTask) {
                return;
            }

            const viewRevision = this.viewRevision;
            this.isActing = true;

            return this.workflowRuntimeApiService[method](this.activeTask.id, comment)
                .then(() => {
                    if (viewRevision !== this.viewRevision) return;
                    this.createNotificationSuccess({
                        message: this.$t('sw-workflow.runtime.instance.actionDone'),
                    });

                    // 重新拉一次实例，而不是本地改状态：会签的下一票、节点推进、
                    // 分支选择都在后端算，前端猜不出来
                    return this.loadInstance();
                })
                .catch((error) => {
                    if (viewRevision !== this.viewRevision) return;
                    this.createNotificationError({ message: this.errorMessage(error) });
                    return this.loadInstance();
                })
                .finally(() => {
                    if (viewRevision === this.viewRevision) this.isActing = false;
                });
        },

        formatValue(value: unknown, field?: { type?: string; key: string; label: string }) {
            if (field?.type) {
                const formatted = formatFormValue(
                    field as WorkflowFormField,
                    value,
                    Shopwell.Store.get('session').currentLocale || 'en',
                );
                if (formatted !== null) return formatted;
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

            // 对象型字段（附件、地址）直接摊开比显示 [object Object] 有用
            if (typeof value === 'object') {
                return JSON.stringify(value);
            }

            return typeof value === 'string' || typeof value === 'number' ? String(value) : '—';
        },

        errorMessage(error: unknown): string {
            const failure = error as { response?: { data?: { errors?: Array<{ detail?: string }> } }; message?: string };
            return (
                failure?.response?.data?.errors?.[0]?.detail ??
                failure?.message ??
                this.$t('sw-workflow.runtime.instance.loadError')
            );
        },
    },
});
