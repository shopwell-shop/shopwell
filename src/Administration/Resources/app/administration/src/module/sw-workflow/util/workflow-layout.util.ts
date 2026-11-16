/** @sw-package framework */
import type { WorkflowDocument, WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import type { WorkflowLayoutContext, WorkflowNodeSize, WorkflowPoint } from 'src/core/workflow/workflow-studio.types';
import {
    WORKFLOW_MINIMUM_SLOT_WIDTH,
    WORKFLOW_NODE_HORIZONTAL_SPACING,
    workflowNodeSize,
    workflowRowGap,
} from './workflow-layout-geometry.util';

type RankedNode = {
    node: WorkflowNode;
    rank: number;
    pendingPredecessors: number;
    successors: RankedNode[];
};

type RowNode = {
    id: string;
    size: WorkflowNodeSize;
    slotWidth: number;
};

type LayoutRow = {
    nodes: RowNode[];
    width: number;
    height: number;
    branchCount: number;
};

/**
 * Places draft nodes in dependency ranks and centers each measured row.
 * Invalid references are ignored for draft rendering; server diagnostics own their errors.
 * Nodes in cycles remain visible in their last assigned rank.
 * @private
 */
export function rankedWorkflowLayout(
    document: WorkflowDocument,
    types: WorkflowNodeTypeSpecification[],
    context: WorkflowLayoutContext = {},
): Record<string, WorkflowPoint> {
    const nodes = indexConnectedNodes(document);
    assignDependencyRanks(nodes);
    const rows = measureRows(nodes, types, context);

    return positionRows(rows);
}

function indexConnectedNodes(document: WorkflowDocument): RankedNode[] {
    const nodes = new Map<string, RankedNode>();

    for (const node of document.nodes) {
        nodes.set(node.id, { node, rank: 0, pendingPredecessors: 0, successors: [] });
    }

    for (const connection of document.connections) {
        const source = nodes.get(connection.source);
        const target = nodes.get(connection.target);

        if (!source || !target) {
            continue;
        }

        source.successors.push(target);
        target.pendingPredecessors += 1;
    }

    return [...nodes.values()];
}

function assignDependencyRanks(nodes: RankedNode[]): void {
    const ready = nodes.filter((node) => node.pendingPredecessors === 0);

    while (ready.length > 0) {
        const current = ready.pop();

        if (!current) {
            break;
        }

        for (const target of current.successors) {
            target.rank = Math.max(target.rank, current.rank + 1);
            target.pendingPredecessors -= 1;

            if (target.pendingPredecessors === 0) {
                ready.push(target);
            }
        }
    }
}

function measureRows(
    nodes: RankedNode[],
    types: WorkflowNodeTypeSpecification[],
    context: WorkflowLayoutContext,
): LayoutRow[] {
    const rows = new Map<number, LayoutRow>();

    for (const { node, rank, successors } of nodes) {
        const size = workflowNodeSize(node, types, context);
        const slotWidth = Math.max(WORKFLOW_MINIMUM_SLOT_WIDTH, size.width + WORKFLOW_NODE_HORIZONTAL_SPACING);
        const row = rows.get(rank) ?? { nodes: [], width: 0, height: 0, branchCount: 0 };

        row.nodes.push({ id: node.id, size, slotWidth });
        row.width += slotWidth;
        row.height = Math.max(row.height, size.height);
        row.branchCount = Math.max(row.branchCount, successors.length);
        rows.set(rank, row);
    }

    return [...rows.entries()].sort(([firstRank], [secondRank]) => firstRank - secondRank).map(([, row]) => row);
}

function positionRows(rows: LayoutRow[]): Record<string, WorkflowPoint> {
    const positions: Record<string, WorkflowPoint> = {};
    let top = 0;

    for (const row of rows) {
        let left = -row.width / 2;

        for (const { id, size, slotWidth } of row.nodes) {
            const slotCenter = left + slotWidth / 2;
            positions[id] = { x: slotCenter - size.width / 2, y: top };
            left += slotWidth;
        }

        top += row.height + workflowRowGap(row.branchCount);
    }

    return positions;
}
