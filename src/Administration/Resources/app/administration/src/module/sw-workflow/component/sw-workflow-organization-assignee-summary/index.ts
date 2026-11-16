/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import template from './sw-workflow-organization-assignee-summary.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowApprovalService'],
    props: { node: { type: Object as PropType<WorkflowNode>, required: true } },
    computed: {
        source() {
            const settings = this.workflowApprovalService.read(this.node);
            return this.workflowApprovalService.getSource(settings.assignee.source);
        },
    },
});
