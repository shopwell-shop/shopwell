/** @sw-package framework */
import {
    WORKFLOW_RUNTIME_NODE_TYPES,
    WORKFLOW_TASK_STATES,
    WORKFLOW_COUNTERSIGN_TYPES,
} from 'src/core/workflow/workflow-runtime.constants';
import { BaseEdge, EdgeLabelRenderer, VueFlow, Handle, Position, MarkerType } from '@vue-flow/core';
import type { Node, Edge, EdgeProps } from '@vue-flow/core';
import type { PropType } from 'vue';
import useCanvasViewport from 'src/app/composables/use-canvas-viewport';
import type { RuntimeGraph, RuntimePath, RuntimeStage, RuntimeTask } from 'src/core/workflow/workflow-runtime.types';
import { layoutRuntimeGraph } from './runtime-graph-layout';
import { routeWorkflowConnection } from '../../util/workflow-connection-route.util';
import type { WorkflowBranchLane } from 'src/core/workflow/workflow-studio.types';

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
            requiredReviewer: currentTasks.some((task) => task.assignee.id === participant.id && task.requiredReviewer),
            state: currentTasks.find((task) => task.assignee.id === participant.id)?.state ?? stage?.status ?? 'pending',
        })),
    };
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    components: { VueFlow, Handle, BaseEdge, EdgeLabelRenderer },
    setup() {
        return useCanvasViewport({ padding: 0.18 });
    },
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
        expanded: boolean;
    } {
        return {
            instanceId: `workflow-runtime-${Shopwell.Utils.createId()}`,
            position: Position,
            expanded: false,
        };
    },
    computed: {
        flowNodes(): Node[] {
            return layoutRuntimeGraph(this.graph, { nodeSizes: this.nodeSizes }).nodes.map((node) => {
                const approval = approvalDetails(node.id, this.stages, this.tasks);
                return {
                    id: node.id,
                    type: 'runtime',
                    position: { x: node.x ?? 0, y: node.y ?? 0 },
                    data: {
                        label: node.properties?.name || this.typeLabel(node.type),
                        state: this.nodeStates[node.id] || approval.state,
                        type: node.type,
                        terminal:
                            node.type === WORKFLOW_RUNTIME_NODE_TYPES.START || node.type === WORKFLOW_RUNTIME_NODE_TYPES.END,
                        condition: node.type === WORKFLOW_RUNTIME_NODE_TYPES.CONDITION,
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
    methods: {
        edgeState(id: string): string {
            if (this.selectedPath?.edgeIds.includes(id)) {
                return 'is--visited';
            }

            if (this.plannedPath?.edgeIds.includes(id)) {
                return 'is--planned';
            }

            return this.plannedPath ? 'is--skipped' : '';
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
                [WORKFLOW_RUNTIME_NODE_TYPES.START]: 'start',
                [WORKFLOW_RUNTIME_NODE_TYPES.END]: 'end',
                [WORKFLOW_RUNTIME_NODE_TYPES.CONDITION]: 'condition',
                [WORKFLOW_RUNTIME_NODE_TYPES.TASK]: 'approval',
                [WORKFLOW_RUNTIME_NODE_TYPES.COPY]: 'copy',
            };
            return labels[type] ? this.$t(`sw-workflow.runtime.viewer.types.${labels[type]}`) : type;
        },
        typeIcon(type: string): string {
            const icons: Record<string, string> = {
                [WORKFLOW_RUNTIME_NODE_TYPES.START]: 'regular-play',
                [WORKFLOW_RUNTIME_NODE_TYPES.END]: 'regular-checkmark',
                [WORKFLOW_RUNTIME_NODE_TYPES.CONDITION]: 'regular-sitemap',
                [WORKFLOW_RUNTIME_NODE_TYPES.TASK]: 'regular-users',
                [WORKFLOW_RUNTIME_NODE_TYPES.COPY]: 'regular-envelope',
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
        participantState(state: string, requiredReviewer = false): string {
            const knownState = [
                ...Object.values(WORKFLOW_TASK_STATES),
                'pending',
                'active',
                'approved',
                'skipped',
            ].includes(state);
            const label = knownState ? this.$t(`sw-workflow.runtime.participants.states.${state}`) : state;

            return requiredReviewer
                ? this.$t('sw-workflow.runtime.participants.addedReviewerState', { state: label })
                : label;
        },
        modeLabel(mode: string): string {
            return [
                WORKFLOW_COUNTERSIGN_TYPES.SINGLE,
                WORKFLOW_COUNTERSIGN_TYPES.OR_SIGN,
                WORKFLOW_COUNTERSIGN_TYPES.COUNTERSIGN,
                WORKFLOW_COUNTERSIGN_TYPES.ORDER_SIGN,
                WORKFLOW_COUNTERSIGN_TYPES.FIRST_DECISION,
            ].some((knownMode) => knownMode === mode)
                ? this.$t(`sw-workflow.runtime.countersign.${mode}`)
                : mode;
        },
    },
});
