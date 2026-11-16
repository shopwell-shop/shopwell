/** @sw-package framework */
import { WORKFLOW_NODE_TYPES, WORKFLOW_NODE_PORTS } from 'src/core/workflow/workflow-node.constants';
import type {
    WorkflowConnection,
    WorkflowDocument,
    WorkflowNode,
    WorkflowNodeTypeSpecification,
} from 'src/core/workflow/workflow.types';
import StructuredWorkflowEditingStrategy from './structured-workflow-editing.strategy';
import { conditionBranches } from '../../util/workflow-condition.util';
import type { WorkflowConditionBranch } from '../../util/workflow-condition.util';

type BranchChanges = Pick<WorkflowDocument, 'nodes' | 'connections'>;

/** Only untouched, private terminal paths can be deleted without losing user work. @private */
export function canRemoveConditionBranch(document: WorkflowDocument, nodeId: string, port: string): boolean {
    const branchEdges = document.connections.filter((edge) => edge.source === nodeId && edge.sourcePort === port);
    const [branchEdge] = branchEdges;

    if (!branchEdge || branchEdges.length !== 1) {
        return false;
    }

    const terminal = document.nodes.find((node) => node.id === branchEdge.target);

    if (!terminal || terminal.type !== WORKFLOW_NODE_TYPES.END || terminal.typeVersion !== 1) {
        return false;
    }

    const hasEmptyConfiguration = Object.entries(terminal.config).every(
        ([key, value]) => (key === 'label' || key === 'description') && value === '',
    );

    if (!hasEmptyConfiguration) {
        return false;
    }

    const incomingEdges = document.connections.filter((edge) => edge.target === terminal.id);

    if (incomingEdges.length !== 1) {
        return false;
    }

    return !document.connections.some((edge) => edge.source === terminal.id);
}

/** @private */
export default class ConditionWorkflowEditingStrategy extends StructuredWorkflowEditingStrategy {
    insert(
        document: WorkflowDocument,
        type: WorkflowNodeTypeSpecification,
        connectionId: string,
        id: string,
    ): WorkflowDocument {
        // The fallback preserves the existing downstream; conditions get their own terminal paths.
        const next = super.insert(
            document,
            { ...type, outputPorts: [WORKFLOW_NODE_PORTS.FALLBACK, 'branch-1'] },
            connectionId,
            id,
        );
        const node = next.nodes.find((item) => item.id === id);

        if (!node) {
            return document;
        }

        node.config = { branches: [{ id: 'branch-1', label: '', field: '', fieldType: '', operator: 'equals', value: '' }] };
        next.connections = this.orderBranchConnections(next.connections, id, ['branch-1']);
        return next;
    }

    updateConfig(document: WorkflowDocument, nodeId: string, patch: Record<string, unknown>): WorkflowDocument {
        const node = document.nodes.find((item) => item.id === nodeId);

        if (!node) {
            return document;
        }

        const config = { ...(Array.isArray(node.config) ? {} : node.config), ...patch };

        if (!('branches' in patch)) {
            return this.commitConfiguration(document, node, patch, document);
        }

        const branches = conditionBranches({ ...node, config });

        // Draft reads drop invalid or duplicate IDs; an edit must account for every submitted branch.
        if (!Array.isArray(patch.branches) || branches.length !== patch.branches.length || branches.length === 0) {
            return document;
        }

        const changes = this.prepareBranchChanges(document, nodeId, conditionBranches(node), branches);

        if (!changes) {
            return document;
        }

        changes.connections = this.orderBranchConnections(
            changes.connections,
            nodeId,
            branches.map((branch) => branch.id),
        );
        return this.commitConfiguration(document, node, patch, changes);
    }

