import type { PropType } from 'vue';
import type { RuntimeTask } from 'src/core/workflow/workflow-runtime.types';
/** @sw-package framework */
import { absoluteTime, initialOf, relativeTime } from '../../util/workflow-runtime-time.util';
import template from './sw-workflow-approval-timeline.html.twig';
import './sw-workflow-approval-timeline.scss';

/**
 * 审批时间线。
 *
 * 数据源是 `his_task`（已完成的决策），不是 `task`（当前待办）：待办里
 * 还没决策的行没有意见也没有时间，混进来会变成一条"空白节点"。
 * 当前节点单独用一个"处理中"条目接在末尾，由页面传入。
 *
 * 顺序按 `completedAt` 升序：时间线是给人顺着读的，最新的在最下面。
 */
/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-workflow-approval-timeline',

    template,

    props: {
        history: {
            type: Array as PropType<RuntimeTask[]>,
            required: false,
            default: () => [],
        },

        /** 当前停在哪个节点（名字 + 进入时间），没有就不显示"处理中" */
        currentNode: {
            type: Object as PropType<{ id: string; name: string; enteredAt: string | null } | null>,
            required: false,
            default: null,
        },

        /** 实例终态：REJECTED / APPROVED / CANCELED，用来给末条换色 */
        status: {
            type: String,
            required: false,
            default: 'RUNNING',
        },
    },

    computed: {
        rows() {
            const sorted = [...this.history].sort((left, right) => {
                return new Date(left.completedAt ?? 0).getTime() - new Date(right.completedAt ?? 0).getTime();
            });

            return sorted.map((row) => ({
                ...row,
                kind: row.decision === 'reject' ? 'rejected' : 'agreed',
                relative: relativeTime(row.completedAt),
                absolute: absoluteTime(row.completedAt),
                initial: initialOf(row.assignee?.name),
            }));
        },

        pending() {
            return this.status === 'RUNNING' && this.currentNode ? this.currentNode : null;
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
            if (decision === 'reject') {
                return this.$t('sw-workflow.runtime.timeline.rejected');
            }

            return this.$t('sw-workflow.runtime.timeline.agreed');
        },
    },
});
