/** @sw-package framework */
import type { PropType } from 'vue';
import { WORKFLOW_ASSIGNEE_SOURCES } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import template from './sw-workflow-organization-assignee-summary.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowApprovalService'],
    props: { node: { type: Object as PropType<WorkflowNode>, required: true } },
    computed: {
        hierarchySummary(): string {
            const { assignee } = this.workflowApprovalService.read(this.node);
            if (
                ![WORKFLOW_ASSIGNEE_SOURCES.DEPARTMENT_LEADERS, WORKFLOW_ASSIGNEE_SOURCES.SUPERVISORS].some(
                    (source) => source === assignee.source,
                )
            ) {
                return '';
            }
            return this.$t(
                assignee.config.continuous === true
                    ? 'sw-workflow.approval.hierarchy.throughLevel'
                    : 'sw-workflow.approval.hierarchy.atLevel',
                { level: assignee.config.level ?? 1 },
            );
        },
        source() {
            const settings = this.workflowApprovalService.read(this.node);
            return this.workflowApprovalService.getSource(settings.assignee.source);
        },
    },
});
