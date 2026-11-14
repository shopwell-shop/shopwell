/** @sw-package framework */
import type { WorkflowDocument, WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import type { WorkflowLayoutContext, WorkflowNodeSize, WorkflowPoint } from 'src/core/workflow/workflow-studio.types';
import { workflowNodeSize, workflowRowGap } from './workflow-layout-geometry.util';

type RankedNode = { node: WorkflowNode; rank: number; incoming: number; successors: RankedNode[] };
type RowNode = { ranked: RankedNode; size: WorkflowNodeSize; slotWidth: number };

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
    const nodes = new Map<string, RankedNode>();

    for (const node of document.nodes) {
        nodes.set(node.id, { node, rank: 0, incoming: 0, successors: [] });
    }

    for (const connection of document.connections) {
        const source = nodes.get(connection.source);
        const target = nodes.get(connection.target);

        if (!source || !target) {
            continue;
        }

        source.successors.push(target);
        target.incoming += 1;
    }

    const ready = [...nodes.values()].filter((node) => node.incoming === 0);

    while (ready.length > 0) {
        const current = ready.pop();

        if (!current) {
            break;
        }

        for (const target of current.successors) {
            target.rank = Math.max(target.rank, current.rank + 1);
            target.incoming -= 1;

            if (target.incoming === 0) {
                ready.push(target);
            }
        }
    }

    const rows = new Map<number, RowNode[]>();

    for (const ranked of nodes.values()) {
        const size = workflowNodeSize(ranked.node, types, context);
        const row = rows.get(ranked.rank) ?? [];
        row.push({ ranked, size, slotWidth: Math.max(320, size.width + 64) });
        rows.set(ranked.rank, row);
    }

    return positionRows(rows);
}

function positionRows(rows: Map<number, RowNode[]>): Record<string, WorkflowPoint> {
    const positions: Record<string, WorkflowPoint> = {};
    let top = 0;

    for (const [, row] of [...rows.entries()].sort(([first], [second]) => first - second)) {
        const rowWidth = row.reduce((width, item) => width + item.slotWidth, 0);
        let left = -rowWidth / 2;
        let rowHeight = 0;
        let branchCount = 0;

        for (const { ranked, size, slotWidth } of row) {
            const slotCenter = left + slotWidth / 2;
            positions[ranked.node.id] = { x: slotCenter - size.width / 2, y: top };
            left += slotWidth;
            rowHeight = Math.max(rowHeight, size.height);
            branchCount = Math.max(branchCount, ranked.successors.length);
        }

        top += rowHeight + workflowRowGap(branchCount);
    }

    return positions;
}
