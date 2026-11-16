/** @sw-package framework */
import {
    WORKFLOW_INSTANCE_STATUSES,
    WORKFLOW_TASK_DECISIONS,
    WORKFLOW_COUNTERSIGN_TYPES,
} from 'src/core/workflow/workflow-runtime.constants';
import type {
    RuntimeTodoTask,
    RuntimeDoneTask,
    RuntimeInstanceListItem,
    RuntimePage,
} from 'src/core/workflow/workflow-runtime.types';

type QueueRow =
    | (RuntimeTodoTask & { queue: 'todo' })
    | (RuntimeDoneTask & { queue: 'done' })
    | (RuntimeInstanceListItem & { queue: 'mine' });
import { absoluteTime, relativeTime } from '../../util/workflow-runtime-time.util';
import template from './sw-workflow-tasks.html.twig';
import './sw-workflow-tasks.scss';

function queueStartedAt(row: QueueRow): string | null | undefined {
    switch (row.queue) {
        case 'mine':
            return row.startedAt;
        case 'todo':
            return row.createdAt;
        default:
            return null;
    }
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-workflow-tasks',

    template,

    mixins: [Shopwell.Mixin.getByName('notification')],

    inject: ['workflowRuntimeApiService', 'acl'],

    data(): {
        activeTab: string;
        rows: QueueRow[];
        total: number;
        page: number;
        limit: number;
        isLoading: boolean;
        loadRevision: number;
        showRequestPicker: boolean;
        hasError: boolean;
    } {
        return {
            activeTab: 'todo',
            showRequestPicker: false,
            hasError: false,
            loadRevision: 0,
            rows: [],
            total: 0,
            page: 1,
            limit: 25,
            isLoading: false,
        };
    },

    metaInfo() {
        return { title: this.$createTitle() };
    },

    computed: {
        tabs() {
            return [
                { key: 'todo', label: this.$t('sw-workflow.runtime.todo.tabTodo') },
                { key: 'done', label: this.$t('sw-workflow.runtime.todo.tabDone') },
                { key: 'mine', label: this.$t('sw-workflow.runtime.todo.tabMine') },
            ].map((tab) => ({ name: tab.key, label: tab.label, onClick: () => this.onTabChange(tab.key) }));
        },

        isTodo() {
            return this.activeTab === 'todo';
        },

        columns() {
            if (this.activeTab === 'mine') {
                return [
                    {
                        property: 'title',
                        sortable: false,
                        label: this.$t('sw-workflow.runtime.todo.columnTitle'),
                        primary: true,
                    },
                    { property: 'status', sortable: false, label: this.$t('sw-workflow.runtime.todo.columnStatus') },
                    { property: 'startedAt', sortable: false, label: this.$t('sw-workflow.runtime.todo.columnStarted') },
                ];
            }

            if (this.isTodo) {
                return [
                    {
                        property: 'title',
                        sortable: false,
                        label: this.$t('sw-workflow.runtime.todo.columnTitle'),
                        primary: true,
                    },
                    { property: 'nodeName', sortable: false, label: this.$t('sw-workflow.runtime.todo.columnNode') },
                    {
                        property: 'countersignType',
                        sortable: false,
                        label: this.$t('sw-workflow.runtime.todo.columnCountersign'),
                    },
                    { property: 'initiator', sortable: false, label: this.$t('sw-workflow.runtime.todo.columnInitiator') },
                    { property: 'startedAt', sortable: false, label: this.$t('sw-workflow.runtime.todo.columnWaiting') },
                    { property: 'actions', sortable: false, label: '', align: 'right' },
                ];
            }

            return [
                {
                    property: 'title',
                    sortable: false,
                    label: this.$t('sw-workflow.runtime.todo.columnTitle'),
                    primary: true,
                },
                { property: 'nodeName', sortable: false, label: this.$t('sw-workflow.runtime.todo.columnNode') },
                { property: 'decision', sortable: false, label: this.$t('sw-workflow.runtime.todo.columnDecision') },
                { property: 'comment', sortable: false, label: this.$t('sw-workflow.runtime.todo.columnComment') },
                { property: 'completedAt', sortable: false, label: this.$t('sw-workflow.runtime.todo.columnCompleted') },
            ];
        },

        canApprove() {
            return this.acl.can('workflow_participant.approver');
        },
    },

    created() {
        void this.loadRows();
    },

    activated() {
        if (!this.isLoading) {
            void this.loadRows();
        }
    },

    beforeUnmount() {
        this.loadRevision += 1;
    },

    methods: {
        statusVariant(status: string): string {
            const variants: Record<string, string> = {
                [WORKFLOW_INSTANCE_STATUSES.REJECTED]: 'critical',
                [WORKFLOW_INSTANCE_STATUSES.AGREED]: 'positive',
            };
            return variants[status] ?? 'neutral';
        },
        statusLabel(status: string): string {
            const labels: Record<string, string> = {
                [WORKFLOW_INSTANCE_STATUSES.RUNNING]: 'running',
                [WORKFLOW_INSTANCE_STATUSES.AGREED]: 'approved',
                [WORKFLOW_INSTANCE_STATUSES.REJECTED]: 'rejected',
                [WORKFLOW_INSTANCE_STATUSES.CANCELED]: 'canceled',
            };
            return labels[status] ? this.$t(`sw-workflow.runtime.status.${labels[status]}`) : status;
        },
        loadRows() {
            const revision = ++this.loadRevision;
            this.isLoading = true;
            this.hasError = false;
            this.rows = [];

            const params = { limit: this.limit, offset: (this.page - 1) * this.limit };
            const request = this.requestRows(params);

            return request
                .then((response) => {
                    if (revision !== this.loadRevision) {
                        return;
                    }

                    this.rows = response.data ?? [];
                    this.total = response.meta?.total ?? this.rows.length;
                })
                .catch((error: unknown) => {
                    if (revision !== this.loadRevision) {
                        return;
                    }

                    this.hasError = true;
                    this.createNotificationError({ message: this.errorMessage(error) });
                    this.rows = [];
                    this.total = 0;
                })
                .finally(() => {
                    if (revision === this.loadRevision) {
                        this.isLoading = false;
                    }
                });
        },

        async requestRows(params: { limit: number; offset: number }): Promise<RuntimePage<QueueRow>> {
            switch (this.activeTab) {
                case 'todo': {
                    const response = await this.workflowRuntimeApiService.listTodo(params);
                    return { ...response, data: response.data.map((row) => ({ ...row, queue: 'todo' })) };
                }
                case 'mine': {
                    const response = await this.workflowRuntimeApiService.listInitiated(params);
                    return { ...response, data: response.data.map((row) => ({ ...row, queue: 'mine' })) };
                }
                default: {
                    const response = await this.workflowRuntimeApiService.listDone(params);
                    return { ...response, data: response.data.map((row) => ({ ...row, queue: 'done' })) };
                }
            }
        },

        onTabChange(key: string) {
            if (this.activeTab === key) {
                return;
            }

            this.activeTab = key;
            this.page = 1;
            void this.loadRows();
        },

        onPageChange({ page, limit }: { page: number; limit: number }) {
            this.page = page;
            this.limit = limit;
            void this.loadRows();
        },

        onOpen(row: QueueRow) {
            void this.$router.push({
                name: 'sw.workflow.instance',
                params: { id: row.queue === 'mine' ? row.id : row.instanceId },
                query: row.queue === 'todo' ? { taskId: row.id } : {},
            });
        },

        waitingLabel(row: QueueRow) {
            const startedAt = queueStartedAt(row);
            return startedAt ? relativeTime(startedAt) : '—';
        },

        waitingTitle(row: QueueRow) {
            const startedAt = queueStartedAt(row);
            return startedAt ? absoluteTime(startedAt) : '';
        },

        completedLabel(row: QueueRow) {
            return row.queue === 'done' && row.completedAt ? relativeTime(row.completedAt) : '—';
        },

        completedTitle(row: QueueRow) {
            return row.queue === 'done' && row.completedAt ? absoluteTime(row.completedAt) : '';
        },

        countersignLabel(type: string) {
            const map: Record<string, string> = {
                [WORKFLOW_COUNTERSIGN_TYPES.SINGLE]: this.$t('sw-workflow.runtime.countersign.single'),
                [WORKFLOW_COUNTERSIGN_TYPES.FIRST_DECISION]: this.$t('sw-workflow.runtime.countersign.firstDecision'),
                [WORKFLOW_COUNTERSIGN_TYPES.OR_SIGN]: this.$t('sw-workflow.runtime.countersign.orSign'),
                [WORKFLOW_COUNTERSIGN_TYPES.COUNTERSIGN]: this.$t('sw-workflow.runtime.countersign.countersign'),
                [WORKFLOW_COUNTERSIGN_TYPES.ORDER_SIGN]: this.$t('sw-workflow.runtime.countersign.orderSign'),
            };

            return map[type] ?? type;
        },

        decisionLabel(decision: string) {
            return this.$t(
                decision === WORKFLOW_TASK_DECISIONS.REJECT
                    ? 'sw-workflow.runtime.timeline.rejected'
                    : 'sw-workflow.runtime.timeline.agreed',
            );
        },

        errorMessage(error: unknown): string {
            const failure = error as { response?: { data?: { errors?: Array<{ detail?: string }> } }; message?: string };
            return (
                failure?.response?.data?.errors?.[0]?.detail ??
                failure?.message ??
                this.$t('sw-workflow.runtime.todo.loadError')
            );
        },
    },
});
