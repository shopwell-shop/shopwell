/** @sw-package framework */
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

import type { WorkflowNodeEditingStrategy } from 'src/core/workflow/workflow-studio.types';

/** @public */
export type { WorkflowNodeEditingStrategy } from 'src/core/workflow/workflow-studio.types';

/** Default structured editing; alternative semantics register another strategy. @private */
export default class StructuredWorkflowEditingStrategy implements WorkflowNodeEditingStrategy {
    canInsert(document: WorkflowDocument, type: WorkflowNodeTypeSpecification, connectionId: string): boolean {
        return (
            type.kind === 'activity' &&
            type.inputPorts.length === 1 &&
            type.outputPorts.length > 0 &&
            document.connections.some((edge) => edge.id === connectionId)
        );
    }
    insert(
        document: WorkflowDocument,
        type: WorkflowNodeTypeSpecification,
        connectionId: string,
        id: string,
    ): WorkflowDocument {
        const next = JSON.parse(JSON.stringify(document)) as WorkflowDocument;
        const connection = next.connections.find((item) => item.id === connectionId);
        if (!connection || type.kind !== 'activity' || type.inputPorts.length !== 1 || type.outputPorts.length === 0) {
            throw new Error('The node cannot be inserted on this connection.');
        }

        const nodeIds = [id, ...type.outputPorts.slice(1).map((_, index) => `${id}-end-${index + 1}`)];
        const edgeIds = type.outputPorts.map((_, index) => `${id}-out-${index}`);
        if (
            next.nodes.some((node) => nodeIds.includes(node.id)) ||
            next.connections.some((edge) => edgeIds.includes(edge.id))
        ) {
            throw new Error('Workflow identifiers must be unique.');
        }

        const target = connection.target;
        const targetPort = connection.targetPort;
        connection.target = id;
        connection.targetPort = type.inputPorts[0];
        next.nodes.push({ id, type: type.name, typeVersion: type.version, config: {} });
        type.outputPorts.forEach((port, index) => {
            const endId = index === 0 ? target : `${id}-end-${index}`;
            if (index > 0) {
                next.nodes.push({ id: endId, type: 'core:end', typeVersion: 1, config: {} });
            }
            next.connections.push({
                id: `${id}-out-${index}`,
                source: id,
                sourcePort: port,
                target: endId,
                targetPort: index === 0 ? targetPort : 'in',
            });
        });
        return next;
    }

    canRemove(document: WorkflowDocument, id: string): boolean {
        return (
            document.connections.filter((edge) => edge.target === id).length === 1 &&
            document.connections.filter((edge) => edge.source === id).length === 1
        );
    }

    remove(document: WorkflowDocument, id: string): WorkflowDocument {
        if (!this.canRemove(document, id)) {
            return document;
        }
        const next = JSON.parse(JSON.stringify(document)) as WorkflowDocument;
        const incoming = next.connections.find((edge) => edge.target === id);
        const outgoing = next.connections.find((edge) => edge.source === id);
        if (!incoming || !outgoing) {
            return document;
        }
        incoming.target = outgoing.target;
        incoming.targetPort = outgoing.targetPort;
        next.connections = next.connections.filter((edge) => edge.id !== outgoing.id);
        next.nodes = next.nodes.filter((node) => node.id !== id);
        return next;
    }
}
