/** @sw-package framework */
import type { CanvasRenderGraph } from 'src/core/canvas-editor/canvas-renderer.types';
import { rankedWorkflowLayout } from '../../util/workflow-layout.util';
import type { WorkflowDocument, WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

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
): CanvasRenderGraph {
    return {
        nodes: document.nodes.map((node) => {
            const type = types.find((item) => item.name === node.type && item.version === node.typeVersion);
            const ports = type ? resolvePorts(node, type) : { inputPorts: [], outputPorts: [] };
            const configuredLabel = configText(node, 'label');
            return {
                id: node.id,
                label: configuredLabel || labels[node.type] || node.type,
                component: 'sw-workflow-studio-node',
                position: positions[node.id] ?? { x: 0, y: 0 },
                inputPorts: ports.inputPorts.map((id) => ({ id })),
                outputPorts: ports.outputPorts.map((id) => ({ id })),
                data: {
                    node: JSON.parse(JSON.stringify(node)) as WorkflowNode,
                    nodeType: node.type,
                    typeVersion: node.typeVersion,
                    description: configText(node, 'description'),
                    label: configuredLabel || labels[node.type] || node.type,
                    typeName: labels[node.type] ?? node.type,
                    kind: type?.kind ?? 'unknown',
                    inputPorts: ports.inputPorts,
                    outputPorts: ports.outputPorts,
                    invalid: invalidIds.includes(node.id) || !type,
                },
            };
        }),
        connections: document.connections.map((edge) => {
            const source = document.nodes.find((node) => node.id === edge.source);
            const type = types.find((item) => item.name === source?.type && item.version === source?.typeVersion);
            const portLabels = source && type ? resolvePorts(source, type).labels : undefined;
            const ports = source && type ? resolvePorts(source, type).outputPorts : [];
            const siblings = document.connections
                .filter((item) => item.source === edge.source)
                .sort((a, b) => ports.indexOf(a.sourcePort) - ports.indexOf(b.sourcePort));
            return {
                id: edge.id,
                source: edge.source,
                data: { branch: { index: siblings.findIndex((item) => item.id === edge.id), count: siblings.length } },
                target: edge.target,
                sourcePort: edge.sourcePort,
                targetPort: edge.targetPort,
                label: portLabels?.[edge.sourcePort] ?? labels[edge.sourcePort] ?? '',
            };
        }),
    };
}
