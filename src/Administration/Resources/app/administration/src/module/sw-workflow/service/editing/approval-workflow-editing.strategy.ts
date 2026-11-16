/** @sw-package framework */
import {
    WORKFLOW_NODE_TYPES,
    WORKFLOW_NODE_PORTS,
    WORKFLOW_ASSIGNEE_SOURCES,
    WORKFLOW_APPROVAL_MODES,
} from 'src/core/workflow/workflow-node.constants';
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import StructuredWorkflowEditingStrategy from './structured-workflow-editing.strategy';

function approvedPathReturnsToParent(document: WorkflowDocument, approvedTarget: string, parentId: string): boolean {
    const outgoingTargets = new Map<string, string[]>();
    for (const connection of document.connections) {
        const targets = outgoingTargets.get(connection.source) ?? [];
        targets.push(connection.target);
        outgoingTargets.set(connection.source, targets);
    }

    const pending = [approvedTarget];
    const visited = new Set<string>();
    while (pending.length > 0) {
        const nodeId = pending.pop();

        if (nodeId === undefined) {
            break;
        }

        if (nodeId === parentId) {
            return true;
        }

        if (visited.has(nodeId)) {
            continue;
        }

        visited.add(nodeId);
        pending.push(...(outgoingTargets.get(nodeId) ?? []));
    }
    return false;
}

function hasPrivateEmptyRejectionEnd(document: WorkflowDocument, rejectedTarget: string): boolean {
    const rejectionEnd = document.nodes.find((item) => item.id === rejectedTarget);
    return (
        rejectionEnd?.type === WORKFLOW_NODE_TYPES.END &&
        rejectionEnd.typeVersion === 1 &&
        Object.keys(rejectionEnd.config).length === 0 &&
        document.connections.filter((edge) => edge.target === rejectionEnd.id).length === 1 &&
        !document.connections.some((edge) => edge.source === rejectionEnd.id)
    );
}

/** @private */
export default class ApprovalWorkflowEditingStrategy extends StructuredWorkflowEditingStrategy {
    insert(
        document: WorkflowDocument,
        type: WorkflowNodeTypeSpecification,
        connectionId: string,
        id: string,
    ): WorkflowDocument {
        const next = super.insert(document, type, connectionId, id);
        const node = next.nodes.find((item) => item.id === id);

        if (node) {
            node.config = {
                assignee: { source: WORKFLOW_ASSIGNEE_SOURCES.USER, config: { userId: null } },
                approval: { mode: WORKFLOW_APPROVAL_MODES.SINGLE, config: {} },
            };
        }

        return next;
    }

    canRemove(document: WorkflowDocument, id: string): boolean {
        const node = document.nodes.find((item) => item.id === id);
        const incoming = document.connections.filter((edge) => edge.target === id);
        const outgoing = document.connections.filter((edge) => edge.source === id);
        const approved = outgoing.find((edge) => edge.sourcePort === WORKFLOW_NODE_PORTS.APPROVED);
        const rejected = outgoing.find((edge) => edge.sourcePort === WORKFLOW_NODE_PORTS.REJECTED);

        if (
            node?.type !== WORKFLOW_NODE_TYPES.APPROVAL ||
            incoming.length !== 1 ||
            outgoing.length !== 2 ||
            !approved ||
            !rejected
        ) {
            return false;
        }

        if (
            approved.target === id ||
            approved.target === rejected.target ||
            !document.nodes.some((item) => item.id === approved.target) ||
            approvedPathReturnsToParent(document, approved.target, incoming[0].source)
        ) {
            return false;
        }

        return hasPrivateEmptyRejectionEnd(document, rejected.target);
    }

    remove(document: WorkflowDocument, id: string): WorkflowDocument {
        const approved = document.connections.find(
            (edge) => edge.source === id && edge.sourcePort === WORKFLOW_NODE_PORTS.APPROVED,
        );
        const rejected = document.connections.find(
            (edge) => edge.source === id && edge.sourcePort === WORKFLOW_NODE_PORTS.REJECTED,
        );

        if (!approved || !rejected || !this.canRemove(document, id)) {
            return document;
        }

        const next = JSON.parse(JSON.stringify(document)) as WorkflowDocument;
        next.connections = next.connections
            .filter((edge) => edge.source !== id)
            .map((edge) =>
                edge.target === id ? { ...edge, target: approved.target, targetPort: approved.targetPort } : edge,
            );
        next.nodes = next.nodes.filter((node) => node.id !== id && node.id !== rejected.target);
        return next;
    }
}
