/** @sw-package framework */
import type { WorkflowNode } from 'src/core/workflow/workflow.types';

const BRANCH_ID_PATTERN = /^branch-[a-zA-Z0-9-]+$/;

/** Limits apply to each branch, including boolean groups. @private */
export const WORKFLOW_CONDITION_LIMITS = { DEPTH: 6, SIZE: 100 } as const;

/** @private */
export interface WorkflowConditionComparison {
    field: string;
    fieldType: string;
    operator: string;
    value: string;
}

/** @private */
export interface WorkflowConditionGroup {
    operator: 'and' | 'or';
    conditions: WorkflowCondition[];
}

/** @private */
export type WorkflowCondition = WorkflowConditionComparison | WorkflowConditionGroup;

/** @private */
export type WorkflowConditionBranch = WorkflowCondition & { id: string; label: string };

/** @private */
export function isConditionGroup(condition: unknown): condition is WorkflowConditionGroup {
    return (
        typeof condition === 'object' &&
        condition !== null &&
        'operator' in condition &&
        (condition.operator === 'and' || condition.operator === 'or') &&
        'conditions' in condition &&
        Array.isArray(condition.conditions)
    );
}

/** Count draft entries too, so an invalid rule cannot hide the branch's size. @private */
export function countConditionNodes(condition: unknown): number {
    const pending = [condition];
    let count = 0;

    while (pending.length > 0) {
        const current = pending.pop();
        count += 1;

        if (isConditionGroup(current)) {
            pending.push(...current.conditions);
        }
    }

    return count;
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
    const metadata = { id: raw.id, label: typeof branch.label === 'string' ? branch.label : '' };
    if (branch.operator === 'and' || branch.operator === 'or') {
        return {
            ...branch,
            ...metadata,
            operator: branch.operator,
            conditions: Array.isArray(branch.conditions)
                ? (JSON.parse(JSON.stringify(branch.conditions)) as WorkflowCondition[])
                : [],
        };
    }

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
