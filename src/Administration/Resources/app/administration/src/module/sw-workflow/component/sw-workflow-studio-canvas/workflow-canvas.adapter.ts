/** @sw-package framework */
import type { Edge, Node } from '@vue-flow/core';
import type { WorkflowBranchLane } from 'src/core/workflow/workflow-studio.types';
import { rankedWorkflowLayout } from '../../util/workflow-layout.util';
import type {
    WorkflowConnection,
    WorkflowDocument,
    WorkflowNode,
    WorkflowNodeTypeSpecification,
} from 'src/core/workflow/workflow.types';

/** @private */
export interface WorkflowCanvasNodeData {
    node: WorkflowNode;
    label: string;
    nodeType: string;
    typeVersion: number;
    description: string;
    typeName: string;
    kind: string;
    inputPorts: string[];
    outputPorts: string[];
    invalid: boolean;
}

/** @private */
export type WorkflowCanvasEdgeData = { branch: WorkflowBranchLane };

/** @private */
export type WorkflowCanvasGraph = {
    nodes: (Node<WorkflowCanvasNodeData> & { data: WorkflowCanvasNodeData })[];
    edges: Edge<WorkflowCanvasEdgeData>[];
};

function configText(node: WorkflowNode, key: string): string {
    const config = node.config;

    if (Array.isArray(config)) {
        return '';
    }

    return typeof config[key] === 'string' ? config[key] : '';
}

/** @private */
export function toCanvasGraph(
    document: WorkflowDocument,
    types: WorkflowNodeTypeSpecification[],
    labels: Record<string, string>,
    {
        invalidIds = [],
        positions = rankedWorkflowLayout(document, types),
        resolvePorts = (node, type) => type,
    }: {
        invalidIds?: string[];
        positions?: Record<string, { x: number; y: number }>;
        resolvePorts?: (
            node: WorkflowNode,
            type: WorkflowNodeTypeSpecification,
        ) => { inputPorts: string[]; outputPorts: string[]; labels?: Record<string, string> };
    } = {},
): WorkflowCanvasGraph {
    const specifications = new Map(types.map((type) => [`${type.name}@${type.version}`, type]));
    const invalidNodes = new Set(invalidIds);
    const portsByNode = new Map<string, { inputPorts: string[]; outputPorts: string[]; labels?: Record<string, string> }>();
    const outgoing = new Map<string, WorkflowConnection[]>();

    for (const node of document.nodes) {
        const specification = specifications.get(`${node.type}@${node.typeVersion}`);
        portsByNode.set(node.id, specification ? resolvePorts(node, specification) : { inputPorts: [], outputPorts: [] });
    }

    for (const connection of document.connections) {
        const siblings = outgoing.get(connection.source) ?? [];
        siblings.push(connection);
        outgoing.set(connection.source, siblings);
    }

    const lanes = new Map<string, WorkflowBranchLane>();

    for (const [sourceId, siblings] of outgoing) {
        const ports = portsByNode.get(sourceId)?.outputPorts ?? [];
        siblings.sort((first, second) => ports.indexOf(first.sourcePort) - ports.indexOf(second.sourcePort));
        siblings.forEach((connection, index) => lanes.set(connection.id, { index, count: siblings.length }));
    }

    const branchLanes: Record<string, WorkflowBranchLane> = Object.fromEntries(lanes);

    const nodes: WorkflowCanvasGraph['nodes'] = document.nodes.map((node) => {
        const type = specifications.get(`${node.type}@${node.typeVersion}`);
        const ports = portsByNode.get(node.id) ?? { inputPorts: [], outputPorts: [] };
        const label = configText(node, 'label') || labels[node.type] || node.type;

        return {
            id: node.id,
            label,
            type: 'workflow',
            ariaLabel: label,
            position: positions[node.id] ?? { x: 0, y: 0 },
            data: {
                node: JSON.parse(JSON.stringify(node)) as WorkflowNode,
                nodeType: node.type,
                typeVersion: node.typeVersion,
                description: configText(node, 'description'),
                label,
                typeName: labels[node.type] ?? node.type,
                kind: type?.kind ?? 'unknown',
                inputPorts: ports.inputPorts,
                outputPorts: ports.outputPorts,
                invalid: invalidNodes.has(node.id) || !type,
            },
        };
    });

    const edges: WorkflowCanvasGraph['edges'] = document.connections.map((edge) => {
        const sourcePorts = portsByNode.get(edge.source);

        return {
            id: edge.id,
            type: 'workflow',
            source: edge.source,
            target: edge.target,
            sourceHandle: edge.sourcePort,
            targetHandle: edge.targetPort,
            data: { branch: branchLanes[edge.id] },
            label: sourcePorts?.labels?.[edge.sourcePort] ?? labels[edge.sourcePort] ?? '',
        };
    });

    return { nodes, edges };
}
