/** @sw-package framework */
import type { WorkflowDocument, WorkflowNode } from 'src/core/workflow/workflow.types';

/** @private */
export interface WorkflowConditionBranch {
    id: string;
    label: string;
    field: string;
    fieldType: string;
    operator: string;
    value: string;
}

/** @private */
export function conditionBranches(node: WorkflowNode): WorkflowConditionBranch[] {
    const branches: unknown = 'branches' in node.config ? node.config.branches : [];
    if (!Array.isArray(branches)) return [];
    const ids = new Set<string>();
    return branches.flatMap((value: unknown): WorkflowConditionBranch[] => {
        if (
            typeof value !== 'object' ||
            value === null ||
            !('id' in value) ||
            typeof value.id !== 'string' ||
            !/^branch-[a-zA-Z0-9-]+$/.test(value.id) ||
            ids.has(value.id)
        )
            return [];
        ids.add(value.id);
        const branch = value as Record<string, unknown>;
        const operator = branch.operator;
        return [
            {
                ...branch,
                id: value.id,
                label: typeof branch.label === 'string' ? branch.label : '',
                field: typeof branch.field === 'string' ? branch.field : '',
                fieldType: typeof branch.fieldType === 'string' ? branch.fieldType : '',
                operator: typeof operator === 'string' ? operator : 'equals',
                value: typeof branch.value === 'string' ? branch.value : '',
            },
        ];
    });
}

/** Only untouched terminal paths may be removed without discarding user work. @private */
export function emptyConditionBranch(document: WorkflowDocument, nodeId: string, port: string): boolean {
    const edges = document.connections.filter((edge) => edge.source === nodeId && edge.sourcePort === port);
    if (edges.length !== 1) {
        return false
    };
    const end = document.nodes.find((node) => node.id === edges[0].target);
    return (
        !!end &&
        end.type === 'core:end' &&
        end.typeVersion === 1 &&
        Object.entries(end.config).every(([key, value]) => ['label', 'description'].includes(key) && value === '') &&
        document.connections.filter((edge) => edge.target === end.id).length === 1 &&
        !document.connections.some((edge) => edge.source === end.id)
    );
}
