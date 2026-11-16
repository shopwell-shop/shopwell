/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import template from './sw-workflow-organization-assignee-settings.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: { node: { type: Object as PropType<WorkflowNode>, required: true } },
});
