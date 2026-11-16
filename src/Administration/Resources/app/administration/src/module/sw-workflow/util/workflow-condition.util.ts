/** @sw-package framework */
import type { WorkflowNode } from 'src/core/workflow/workflow.types';

const BRANCH_ID_PATTERN = /^branch-[a-zA-Z0-9-]+$/;

/** @private */
export interface WorkflowConditionBranch {
    id: string;
    label: string;
    field: string;
    fieldType: string;
    operator: string;
    value: string;
}

/** Read incomplete draft rules without discarding extension fields or unknown operators. @private */
export function conditionBranches(node: WorkflowNode): WorkflowConditionBranch[] {
    const rawBranches: unknown = 'branches' in node.config ? node.config.branches : [];

    if (!Array.isArray(rawBranches)) {
        return [];
    }

    const branches: WorkflowConditionBranch[] = [];
    const seenIds = new Set<string>();

    for (const raw of rawBranches) {
        const branch = normalizeDraftBranch(raw);

        if (!branch || seenIds.has(branch.id)) {
            continue;
        }

        seenIds.add(branch.id);
        branches.push(branch);
    }

    return branches;
}

function normalizeDraftBranch(raw: unknown): WorkflowConditionBranch | null {
    if (typeof raw !== 'object' || raw === null) {
        return null;
    }

    if (!('id' in raw) || typeof raw.id !== 'string' || !BRANCH_ID_PATTERN.test(raw.id)) {
        return null;
    }

    const branch = raw as Record<string, unknown>;
    return {
        ...branch,
        id: raw.id,
        label: typeof branch.label === 'string' ? branch.label : '',
        field: typeof branch.field === 'string' ? branch.field : '',
        fieldType: typeof branch.fieldType === 'string' ? branch.fieldType : '',
        operator: typeof branch.operator === 'string' ? branch.operator : 'equals',
        value: typeof branch.value === 'string' ? branch.value : '',
    };
}
