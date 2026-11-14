/** @sw-package framework */
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import zh from '../snippet/zh.json';
import type { WorkflowFormField } from './workflow-form.util';
import ApprovalWorkflowEditingStrategy from '../service/editing/approval-workflow-editing.strategy';
import { createStructuredWorkflowDraft } from './workflow-template.util';

/** @private */
export function createApprovalWorkflow(types: WorkflowNodeTypeSpecification[]): WorkflowDocument {
    const draft = createStructuredWorkflowDraft();
    const approval = types.find((type) => type.name === 'core:approval' && type.version === 1);
    if (approval) draft.nodes[0].config = { inputType: 'core:approval-request' };
    return approval ? new ApprovalWorkflowEditingStrategy().insert(draft, approval, 'start-end', 'approval') : draft;
}

/** Expense approval uses separate terminal nodes to preserve the structured tree policy. @private */
export function createExpenseWorkflow(types: WorkflowNodeTypeSpecification[]): WorkflowDocument {
    if (
        !['core:approval', 'core:condition'].every((name) => types.some((type) => type.name === name && type.version === 1))
    ) {
        return createStructuredWorkflowDraft();
    }
    const labels = zh['sw-workflow'].expense;
    const approval = (id: string, label: string) => ({
        id,
        type: 'core:approval',
        typeVersion: 1,
        config: {
            label,
            assignee: { source: 'core:user', config: { userId: null } },
            approval: { mode: 'core:single', config: {} },
        },
    });
    const edge = (source: string, sourcePort: string, target: string) => ({
        id: `${source}-${sourcePort}-${target}`,
        source,
        sourcePort,
        target,
        targetPort: 'in',
    });
    return {
        schemaVersion: 1,
        policy: 'core:structured',
        nodes: [
            {
                id: 'start',
                type: 'core:start',
                typeVersion: 1,
                config: { inputType: 'core:approval-request', formFields: expenseFields() },
            },
            approval('department', labels.department),
            {
                id: 'amount-check',
                type: 'core:condition',
                typeVersion: 1,
                config: {
                    branches: [
                        {
                            id: 'branch-finance',
                            label: labels.threshold,
                            field: 'request.amount',
                            fieldType: 'amount',
                            operator: 'greaterThan',
                            value: '5000',
                        },
                    ],
                },
            },
            approval('finance', labels.finance),
            ...[
                'department-rejected',
                'finance-rejected',
                'finance-approved',
                'standard-approved',
            ].map((id) => ({
                id,
                type: 'core:end',
                typeVersion: 1,
                config: {},
            })),
        ],
        connections: [
            edge('start', 'out', 'department'),
            edge('department', 'approved', 'amount-check'),
            edge('department', 'rejected', 'department-rejected'),
            edge('amount-check', 'branch-finance', 'finance'),
            edge('amount-check', 'fallback', 'standard-approved'),
            edge('finance', 'approved', 'finance-approved'),
            edge('finance', 'rejected', 'finance-rejected'),
        ],
    };
}

function expenseFields(): WorkflowFormField[] {
    const labels = zh['sw-workflow'].expense;
    return [
        { key: 'reason', label: labels.reason, type: 'text', required: true },
        { key: 'amount', label: labels.amount, type: 'amount', required: true },
        { key: 'expenseDate', label: labels.expenseDate, type: 'date', required: true },
        {
            key: 'category',
            label: labels.category,
            type: 'select',
            required: true,
            options: [
                { value: 'travel', label: labels.travel },
                { value: 'supplies', label: labels.supplies },
            ],
        },
        { key: 'quantity', label: labels.quantity, type: 'number', required: false },
    ];
}
