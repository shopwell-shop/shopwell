/** @sw-package framework */
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import StructuredWorkflowEditingStrategy from './structured-workflow-editing.strategy';
import { conditionBranches, emptyConditionBranch } from '../../util/workflow-condition.util';

/** @private */
export default class ConditionWorkflowEditingStrategy extends StructuredWorkflowEditingStrategy {
    insert(
        document: WorkflowDocument,
        type: WorkflowNodeTypeSpecification,
        connectionId: string,
        id: string,
    ): WorkflowDocument {
        // The fallback preserves the existing downstream; conditions get their own terminal paths.
        const next = super.insert(document, { ...type, outputPorts: ['fallback', 'branch-1'] }, connectionId, id);
        const node = next.nodes.find((item) => item.id === id);
        if (!node) return document;
        node.config = { branches: [{ id: 'branch-1', label: '', field: '', fieldType: '', operator: 'equals', value: '' }] };
        const outgoing = next.connections.filter((edge) => edge.source === id);
        next.connections = [...next.connections.filter((edge) => edge.source !== id), outgoing[1], outgoing[0]];
        return next;
    }

    updateConfig(document: WorkflowDocument, nodeId: string, patch: Record<string, unknown>): WorkflowDocument {
        const next = JSON.parse(JSON.stringify(document)) as WorkflowDocument;
        const node = next.nodes.find((item) => item.id === nodeId);
        if (!node) return document;
        const previous = conditionBranches(node);
        node.config = { ...(Array.isArray(node.config) ? {} : node.config), ...patch };
        if (!('branches' in patch)) return next;
        const branches = conditionBranches(node);
        const ids = branches.map((branch) => branch.id);
        if (
            !Array.isArray(patch.branches) ||
            branches.length !== patch.branches.length ||
            !ids.length ||
            new Set(ids).size !== ids.length ||
            ids.some((id) => !/^branch-[a-zA-Z0-9-]+$/.test(id))
        )
            return document;
        const removed = previous.filter((branch) => !ids.includes(branch.id));
        if (removed.some((branch) => !emptyConditionBranch(document, nodeId, branch.id))) return document;
        removed.forEach((branch) => {
            const edge = next.connections.find((item) => item.source === nodeId && item.sourcePort === branch.id);
            if (!edge) return;
            next.nodes = next.nodes.filter((item) => item.id !== edge.target);
            next.connections = next.connections.filter((item) => item.id !== edge.id);
        });
        for (const branch of branches.filter((item) => !previous.some((old) => old.id === item.id))) {
            const id = `${nodeId}-${branch.id}`;
            if (next.nodes.some((item) => item.id === id) || next.connections.some((edge) => edge.id === id))
                return document;
            next.nodes.push({ id, type: 'core:end', typeVersion: 1, config: {} });
            next.connections.push({ id, source: nodeId, sourcePort: branch.id, target: id, targetPort: 'in' });
        }
        const order = [...ids, 'fallback'];
        const outgoing = next.connections
            .filter((edge) => edge.source === nodeId)
            .sort((a, b) => order.indexOf(a.sourcePort) - order.indexOf(b.sourcePort));
        next.connections = [...next.connections.filter((edge) => edge.source !== nodeId), ...outgoing];
        return next;
    }

    canRemove(document: WorkflowDocument, id: string): boolean {
        const node = document.nodes.find((item) => item.id === id);
        const branches = node ? conditionBranches(node) : [];
        const outgoing = document.connections.filter((edge) => edge.source === id);
        return (
            !!node &&
            branches.length > 0 &&
            outgoing.length === branches.length + 1 &&
            outgoing.every(
                (edge) => edge.sourcePort === 'fallback' || branches.some((branch) => branch.id === edge.sourcePort),
            ) &&
            document.connections.filter((edge) => edge.target === id).length === 1 &&
            document.connections.filter((edge) => edge.source === id && edge.sourcePort === 'fallback').length === 1 &&
            conditionBranches(node).every((branch) => emptyConditionBranch(document, id, branch.id))
        );
    }

    remove(document: WorkflowDocument, id: string): WorkflowDocument {
        if (!this.canRemove(document, id)) return document;
        const next = JSON.parse(JSON.stringify(document)) as WorkflowDocument;
        const incoming = next.connections.find((edge) => edge.target === id);
        const fallback = next.connections.find((edge) => edge.source === id && edge.sourcePort === 'fallback');
        if (!incoming || !fallback) return document;
        incoming.target = fallback.target;
        incoming.targetPort = fallback.targetPort;
        const ends = next.connections
            .filter((edge) => edge.source === id && edge.sourcePort !== 'fallback')
            .map((edge) => edge.target);
        next.nodes = next.nodes.filter((node) => node.id !== id && !ends.includes(node.id));
        next.connections = next.connections.filter((edge) => edge.source !== id);
        return next;
    }
}
