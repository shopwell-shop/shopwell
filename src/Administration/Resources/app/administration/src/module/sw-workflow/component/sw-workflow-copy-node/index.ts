/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowCanvasNodeData } from '../sw-workflow-studio-canvas/workflow-canvas.adapter';
import template from './sw-workflow-copy-node.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    props: {
        data: { type: Object as PropType<WorkflowCanvasNodeData>, required: true },
    },

    computed: {
        hasRecipients(): boolean {
            const configuration = this.data.node.config;

            if (Array.isArray(configuration)) {
                return false;
            }

            return Array.isArray(configuration.userIds) && configuration.userIds.length > 0;
        },
    },
});
