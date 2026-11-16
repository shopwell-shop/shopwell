/** @sw-package framework */
import { BaseEdge, EdgeLabelRenderer, Handle, Position } from '@vue-flow/core';
import type { EdgeMouseEvent, EdgeProps, NodeMouseEvent, VueFlowStore } from '@vue-flow/core';
import { markRaw, watch } from 'vue';
import type { PropType } from 'vue';
import type { WorkflowDocument, WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import { toCanvasGraph } from './workflow-canvas.adapter';
import type { WorkflowCanvasEdgeData } from './workflow-canvas.adapter';
import type { WorkflowConnectionRoute } from 'src/core/workflow/workflow-studio.types';
import type { WorkflowNodeSize } from 'src/core/workflow/workflow-studio.types';
import template from './sw-workflow-studio-canvas.html.twig';
import './sw-workflow-studio-canvas.scss';

// Return the smallest translation that keeps a node inside the visible canvas.
function visibilityOffset(start: number, end: number, extent: number): number {
    const padding = 24;

    if (end - start > extent - 2 * padding || start < padding) {
        return padding - start;
    }

    if (end > extent - padding) {
        return extent - padding - end;
    }

    return 0;
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    components: { BaseEdge, EdgeLabelRenderer, Handle },
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
    ],
    data(): {
        nodeSizes: Record<string, WorkflowNodeSize>;
        viewport: Pick<VueFlowStore, 'updateNodeInternals' | 'applyNodeChanges' | 'applyEdgeChanges'> | null;
        stopVisibilityWatch: (() => void) | null;
        activeConnectionId: string | null;
        hideInsertionTimer: ReturnType<typeof setTimeout> | null;
    } {
        return {
            nodeSizes: {},
            viewport: null,
            stopVisibilityWatch: null,
            activeConnectionId: null,
            hideInsertionTimer: null,
        };
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
                edges: graph.edges.map((edge) => ({ ...edge, selected: edge.id === this.selectedConnectionId })),
            };
        },

        portSignature(): string {
            return JSON.stringify(this.graph.nodes.map((node) => [node.id, node.data.inputPorts, node.data.outputPorts]));
        },
    },
    watch: {
        portSignature() {
            void this.$nextTick(() => this.viewport?.updateNodeInternals(this.graph.nodes.map((node) => node.id)));
        },
    },
    beforeUnmount() {
        this.clearInsertionTimer();
        this.stopVisibilityWatch?.();
    },
    methods: {
        showInsertion(id: string): void {
            this.clearInsertionTimer();
            this.activeConnectionId = id;
        },

        hideInsertion(): void {
            this.clearInsertionTimer();
            // Edge controls are rendered in a portal; keep them reachable across the gap.
            this.hideInsertionTimer = setTimeout(() => {
                this.activeConnectionId = null;
                this.hideInsertionTimer = null;
            }, 150);
        },

        clearInsertionTimer(): void {
            if (this.hideInsertionTimer !== null) {
                clearTimeout(this.hideInsertionTimer);
                this.hideInsertionTimer = null;
            }
        },

        insertNode(id: string, event: MouseEvent): void {
            this.$emit('insert-node', id, event.currentTarget);
        },

        routeConnection(edge: EdgeProps<WorkflowCanvasEdgeData>): WorkflowConnectionRoute {
            const route = this.workflowLayoutService.connectionRouter(this.document.policy, this.layout);
            return route({ x: edge.sourceX, y: edge.sourceY }, { x: edge.targetX, y: edge.targetY }, edge.data.branch);
        },

        insertStyle(edge: EdgeProps<WorkflowCanvasEdgeData>): Record<string, string> {
            const { insert } = this.routeConnection(edge);
            return { transform: `translate(-50%, -50%) translate(${insert.x}px, ${insert.y}px)` };
        },

        labelStyle(edge: EdgeProps<WorkflowCanvasEdgeData>): Record<string, string> {
            const { label } = this.routeConnection(edge);
            return { transform: `translate(-50%, -50%) translate(${label.x}px, ${label.y}px)` };
        },

        portPosition(input: boolean): Position {
            return input ? Position.Top : Position.Bottom;
        },

        portStyle(index: number, count: number): Record<string, string> {
            return { left: `${((index + 1) / (count + 1)) * 100}%` };
        },

        onInit(flow: VueFlowStore): void {
            this.stopVisibilityWatch?.();
            this.stopVisibilityWatch = watch(
                () => [flow.dimensions.value.width, flow.dimensions.value.height, this.selectedNodeId],
                () => this.keepSelectedNodeVisible(flow),
                { flush: 'post' },
            );
            this.viewport = markRaw({
                updateNodeInternals: flow.updateNodeInternals,
                applyNodeChanges: flow.applyNodeChanges,
                applyEdgeChanges: flow.applyEdgeChanges,
            });
        },

        keepSelectedNodeVisible(flow: VueFlowStore): void {
            const node = this.selectedNodeId ? flow.findNode(this.selectedNodeId) : undefined;
            const { width, height } = flow.dimensions.value;

            if (!node || !width || !height || !node.dimensions.width || !node.dimensions.height) {
                return;
            }

            const { x, y, zoom } = flow.viewport.value;
            const left = node.computedPosition.x * zoom + x;
            const top = node.computedPosition.y * zoom + y;
            const offsetX = visibilityOffset(left, left + node.dimensions.width * zoom, width);
            const offsetY = visibilityOffset(top, top + node.dimensions.height * zoom, height);

            if (offsetX !== 0 || offsetY !== 0) {
                void flow.setViewport({ x: x + offsetX, y: y + offsetY, zoom });
            }
        },

        syncSelection(): void {
            this.viewport?.applyNodeChanges(
                this.graph.nodes.map((node) => ({ id: node.id, type: 'select', selected: node.selected ?? false })),
            );
            this.viewport?.applyEdgeChanges(
                this.graph.edges.map((edge) => ({ id: edge.id, type: 'select', selected: edge.selected ?? false })),
            );
        },

        onNodeClick(event: NodeMouseEvent): void {
            this.$emit('select-node', event.node.id);
        },

        onEdgeClick(event: EdgeMouseEvent): void {
            this.$emit('select-connection', event.edge.id);
        },

        onNodeContextmenu(event: NodeMouseEvent): void {
            event.event.preventDefault();
            this.$emit('node-contextmenu', { nodeId: event.node.id }, event.event);
        },

        onEdgeContextmenu(event: EdgeMouseEvent): void {
            event.event.preventDefault();
            this.$emit('connection-contextmenu', { connectionId: event.edge.id }, event.event);
        },

        onConnectionKeydown(event: KeyboardEvent, id: string): void {
            if (event.target !== event.currentTarget || !['Enter', ' '].includes(event.key)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.$emit('select-connection', id);
        },

        onNodeSizesChange(sizes: Record<string, WorkflowNodeSize>): void {
            this.nodeSizes = sizes;
        },
    },
});
