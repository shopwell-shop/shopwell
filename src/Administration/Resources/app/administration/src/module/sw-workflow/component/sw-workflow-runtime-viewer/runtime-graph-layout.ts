/** @sw-package framework */
import { WORKFLOW_NODE_KINDS } from 'src/core/workflow/workflow-node.constants';
import { WORKFLOW_RUNTIME_NODE_TYPES } from 'src/core/workflow/workflow-runtime.constants';
import type { RuntimeGraph } from 'src/core/workflow/workflow-runtime.types';
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import type { WorkflowLayoutContext } from 'src/core/workflow/workflow-studio.types';
import { structuredWorkflowLayout } from '../../util/structured-workflow-layout.util';

function runtimeNodeKind(type: string): WorkflowNodeTypeSpecification['kind'] {
    switch (type) {
        case WORKFLOW_RUNTIME_NODE_TYPES.START:
            return WORKFLOW_NODE_KINDS.START;
        case WORKFLOW_RUNTIME_NODE_TYPES.END:
            return WORKFLOW_NODE_KINDS.END;
        default:
            return WORKFLOW_NODE_KINDS.ACTIVITY;
    }
}

/** Adapts the authorized execution snapshot to the same geometry used by the designer. @private */
export function layoutRuntimeGraph(graph: RuntimeGraph, context: WorkflowLayoutContext = {}): RuntimeGraph {
    const nodes = Array.isArray(graph.nodes) ? graph.nodes : [];
    const edges = Array.isArray(graph.edges) ? graph.edges : [];
    const document: WorkflowDocument = {
        schemaVersion: 1,
        policy: 'core:structured',
        nodes: nodes.map((node) => ({ id: node.id, type: node.type, typeVersion: 1, config: {} })),
        connections: edges.map((edge) => ({
            id: edge.id,
            source: edge.sourceNodeId,
            sourcePort: edge.id,
            target: edge.targetNodeId,
            targetPort: edge.id,
        })),
    };
    const types: WorkflowNodeTypeSpecification[] = [...new Set(nodes.map((node) => node.type))].map((type) => ({
        name: type,
        version: 1,
        kind: runtimeNodeKind(type),
        inputPorts: [],
        outputPorts: [],
    }));
    // Compiled edge order preserves branch priority; it is also the renderer's handle order.
    const outputPorts = Object.fromEntries(
        nodes.map((node) => [node.id, edges.filter((edge) => edge.sourceNodeId === node.id).map((edge) => edge.id)]),
    );
    const positions = structuredWorkflowLayout(document, types, { ...context, outputPorts });
    return { nodes: nodes.map((node) => ({ ...node, ...positions[node.id] })), edges };
}
