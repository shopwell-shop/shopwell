/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowCanvasNodeData } from '../sw-workflow-studio-canvas/workflow-canvas.adapter';
import template from './sw-workflow-approval-node.html.twig';
import './sw-workflow-approval-node.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowApprovalService'],
    props: { data: { type: Object as PropType<WorkflowCanvasNodeData>, required: true } },
    computed: {
        settings() {
            return this.workflowApprovalService.read(this.data.node);
        },
        source() {
            return this.workflowApprovalService.getSource(this.settings.assignee.source);
        },
        mode() {
            return this.workflowApprovalService.getMode(this.settings.approval.mode);
        },
    },
});
