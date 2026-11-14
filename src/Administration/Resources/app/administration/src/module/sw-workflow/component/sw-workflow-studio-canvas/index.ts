/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowDocument, WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import { toCanvasGraph } from './workflow-canvas.adapter';
import type {
    CanvasConnectionRoute,
    CanvasPoint,
    CanvasRenderConnection,
} from 'src/core/canvas-editor/canvas-renderer.types';
import type { WorkflowNodeSize } from '../../util/workflow-layout-geometry.util';
import template from './sw-workflow-studio-canvas.html.twig';
import './sw-workflow-studio-canvas.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowLayoutService', 'workflowNodePresentationService'],
    props: {
        allowEdit: { type: Boolean, default: true },
        layout: { type: String, default: '' },
        document: { type: Object as PropType<WorkflowDocument>, required: true },
        nodeTypes: { type: Array as PropType<WorkflowNodeTypeSpecification[]>, required: true },
        labels: { type: Object as PropType<Record<string, string>>, required: true },
        invalidIds: { type: Array as PropType<string[]>, default: () => [] },
        selectedNodeId: { type: String, default: null },
        selectedConnectionId: { type: String, default: null },
    },
    emits: [
        'select-node',
        'select-connection',
        'insert-node',
        'node-contextmenu',
        'connection-contextmenu',
        'canvas-click',
        'canvas-contextmenu',
    ],
    data(): { nodeSizes: Record<string, WorkflowNodeSize> } {
        return { nodeSizes: {} };
    },
    computed: {
        graph() {
            const resolvePorts = (node: WorkflowNode, type: WorkflowNodeTypeSpecification) => {
                const presentation = this.workflowNodePresentationService.get(node.type, node.typeVersion);
                const ports = presentation.resolvePorts?.(node, type) ?? type;
                const translatedLabels = Object.fromEntries(
                    Object.entries(presentation.portLabelKeys ?? {}).map(([port, key]) => [port, this.$t(key)]),
                );

                return { ...ports, labels: { ...translatedLabels, ...('labels' in ports ? ports.labels : {}) } };
            };
            const outputPorts = Object.fromEntries(
                this.document.nodes.map((node) => {
                    const type = this.nodeTypes.find((item) => item.name === node.type && item.version === node.typeVersion);
                    return [node.id, type ? resolvePorts(node, type).outputPorts : []];
                }),
            );
            const graph = toCanvasGraph(this.document, this.nodeTypes, this.labels, {
                invalidIds: this.invalidIds,
                resolvePorts,
                positions: (this.layout
                    ? this.workflowLayoutService.get(this.layout)
                    : this.workflowLayoutService.forPolicy(this.document.policy))(this.document, this.nodeTypes, {
                    nodeSizes: this.nodeSizes,
                    outputPorts,
                }),
            });
            return {
                nodes: graph.nodes.map((node) => ({ ...node, selected: node.id === this.selectedNodeId })),
                connections: graph.connections.map((edge) => ({ ...edge, selected: edge.id === this.selectedConnectionId })),
            };
        },
    },
    methods: {
        routeConnection(
            source: CanvasPoint,
            target: CanvasPoint,
            connection: CanvasRenderConnection,
        ): CanvasConnectionRoute {
            const route = this.workflowLayoutService.connectionRouter(this.document.policy, this.layout);
            const branch = connection.data?.branch as { index: number; count: number } | undefined;
            const geometry = route(source, target, branch);

            return { path: geometry.path, label: geometry.label, tools: geometry.insert };
        },

        insertStyle(route: CanvasConnectionRoute): Record<string, string> {
            return { transform: `translate(-50%, -50%) translate(${route.tools.x}px, ${route.tools.y}px)` };
        },

        onNodeSizesChange(sizes: Record<string, WorkflowNodeSize>): void {
            this.nodeSizes = sizes;
        },
    },
});
