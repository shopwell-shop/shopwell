/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowDocument, WorkflowNode } from 'src/core/workflow/workflow.types';
import template from './sw-workflow-studio-node-settings.html.twig';
import './sw-workflow-studio-node-settings.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowNodePresentationService'],
    props: {
        document: { type: Object as PropType<WorkflowDocument>, required: true },
        node: { type: Object as PropType<WorkflowNode | null>, default: null },
        typeLabel: { type: String, default: '' },
        allowEdit: { type: Boolean, default: true },
        canRemove: { type: Boolean, default: false },
    },
    emits: ['update-config', 'remove'],
    data() {
        return { label: '', description: '' };
    },
    computed: {
        presentation() {
            return this.node ? this.workflowNodePresentationService.get(this.node.type, this.node.typeVersion) : null;
        },
    },
    watch: {
        node: {
            immediate: true,
            handler(node: WorkflowNode | null) {
                const config = node?.config ?? {};
                this.label = 'label' in config && typeof config.label === 'string' ? config.label : '';
                this.description =
                    'description' in config && typeof config.description === 'string' ? config.description : '';
            },
        },
    },
    methods: {
        updateConfig(patch: Record<string, unknown>): void {
            if (this.allowEdit) {
                this.$emit('update-config', patch);
            }
        },
        commit(): void {
            this.updateConfig({ label: this.label, description: this.description });
        },
    },
});
