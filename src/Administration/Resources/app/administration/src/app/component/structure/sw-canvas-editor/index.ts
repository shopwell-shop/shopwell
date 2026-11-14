/** @sw-package framework */
import { BaseEdge, EdgeLabelRenderer, Handle, Position, VueFlow } from '@vue-flow/core';
import { NodeToolbar } from '@vue-flow/node-toolbar';
import type { Connection, EdgeMouseEvent, EdgeProps, NodeChange, NodeMouseEvent, VueFlowStore } from '@vue-flow/core';
import { markRaw, watch } from 'vue';
import type { PropType } from 'vue';
import type {
    CanvasConnectionRoute,
    CanvasConnectionRouter,
    CanvasNodeSize,
    CanvasPort,
    CanvasRenderConnection,
    CanvasRenderGraph,
    CanvasRenderNode,
} from 'src/core/canvas-editor/canvas-renderer.types';

import template from './sw-canvas-editor.html.twig';
import './sw-canvas-editor.scss';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';

type Viewport = Pick<
    VueFlowStore,
    'fitView' | 'zoomIn' | 'zoomOut' | 'applyNodeChanges' | 'applyEdgeChanges' | 'updateNodeInternals'
>;
type RenderEdgeData = { connection: CanvasRenderConnection };

function straightConnection(source: { x: number; y: number }, target: { x: number; y: number }): CanvasConnectionRoute {
    const midpoint = { x: (source.x + target.x) / 2, y: (source.y + target.y) / 2 };

    return { path: `M${source.x},${source.y}L${target.x},${target.y}`, label: midpoint, tools: midpoint };
}

