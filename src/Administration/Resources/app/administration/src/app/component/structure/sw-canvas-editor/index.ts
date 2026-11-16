/** @sw-package framework */
import { VueFlow } from '@vue-flow/core';
import type { Dimensions, Edge, Node, VueFlowStore } from '@vue-flow/core';
import { NodeToolbar } from '@vue-flow/node-toolbar';
import { markRaw, watch } from 'vue';
import type { PropType } from 'vue';
import template from './sw-canvas-editor.html.twig';
import './sw-canvas-editor.scss';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';

type Viewport = Pick<VueFlowStore, 'fitView' | 'zoomIn' | 'zoomOut'>;

/** A Shopwell editor shell using native Vue Flow renderers, slots and events. @public */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    components: { VueFlow, NodeToolbar },

    props: {
        nodes: {
            type: Array as PropType<Node[]>,
            required: true,
        },
        edges: {
            type: Array as PropType<Edge[]>,
            default: () => [],
        },
    },

    emits: ['node-sizes-change'],

    data(): {
        canvasId: string;
        viewport: Viewport | null;
        stopMeasurements: (() => void) | null;
        hasFitted: boolean;
        nodeSizes: Record<string, Dimensions>;
        hoveredNodeId: string | null;
        focusedNodeId: string | null;
        hideNodeToolsTimer: ReturnType<typeof setTimeout> | null;
    } {
        return {
            canvasId: Shopwell.Utils.createId(),
            viewport: null,
            stopMeasurements: null,
            hasFitted: false,
            nodeSizes: {},
            hoveredNodeId: null,
            focusedNodeId: null,
            hideNodeToolsTimer: null,
        };
    },

    computed: {
        flowAttributes() {
            return Object.fromEntries(Object.entries(this.$attrs).filter(([name]) => name !== 'class' && name !== 'style'));
        },
    },

    beforeUnmount() {
        this.stopMeasurements?.();
        this.clearNodeToolsTimer();
    },

    methods: {
        rendererSlots(): string[] {
            return Object.keys(this.$slots).filter((name) => {
                if (name === 'node-tools') {
                    return false;
                }

                return (
                    name.startsWith('node-') ||
                    name.startsWith('edge-') ||
                    name === 'connection-line' ||
                    name === 'zoom-pane'
                );
            });
        },

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

        nodeOwner(target: EventTarget | null): string | null {
            if (!(target instanceof Element)) {
                return null;
            }

            const owner = target.closest('[data-canvas-node-owner], .vue-flow__node');
            return owner?.getAttribute('data-canvas-node-owner') ?? owner?.getAttribute('data-id') ?? null;
        },

        onFocusIn(event: FocusEvent): void {
            this.focusedNodeId = this.nodeOwner(event.target);
        },

        onFocusOut(event: FocusEvent): void {
            this.focusedNodeId = this.nodeOwner(event.relatedTarget);
        },

        onInit(flow: VueFlowStore): void {
            this.stopMeasurements?.();
            this.hasFitted = false;
            this.viewport = markRaw({ fitView: flow.fitView, zoomIn: flow.zoomIn, zoomOut: flow.zoomOut });
            this.stopMeasurements = watch(
                () => ({
                    nodes: flow.getNodes.value.map(
                        (node) => [node.id, node.dimensions.width, node.dimensions.height] as const,
                    ),
                    width: flow.dimensions.value.width,
                    height: flow.dimensions.value.height,
                }),
                ({ nodes, width, height }) => {
                    const sizes: Record<string, Dimensions> = {};

                    for (const [id, nodeWidth, nodeHeight] of nodes) {
                        if (nodeWidth > 0 && nodeHeight > 0) {
                            sizes[id] = { width: nodeWidth, height: nodeHeight };
                        }
                    }

                    if (JSON.stringify(sizes) !== JSON.stringify(this.nodeSizes)) {
                        this.nodeSizes = sizes;
                        this.$emit('node-sizes-change', sizes);
                    }

                    if (
                        !this.hasFitted &&
                        width > 0 &&
                        height > 0 &&
                        nodes.length > 0 &&
                        Object.keys(sizes).length === nodes.length
                    ) {
                        void this.$nextTick(() => this.fit());
                    }
                },
                { immediate: true, flush: 'post' },
            );
        },

        fit(): void {
            this.hasFitted = true;
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
