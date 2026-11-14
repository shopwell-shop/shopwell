/** @sw-package framework */
import type { PropType } from 'vue';
import type { CanvasContributionHost } from './canvas-contributions.types';
import type {
    CanvasActionPosition,
    CanvasComponentPosition,
    CanvasUiTarget,
} from 'src/core/canvas-editor/canvas-extension.types';
import template from './sw-canvas-contributions.html.twig';

/** @public */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        session: { type: Object as PropType<CanvasContributionHost>, required: true },
        actionPosition: { type: String as PropType<CanvasActionPosition>, default: null },
        componentPosition: { type: String as PropType<CanvasComponentPosition>, default: null },
        target: { type: Object as PropType<CanvasUiTarget>, default: () => ({}) },
    },
    data(): { revision: number; unsubscribe: (() => void) | null } {
        return { revision: 0, unsubscribe: null };
    },
    computed: {
        contributionState() {
            void this.revision;
            return this.session.getState();
        },
        actions() {
            void this.contributionState;
            return this.actionPosition ? this.session.actions(this.actionPosition, this.target) : [];
        },
        components() {
            void this.contributionState;
            return this.componentPosition && this.componentPosition !== 'dialog'
                ? this.session.components(this.componentPosition, this.target)
                : [];
        },
        dialog() {
            void this.contributionState;
            return this.componentPosition === 'dialog' ? this.session.activeDialog : null;
        },
    },
    methods: {
        contributionProps(name: string) {
            const state = this.contributionState;
            return {
                context: this.session.contextForComponent(name),
                target: this.target,
                node: state.document.nodes.find((node) => node.id === this.target.nodeId) ?? null,
                connection: state.document.connections.find((edge) => edge.id === this.target.connectionId) ?? null,
            };
        },
    },
    watch: {
        session: {
            immediate: true,
            handler() {
                this.unsubscribe?.();
                this.unsubscribe = this.session.subscribe('session.changed', () => {
                    this.revision += 1;
                });
                this.revision += 1;
            },
        },
    },
    beforeUnmount() {
        this.unsubscribe?.();
    },
});
