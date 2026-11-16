/** @sw-package framework */
import { WORKFLOW_NODE_KINDS } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

import type { WorkflowNodeSize, WorkflowLayoutContext } from 'src/core/workflow/workflow-studio.types';

/** @private */
export const WORKFLOW_MINIMUM_SLOT_WIDTH = 320;

/** @private */
export const WORKFLOW_NODE_HORIZONTAL_SPACING = 64;

const MINIMUM_ROW_GAP = 144;
const ADDITIONAL_BRANCH_LANE_GAP = 24;

/** Space for branch labels, an insertion control and separate orthogonal routing lanes. @private */
export function workflowRowGap(branchCount: number): number {
    const additionalBranchLanes = Math.max(0, branchCount - 2);
    return MINIMUM_ROW_GAP + additionalBranchLanes * ADDITIONAL_BRANCH_LANE_GAP;
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
    return type?.kind === WORKFLOW_NODE_KINDS.ACTIVITY ? { width: 252, height: 106 } : { width: 160, height: 44 };
}