/**
 * Shared graph editor surface. Owners supply the graph projection and handle editing intents.
 * The renderer owns viewport and measurements; it never edits the document or imports business modules.
 * @public
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    components: { VueFlow, Handle, BaseEdge, EdgeLabelRenderer, NodeToolbar },

    props: {
        graph: { type: Object as PropType<CanvasRenderGraph>, required: true },
        allowEdit: { type: Boolean, default: true },
        allowNodeMove: { type: Boolean, default: true },
        allowConnections: { type: Boolean, default: true },
        routeConnection: { type: Function as PropType<CanvasConnectionRouter>, default: straightConnection },
    },

    emits: [
        'select-node',
        'select-connection',
        'move-node',
        'connect',
        'node-contextmenu',
        'connection-contextmenu',
        'canvas-click',
        'canvas-contextmenu',
        'node-sizes-change',
    ],

    data(): {
        canvasId: string;
        viewport: Viewport | null;
        stopMeasurements: (() => void) | null;
        stopResize: (() => void) | null;
        nodeSizes: Record<string, CanvasNodeSize>;
        hoveredNodeId: string | null;
        focusedNodeId: string | null;
        hideNodeToolsTimer: ReturnType<typeof setTimeout> | null;
    } {
        return {
            canvasId: Shopwell.Utils.createId(),
            viewport: null,
            stopMeasurements: null,
            stopResize: null,
            nodeSizes: {},
            hoveredNodeId: null,
            focusedNodeId: null,
            hideNodeToolsTimer: null,
        };
    },

    computed: {
        renderNodes() {
            return this.graph.nodes.map((node) => ({
                id: node.id,
                type: 'canvas',
                position: { ...node.position },
                selected: node.selected ?? false,
                data: { projection: node },
            }));
        },

        renderConnections() {
            return this.graph.connections.map((connection) => ({
                id: connection.id,
                source: connection.source,
                target: connection.target,
                sourceHandle: connection.sourcePort,
                targetHandle: connection.targetPort,
                selected: connection.selected ?? false,
                type: 'canvas',
                data: { connection },
            }));
        },

        portSignature(): string {
            return JSON.stringify(this.graph.nodes.map((node) => [node.id, node.inputPorts, node.outputPorts]));
        },
    },

    watch: {
        portSignature() {
            void this.$nextTick(() => this.viewport?.updateNodeInternals(this.graph.nodes.map((node) => node.id)));
        },

        allowEdit() {
            if (!this.allowEdit) {
                this.resetMovePreview();
            }
        },

        allowNodeMove() {
            if (!this.allowNodeMove) {
                this.resetMovePreview();
            }
        },
    },

    beforeUnmount() {
        this.stopMeasurements?.();
        this.stopResize?.();
        this.clearNodeToolsTimer();
    },

    methods: {
        showNodeTools(nodeId: string): void {
            this.clearNodeToolsTimer();
            this.hoveredNodeId = nodeId;
        },

        scheduleHideNodeTools(nodeId: string): void {
            if (this.hoveredNodeId !== nodeId) {
                return;
            }

            this.clearNodeToolsTimer();
            // The toolbar lives outside the node; allow the pointer to cross the gap.
            this.hideNodeToolsTimer = setTimeout(() => {
                this.hoveredNodeId = null;
                this.hideNodeToolsTimer = null;
            }, 150);
        },

        clearNodeToolsTimer(): void {
            if (this.hideNodeToolsTimer !== null) {
                clearTimeout(this.hideNodeToolsTimer);
                this.hideNodeToolsTimer = null;
            }
        },

        onNodeToolsBlur(event: FocusEvent, nodeId: string): void {
            const target = event.relatedTarget;
            const owner = target instanceof Element ? target.closest('[data-canvas-node-owner]') : null;

            if (owner?.getAttribute('data-canvas-node-owner') === nodeId) {
                return;
            }

            if (this.focusedNodeId === nodeId) {
                this.focusedNodeId = null;
            }
        },

        onNodesChange(changes: NodeChange[]): void {
            if (this.allowEdit && this.allowNodeMove) {
                // Drag positions are a renderer preview until the owner accepts the final intent.
                this.viewport?.applyNodeChanges(changes.filter((change) => change.type === 'position'));
            }

            this.syncSelection();
        },

        onInit(flow: VueFlowStore): void {
            this.stopMeasurements?.();
            this.stopResize?.();
            this.viewport = markRaw({
                fitView: flow.fitView,
                zoomIn: flow.zoomIn,
                zoomOut: flow.zoomOut,
                applyNodeChanges: flow.applyNodeChanges,
                applyEdgeChanges: flow.applyEdgeChanges,
                updateNodeInternals: flow.updateNodeInternals,
            });

            this.stopMeasurements = watch(
                () => flow.getNodes.value.map((node) => [node.id, node.dimensions.width, node.dimensions.height] as const),
                (nodes) => {
                    const sizes: Record<string, CanvasNodeSize> = {};

                    for (const [id, width, height] of nodes) {
                        if (width > 0 && height > 0) {
                            sizes[id] = { width, height };
                        }
                    }

                    if (JSON.stringify(sizes) === JSON.stringify(this.nodeSizes)) {
                        return;
                    }

                    this.nodeSizes = sizes;
                    this.$emit('node-sizes-change', sizes);
                    void this.$nextTick(() => this.fit());
                },
                { immediate: true, flush: 'post' },
            );
            this.stopResize = watch(
                () => [flow.dimensions.value.width, flow.dimensions.value.height],
                () => this.fit(),
                { immediate: true, flush: 'post' },
            );
        },

        syncSelection(): void {
            this.viewport?.applyNodeChanges(
                this.graph.nodes.map((node) => ({ id: node.id, type: 'select', selected: node.selected ?? false })),
            );
            this.viewport?.applyEdgeChanges(
                this.graph.connections.map((connection) => ({
                    id: connection.id,
                    type: 'select',
                    selected: connection.selected ?? false,
                })),
            );
        },

        portPosition(port: CanvasPort, input: boolean): Position {
            const positions = { top: Position.Top, bottom: Position.Bottom, left: Position.Left, right: Position.Right };

            return positions[port.side ?? (input ? 'top' : 'bottom')];
        },

        portStyle(port: CanvasPort, node: CanvasRenderNode, input: boolean): Record<string, string> {
            const side = port.side ?? (input ? 'top' : 'bottom');
            const ports = [
                ...(node.inputPorts ?? []).map((item) => ({ port: item, input: true })),
                ...(node.outputPorts ?? []).map((item) => ({ port: item, input: false })),
            ];
            const siblings = ports.filter((item) => (item.port.side ?? (item.input ? 'top' : 'bottom')) === side);
            const sideIndex = siblings.findIndex((item) => item.input === input && item.port.id === port.id);
            const offset = `${((sideIndex + 1) / (siblings.length + 1)) * 100}%`;

            return side === 'left' || side === 'right' ? { top: offset } : { left: offset };
        },

        edgeRoute(edge: EdgeProps<RenderEdgeData>): CanvasConnectionRoute {
            return this.routeConnection(
                { x: edge.sourceX, y: edge.sourceY },
                { x: edge.targetX, y: edge.targetY },
                edge.data.connection,
            );
        },

        edgeConnection(edge: EdgeProps<RenderEdgeData>): CanvasRenderConnection {
            return edge.data.connection;
        },

        labelStyle(edge: EdgeProps<RenderEdgeData>): Record<string, string> {
            const { label } = this.edgeRoute(edge);

            return { transform: `translate(-50%, -50%) translate(${label.x}px, ${label.y}px)` };
        },

        onNodeClick(event: NodeMouseEvent): void {
            this.$emit('select-node', event.node.id);
        },

        onConnectionClick(event: EdgeMouseEvent): void {
            this.$emit('select-connection', event.edge.id);
        },

        onNodeMove(event: NodeMouseEvent): void {
            if (!this.allowEdit || !this.allowNodeMove) {
                this.resetMovePreview();
                return;
            }

            this.$emit('move-node', { nodeId: event.node.id, position: { ...event.node.position } });
            void this.$nextTick(() => this.resetMovePreview());
        },

        resetMovePreview(): void {
            this.viewport?.applyNodeChanges(
                this.graph.nodes.map((node) => ({
                    id: node.id,
                    type: 'position',
                    position: { ...node.position },
                    from: { ...node.position },
                    dragging: false,
                })),
            );
        },

        onConnectionKeydown(event: KeyboardEvent, connectionId: string): void {
            if (event.target !== event.currentTarget || !['Enter', ' '].includes(event.key)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.$emit('select-connection', connectionId);
        },

        onConnect(connection: Connection): void {
            if (!this.allowEdit || !this.allowConnections) {
                return;
            }

            this.$emit('connect', {
                source: connection.source,
                target: connection.target,
                sourcePort: connection.sourceHandle ?? null,
                targetPort: connection.targetHandle ?? null,
            });
        },

        onNodeContextmenu(event: NodeMouseEvent): void {
            event.event.preventDefault();
            this.$emit('node-contextmenu', { nodeId: event.node.id });
        },

        onConnectionContextmenu(event: EdgeMouseEvent): void {
            event.event.preventDefault();
            this.$emit('connection-contextmenu', { connectionId: event.edge.id });
        },

        onCanvasContextmenu(event: MouseEvent): void {
            event.preventDefault();
            this.$emit('canvas-contextmenu', { x: event.clientX, y: event.clientY });
        },

        fit(): void {
            void this.viewport?.fitView({ padding: 0.3, maxZoom: 1 });
        },

        zoomIn(): void {
            void this.viewport?.zoomIn();
        },

        zoomOut(): void {
            void this.viewport?.zoomOut();
        },
    },
});
