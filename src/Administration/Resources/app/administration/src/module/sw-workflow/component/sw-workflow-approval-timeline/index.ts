/** @sw-package framework */
import { WORKFLOW_INSTANCE_STATUSES, WORKFLOW_TASK_DECISIONS } from 'src/core/workflow/workflow-runtime.constants';
import type { PropType } from 'vue';
import type { RuntimeInstance, RuntimeTaskHistory } from 'src/core/workflow/workflow-runtime.types';
import { absoluteTime, initialOf, relativeTime } from '../../util/workflow-runtime-time.util';
import template from './sw-workflow-approval-timeline.html.twig';
import './sw-workflow-approval-timeline.scss';

/** Completed decisions are chronological; pending task state is supplied separately. */
/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-workflow-approval-timeline',

    template,

    props: {
        logs: { type: Array as PropType<NonNullable<RuntimeInstance['logs']>>, default: () => [] },
        history: {
            type: Array as PropType<RuntimeTaskHistory[]>,
            required: false,
            default: () => [],
        },

        currentNode: {
            type: Object as PropType<{ id: string; name: string; enteredAt: string | null } | null>,
            required: false,
            default: null,
        },

        status: {
            type: String,
            required: false,
            default: WORKFLOW_INSTANCE_STATUSES.RUNNING,
        },
    },

    computed: {
        rows() {
            const auditOperations = this.logs
                .filter(
                    (log) =>
                        log.action === WORKFLOW_TASK_DECISIONS.RESUBMIT || log.action === WORKFLOW_TASK_DECISIONS.REMIND,
                )
                .map((log) => ({
                    id: log.id,
                    taskId: '',
                    nodeId: '',
                    nodeName: '',
                    round: log.payload?.round,
                    assignee: log.operator ?? { id: '', name: this.$t('sw-workflow.reminder.system') },
                    countersignType: '',
                    decision: log.action,
                    comment: log.comment,
                    completedAt: log.createdAt,
                    target: null,
                }));
            const sorted = [...this.history, ...auditOperations].sort((left, right) => {
                return new Date(left.completedAt ?? 0).getTime() - new Date(right.completedAt ?? 0).getTime();
            });

            return sorted.map((row) => ({
                ...row,
                kind: this.decisionKind(row.decision),
                relative: relativeTime(row.completedAt),
                absolute: absoluteTime(row.completedAt),
                initial: initialOf(row.assignee?.name),
            }));
        },

        pending() {
            return this.status === WORKFLOW_INSTANCE_STATUSES.RUNNING && this.currentNode ? this.currentNode : null;
        },
    },

    methods: {
        decisionKind(decision: string): string {
            if (decision === WORKFLOW_TASK_DECISIONS.AGREE) {
                return 'agreed';
            }
            return decision === WORKFLOW_TASK_DECISIONS.REJECT ? 'rejected' : 'operation';
        },

        relative(value: string | null) {
            return relativeTime(value);
        },

        absolute(value: string | null) {
            return absoluteTime(value);
        },

        decisionLabel(decision: string) {
            const labels: Record<string, string> = {
                [WORKFLOW_TASK_DECISIONS.REMIND]: 'sw-workflow.runtime.timeline.remind',
                [WORKFLOW_TASK_DECISIONS.OPERATION]: 'sw-workflow.runtime.timeline.operation',
                [WORKFLOW_TASK_DECISIONS.AGREE]: 'sw-workflow.runtime.timeline.agreed',
                [WORKFLOW_TASK_DECISIONS.REJECT]: 'sw-workflow.runtime.timeline.rejected',
                [WORKFLOW_TASK_DECISIONS.TRANSFER]: 'sw-workflow.runtime.timeline.transfer',
                [WORKFLOW_TASK_DECISIONS.ADD_SIGN]: 'sw-workflow.runtime.timeline.add-sign',
                [WORKFLOW_TASK_DECISIONS.RETURN]: 'sw-workflow.runtime.timeline.return',
                [WORKFLOW_TASK_DECISIONS.RESUBMIT]: 'sw-workflow.runtime.timeline.resubmit',
            };
            return labels[decision] ? this.$t(labels[decision]) : decision;
        },
    },
});
