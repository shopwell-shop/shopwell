/** @sw-package framework */
import { BaseEdge, EdgeLabelRenderer, VueFlow, Handle, Position, MarkerType } from '@vue-flow/core';
import type { Node, Edge, EdgeProps, VueFlowStore } from '@vue-flow/core';
import { markRaw, watch } from 'vue';
import type { PropType } from 'vue';
import type { RuntimeGraph, RuntimePath, RuntimeStage, RuntimeTask } from 'src/core/workflow/workflow-runtime.types';
import { layout } from '../../util/workflow-runtime-layout.util';
import { routeWorkflowConnection } from '../../util/workflow-connection-route.util';
import type { WorkflowNodeSize } from '../../util/workflow-layout-geometry.util';
import type { WorkflowBranchLane } from '../../util/workflow-connection-route.util';

import template from './sw-workflow-runtime-viewer.html.twig';
import './sw-workflow-runtime-viewer.scss';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';

type RuntimeEdgeData = { branch: WorkflowBranchLane };

function approvalDetails(nodeId: string, stages: RuntimeStage[], allTasks: RuntimeTask[]) {
    const stage = stages.find((item) => item.nodeId === nodeId);
    const tasks = stage?.tasks ?? allTasks.filter((task) => task.nodeId === nodeId);
    const round = Math.max(0, ...tasks.map((task) => task.round));
    const currentTasks = tasks.filter((task) => task.round === round);
    const participants = stage?.participants.length
        ? stage.participants
        : [...new Map(currentTasks.map((task) => [task.assignee.id, task.assignee])).values()];
    const states: Record<string, string> = {
        active: 'TODO',
        approved: 'AGREED',
        rejected: 'REJECTED',
        skipped: 'SKIPPED',
        pending: 'PENDING',
    };
    return {
        state: states[stage?.status ?? ''] ?? '',
        mode: stage?.countersignType || currentTasks[0]?.countersignType || '',
        description: stage?.description || '',
        participants: participants.map((participant) => ({
            ...participant,
            state: currentTasks.find((task) => task.assignee.id === participant.id)?.state ?? stage?.status ?? 'pending',
        })),
    };
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    components: { VueFlow, Handle, BaseEdge, EdgeLabelRenderer },
    props: {
        graph: { type: Object as PropType<RuntimeGraph>, required: true },
        selectedPath: { type: Object as PropType<RuntimePath | null>, default: null },
        plannedPath: { type: Object as PropType<RuntimePath | null>, default: null },
        nodeStates: { type: Object as PropType<Record<string, string>>, default: () => ({}) },
        stages: { type: Array as PropType<RuntimeStage[]>, default: () => [] },
        tasks: { type: Array as PropType<RuntimeTask[]>, default: () => [] },
        canExpand: { type: Boolean, default: true },
    },
    data(): {
        instanceId: string;
        position: typeof Position;
        flow: Pick<VueFlowStore, 'fitView' | 'zoomIn' | 'zoomOut'> | null;
        stopSizeWatch: (() => void) | null;
        stopResizeWatch: (() => void) | null;
        nodeSizes: Record<string, WorkflowNodeSize>;
        expanded: boolean;
    } {
        return {
            instanceId: `workflow-runtime-${Shopwell.Utils.createId()}`,
            position: Position,
            flow: null,
            stopSizeWatch: null,
            stopResizeWatch: null,
            nodeSizes: {},
            expanded: false,
        };
    },
    computed: {
        flowNodes(): Node[] {
            return layout(this.graph, { nodeSizes: this.nodeSizes }).nodes.map((node) => {
                const approval = approvalDetails(node.id, this.stages, this.tasks);
                return {
                    id: node.id,
                    type: 'runtime',
                    position: { x: node.x ?? 0, y: node.y ?? 0 },
                    data: {
                        label: node.properties?.name || this.typeLabel(node.type),
                        state: this.nodeStates[node.id] || approval.state,
                        type: node.type,
                        terminal: ['snaker:start', 'snaker:end'].includes(node.type),
                        icon: this.typeIcon(node.type),
                        mode: approval.mode,
                        description: approval.description || node.properties?.description || '',
                        participants: approval.participants,
                        inputPorts: this.graph.edges.filter((edge) => edge.targetNodeId === node.id).map((edge) => edge.id),
                        outputPorts: this.graph.edges.filter((edge) => edge.sourceNodeId === node.id).map((edge) => edge.id),
                    },
                };
            });
        },
        flowEdges(): Edge<RuntimeEdgeData>[] {
            return this.graph.edges.map((edge) => {
                const siblings = this.graph.edges.filter((item) => item.sourceNodeId === edge.sourceNodeId);
                const state = this.edgeState(edge.id);
                return {
                    id: edge.id,
                    source: edge.sourceNodeId,
                    target: edge.targetNodeId,
                    sourceHandle: edge.id,
                    targetHandle: edge.id,
                    type: 'runtime',
                    markerEnd: {
                        type: MarkerType.ArrowClosed,
                        width: 12,
                        height: 12,
                        color: ['is--visited', 'is--planned'].includes(state)
                            ? 'var(--color-border-brand-default)'
                            : 'var(--color-icon-secondary-default)',
                    },
                    data: { branch: { index: siblings.findIndex((item) => item.id === edge.id), count: siblings.length } },
                    label: edge.properties?.name || '',
                    class: state,
                };
            });
        },
        hasFuturePath(): boolean {
            return this.plannedPath?.edgeIds.some((id) => !this.selectedPath?.edgeIds.includes(id)) ?? false;
        },
    },
    beforeUnmount() {
        this.stopSizeWatch?.();
        this.stopResizeWatch?.();
    },
    methods: {
        edgeState(id: string): string {
            if (this.selectedPath?.edgeIds.includes(id)) return 'is--visited';
            if (this.plannedPath?.edgeIds.includes(id)) return 'is--planned';
            return this.plannedPath ? 'is--skipped' : '';
        },
        onInit(flow: VueFlowStore): void {
            this.flow = markRaw({ fitView: flow.fitView, zoomIn: flow.zoomIn, zoomOut: flow.zoomOut });
            this.stopSizeWatch?.();
            this.stopResizeWatch?.();
            this.stopSizeWatch = watch(
                () => flow.getNodes.value.map((node) => [node.id, node.dimensions.width, node.dimensions.height] as const),
                (nodes) => {
                    const sizes = Object.fromEntries(
                        nodes
                            .filter(([, width, height]) => width > 0 && height > 0)
                            .map(([id, width, height]) => [id, { width, height }]),
                    );
                    if (JSON.stringify(sizes) === JSON.stringify(this.nodeSizes)) return;
                    this.nodeSizes = sizes;
                    void this.$nextTick(() => this.fit());
                },
                { flush: 'post', immediate: true },
            );
            this.stopResizeWatch = watch(
                () => [flow.dimensions.value.width, flow.dimensions.value.height],
                () => this.fit(),
                { flush: 'post', immediate: true },
            );
        },
        fit(): void {
            void this.flow?.fitView({ padding: 0.18, maxZoom: 1 });
        },
        zoomIn(): void {
            void this.flow?.zoomIn();
        },
        zoomOut(): void {
            void this.flow?.zoomOut();
        },
        edgeRoute(edge: EdgeProps<RuntimeEdgeData>) {
            return routeWorkflowConnection(
                { x: edge.sourceX, y: edge.sourceY },
                { x: edge.targetX, y: edge.targetY },
                edge.data?.branch,
            );
        },
        typeLabel(type: string): string {
            const labels: Record<string, string> = {
                'snaker:start': 'start',
                'snaker:end': 'end',
                'snaker:condition': 'condition',
                'snaker:task': 'approval',
                'snaker:copy': 'copy',
            };
            return labels[type] ? this.$t(`sw-workflow.runtime.viewer.types.${labels[type]}`) : type;
        },
        typeIcon(type: string): string {
            const icons: Record<string, string> = {
                'snaker:start': 'regular-play',
                'snaker:end': 'regular-checkmark',
                'snaker:condition': 'regular-sitemap',
                'snaker:task': 'regular-users',
                'snaker:copy': 'regular-envelope',
            };
            return icons[type] ?? 'regular-cog';
        },
        stateLabel(state: string): string {
            const labels: Record<string, string> = {
                COMPLETED: 'completed',
                CANCELED: 'canceled',
                TODO: 'active',
                AGREED: 'approved',
                REJECTED: 'rejected',
                SKIPPED: 'skipped',
                PENDING: 'pending',
                VISITED: 'visited',
            };
            return labels[state] ? this.$t(`sw-workflow.runtime.viewer.states.${labels[state]}`) : '';
        },
        participantState(state: string): string {
            return [
                'todo',
                'agreed',
                'rejected',
                'canceled',
                'pending',
                'active',
                'approved',
                'skipped',
            ].includes(state)
                ? this.$t(`sw-workflow.runtime.participants.states.${state}`)
                : state;
        },
        modeLabel(mode: string): string {
            return [
                'single',
                'orSign',
                'countersign',
                'orderSign',
                'firstDecision',
            ].includes(mode)
                ? this.$t(`sw-workflow.runtime.countersign.${mode}`)
                : mode;
        },
    },
});
