/** @sw-package framework */
import { VueFlow } from '@vue-flow/core';
import type { Edge, Node } from '@vue-flow/core';
import { NodeToolbar } from '@vue-flow/node-toolbar';
import type { PropType } from 'vue';
import useCanvasViewport from 'src/app/composables/use-canvas-viewport';
import template from './sw-canvas-editor.html.twig';
import './sw-canvas-editor.scss';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';

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

    setup(_, { emit }) {
        return useCanvasViewport({ onSizesChange: (sizes) => emit('node-sizes-change', sizes) });
    },

    data(): {
        canvasId: string;
        hoveredNodeId: string | null;
        focusedNodeId: string | null;
        hideNodeToolsTimer: ReturnType<typeof setTimeout> | null;
    } {
        return {
            canvasId: Shopwell.Utils.createId(),
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
    },
});
