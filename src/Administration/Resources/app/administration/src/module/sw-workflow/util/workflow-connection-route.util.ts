/** @sw-package framework */
import type { WorkflowPoint, WorkflowBranchLane, WorkflowConnectionRoute } from 'src/core/workflow/workflow-studio.types';

/** @public */
export type { WorkflowPoint, WorkflowBranchLane, WorkflowConnectionRoute } from 'src/core/workflow/workflow-studio.types';

function roundedPath(points: WorkflowPoint[]): string {
    let path = `M${points[0].x},${points[0].y}`;
    points.slice(1, -1).forEach((corner, index) => {
        const previous = points[index];
        const next = points[index + 2];
        const before = Math.hypot(corner.x - previous.x, corner.y - previous.y);
        const after = Math.hypot(next.x - corner.x, next.y - corner.y);
        const radius = Math.min(8, before / 2, after / 2);
        const entry = {
            x: corner.x + ((previous.x - corner.x) * radius) / before,
            y: corner.y + ((previous.y - corner.y) * radius) / before,
        };
        const exit = {
            x: corner.x + ((next.x - corner.x) * radius) / after,
            y: corner.y + ((next.y - corner.y) * radius) / after,
        };
        path += `L${entry.x},${entry.y}Q${corner.x},${corner.y} ${exit.x},${exit.y}`;
    });
    const end = points[points.length - 1];
    return `${path}L${end.x},${end.y}`;
}

/** Routes siblings on separate lanes, with labels and insertion controls inside the reserved corridor. @private */
export function routeWorkflowConnection(
    source: WorkflowPoint,
    target: WorkflowPoint,
    branch: WorkflowBranchLane = { index: 0, count: 1 },
): WorkflowConnectionRoute {
    const gap = target.y - source.y;
    const lane = target.x < source.x ? branch.index : branch.count - branch.index - 1;
    const turnY = gap > 0 ? source.y + Math.min(32 + lane * 24, gap - 80) : source.y + 32 + lane * 24;
    const candidates =
        Math.abs(source.x - target.x) < 0.01
            ? [source, target]
            : [
                  source,
                  { x: source.x, y: turnY },
                  { x: target.x, y: turnY },
                  target,
              ];
    const points = candidates.filter(
        (point, index) => index === 0 || point.x !== candidates[index - 1].x || point.y !== candidates[index - 1].y,
    );
    const corridor = gap > 0 ? Math.max(source.y, turnY) : turnY;
    return {
        path: roundedPath(points),
        points,
        label: { x: target.x, y: corridor + 24 },
        insert: { x: target.x, y: gap > 0 ? target.y - 24 : turnY + 56 },
    };
}
