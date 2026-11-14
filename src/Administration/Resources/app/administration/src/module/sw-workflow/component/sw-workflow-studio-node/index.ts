/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowCanvasNodeData } from '../sw-workflow-studio-canvas/workflow-canvas.adapter';
import template from './sw-workflow-studio-node.html.twig';
import './sw-workflow-studio-node.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowNodePresentationService'],
    props: { data: { type: Object as PropType<WorkflowCanvasNodeData>, required: true } },
    computed: {
        presentation() {
            return this.workflowNodePresentationService.get(this.data.nodeType, this.data.typeVersion);
        },
        summary(): string {
            return (
                this.data.description ||
                (this.presentation.summaryKey ? this.$t(this.presentation.summaryKey) : this.data.typeName)
            );
        },
    },
});