    private prepareBranchChanges(
        document: WorkflowDocument,
        nodeId: string,
        previous: WorkflowConditionBranch[],
        branches: WorkflowConditionBranch[],
    ): BranchChanges | null {
        const retainedPorts = new Set(branches.map((branch) => branch.id));
        const removedBranches = previous.filter((branch) => !retainedPorts.has(branch.id));

        if (removedBranches.some((branch) => !canRemoveConditionBranch(document, nodeId, branch.id))) {
            return null;
        }

        const removedPorts = new Set(removedBranches.map((branch) => branch.id));
        const removedEdges = document.connections.filter(
            (edge) => edge.source === nodeId && removedPorts.has(edge.sourcePort),
        );
        const removedEndIds = new Set(removedEdges.map((edge) => edge.target));
        const removedEdgeIds = new Set(removedEdges.map((edge) => edge.id));
        const nodes = document.nodes.filter((node) => !removedEndIds.has(node.id));
        const connections = document.connections.filter((edge) => !removedEdgeIds.has(edge.id));
        const previousPorts = new Set(previous.map((branch) => branch.id));
        const addedBranches = branches.filter((branch) => !previousPorts.has(branch.id));

        for (const branch of addedBranches) {
            const id = `${nodeId}-${branch.id}`;

            if (nodes.some((node) => node.id === id) || connections.some((edge) => edge.id === id)) {
                return null;
            }

            nodes.push({ id, type: WORKFLOW_NODE_TYPES.END, typeVersion: 1, config: {} });
            connections.push({
                id,
                source: nodeId,
                sourcePort: branch.id,
                target: id,
                targetPort: WORKFLOW_NODE_PORTS.IN,
            });
        }

        return { nodes, connections };
    }

    private orderBranchConnections(
        connections: WorkflowConnection[],
        nodeId: string,
        branchIds: string[],
    ): WorkflowConnection[] {
        const portOrder = [...branchIds, WORKFLOW_NODE_PORTS.FALLBACK];
        const outgoing = connections.filter((edge) => edge.source === nodeId);
        outgoing.sort((first, second) => portOrder.indexOf(first.sourcePort) - portOrder.indexOf(second.sourcePort));
        return [...connections.filter((edge) => edge.source !== nodeId), ...outgoing];
    }

    private commitConfiguration(
        document: WorkflowDocument,
        node: WorkflowNode,
        patch: Record<string, unknown>,
        changes: BranchChanges,
    ): WorkflowDocument {
        const nodeIndex = changes.nodes.indexOf(node);
        const next = JSON.parse(JSON.stringify({ ...document, ...changes })) as WorkflowDocument;
        const updatedNode = next.nodes[nodeIndex];
        updatedNode.config = { ...(Array.isArray(updatedNode.config) ? {} : updatedNode.config), ...patch };
        return next;
    }

    canRemove(document: WorkflowDocument, id: string): boolean {
        const node = document.nodes.find((item) => item.id === id);
        const branches = node ? conditionBranches(node) : [];
        const outgoing = document.connections.filter((edge) => edge.source === id);

        if (!node || branches.length === 0 || outgoing.length !== branches.length + 1) {
            return false;
        }

        const allowedPorts = new Set([...branches.map((branch) => branch.id), WORKFLOW_NODE_PORTS.FALLBACK]);

        if (outgoing.some((edge) => !allowedPorts.has(edge.sourcePort))) {
            return false;
        }

        const fallbackEdges = outgoing.filter((edge) => edge.sourcePort === WORKFLOW_NODE_PORTS.FALLBACK);

        if (fallbackEdges.length !== 1 || document.connections.filter((edge) => edge.target === id).length !== 1) {
            return false;
        }

        return branches.every((branch) => canRemoveConditionBranch(document, id, branch.id));
    }

    remove(document: WorkflowDocument, id: string): WorkflowDocument {
        if (!this.canRemove(document, id)) {
            return document;
        }

        const next = JSON.parse(JSON.stringify(document)) as WorkflowDocument;
        const incoming = next.connections.find((edge) => edge.target === id);
        const fallback = next.connections.find(
            (edge) => edge.source === id && edge.sourcePort === WORKFLOW_NODE_PORTS.FALLBACK,
        );

        if (!incoming || !fallback) {
            return document;
        }

        incoming.target = fallback.target;
        incoming.targetPort = fallback.targetPort;
        const ends = next.connections
            .filter((edge) => edge.source === id && edge.sourcePort !== WORKFLOW_NODE_PORTS.FALLBACK)
            .map((edge) => edge.target);
        next.nodes = next.nodes.filter((node) => node.id !== id && !ends.includes(node.id));
        next.connections = next.connections.filter((edge) => edge.source !== id);
        return next;
    }
}
