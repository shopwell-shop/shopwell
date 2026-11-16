/** @sw-package framework */
import { WORKFLOW_INSTANCE_STATUSES, WORKFLOW_TASK_DECISIONS } from 'src/core/workflow/workflow-runtime.constants';
import type { PropType } from 'vue';
import type { RuntimeTaskHistory } from 'src/core/workflow/workflow-runtime.types';
import { absoluteTime, initialOf, relativeTime } from '../../util/workflow-runtime-time.util';
import template from './sw-workflow-approval-timeline.html.twig';
import './sw-workflow-approval-timeline.scss';

/** Completed decisions are chronological; pending task state is supplied separately. */
/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-workflow-approval-timeline',

    template,

    props: {
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
            const sorted = [...this.history].sort((left, right) => {
                return new Date(left.completedAt ?? 0).getTime() - new Date(right.completedAt ?? 0).getTime();
            });

            return sorted.map((row) => ({
                ...row,
                kind: row.decision === WORKFLOW_TASK_DECISIONS.REJECT ? 'rejected' : 'agreed',
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
        relative(value: string | null) {
            return relativeTime(value);
        },

        absolute(value: string | null) {
            return absoluteTime(value);
        },

        decisionLabel(decision: string) {
            if (decision === WORKFLOW_TASK_DECISIONS.REJECT) {
                return this.$t('sw-workflow.runtime.timeline.rejected');
            }

            return this.$t('sw-workflow.runtime.timeline.agreed');
        },
    },
});
