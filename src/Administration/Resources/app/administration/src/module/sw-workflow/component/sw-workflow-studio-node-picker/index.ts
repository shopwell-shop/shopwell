/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowDocument, WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import template from './sw-workflow-studio-node-picker.html.twig';
import './sw-workflow-studio-node-picker.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowNodePresentationService', 'workflowNodeEditingService'],
    props: {
        document: { type: Object as PropType<WorkflowDocument>, required: true },
        nodeTypes: { type: Array as PropType<WorkflowNodeTypeSpecification[]>, required: true },
        labels: { type: Object as PropType<Record<string, string>>, required: true },
        selectedConnectionId: { type: String, default: null },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['insert'],
    data() {
        return { search: '' };
    },
    computed: {
        activities(): WorkflowNodeTypeSpecification[] {
            return this.nodeTypes.filter(
                (type) =>
                    this.workflowNodeEditingService
                        .get(type.name, type.version)
                        .canInsert(this.document, type, this.selectedConnectionId ?? '') &&
                    (this.labels[type.name] ?? type.name).toLowerCase().includes(this.search.toLowerCase()),
            );
        },
        insertionLabel(): string {
            const edge = this.document.connections.find((item) => item.id === this.selectedConnectionId);
            const node = this.document.nodes.find((item) => item.id === edge?.source);
            return node ? this.nodeLabel(node) : '';
        },
    },
    methods: {
        nodeLabel(node: WorkflowNode): string {
            return 'label' in node.config && typeof node.config.label === 'string' && node.config.label
                ? node.config.label
                : (this.labels[node.type] ?? node.type);
        },
        icon(name: string, version: number): string {
            return this.workflowNodePresentationService.get(name, version).icon;
        },
    },
});
