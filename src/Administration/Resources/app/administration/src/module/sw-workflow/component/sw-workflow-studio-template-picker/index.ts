/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import template from './sw-workflow-studio-template-picker.html.twig';
import './sw-workflow-studio-template-picker.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowTemplateService'],
    props: {
        nodeTypes: { type: Array as PropType<WorkflowNodeTypeSpecification[]>, required: true },
        labels: { type: Object as PropType<Record<string, string>>, required: true },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['apply', 'close'],
    data() {
        return { selectedName: '' };
    },
    computed: {
        templates() {
            return this.workflowTemplateService.all().filter((preset) => preset.available(this.nodeTypes));
        },
        selected() {
            return this.templates.find((preset) => preset.name === this.selectedName) ?? null;
        },
        preview() {
            return this.selected?.create(this.nodeTypes) ?? null;
        },
    },
});
