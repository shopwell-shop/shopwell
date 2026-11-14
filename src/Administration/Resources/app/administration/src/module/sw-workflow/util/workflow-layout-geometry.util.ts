/** @sw-package framework */
import type { WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

import type { WorkflowNodeSize, WorkflowLayoutContext } from 'src/core/workflow/workflow-studio.types';

/** @public */
export type { WorkflowNodeSize, WorkflowLayoutContext } from 'src/core/workflow/workflow-studio.types';

/** Space for branch labels, an insertion control and separate orthogonal routing lanes. @private */
export function workflowRowGap(branchCount: number): number {
    return 144 + Math.max(0, branchCount - 2) * 24;
}

/** Measured renderer dimensions take precedence over the initial layout estimates. @private */
export function workflowNodeSize(
    node: WorkflowNode,
    types: WorkflowNodeTypeSpecification[],
    context: WorkflowLayoutContext,
): WorkflowNodeSize {
    const measured = context.nodeSizes?.[node.id];
    if (measured?.width && measured.height) {
        return measured;
    }

    const type = types.find((item) => item.name === node.type && item.version === node.typeVersion);
    return type?.kind === 'activity' ? { width: 252, height: 106 } : { width: 160, height: 44 };
}
