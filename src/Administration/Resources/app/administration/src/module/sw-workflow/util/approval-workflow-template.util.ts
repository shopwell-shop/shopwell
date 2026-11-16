/** @sw-package framework */
import {
    WORKFLOW_NODE_TYPES,
    WORKFLOW_NODE_PORTS,
    WORKFLOW_ASSIGNEE_SOURCES,
    WORKFLOW_APPROVAL_MODES,
    WORKFLOW_INPUT_TYPES,
} from 'src/core/workflow/workflow-node.constants';
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import type { WorkflowTemplateContext } from 'src/core/workflow/workflow-studio.types';
import type { WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';
import ApprovalWorkflowEditingStrategy from '../service/editing/approval-workflow-editing.strategy';
import { createStructuredWorkflowDraft } from './workflow-template.util';

/** @private */
export function createApprovalWorkflow(
    types: WorkflowNodeTypeSpecification[],
    context: WorkflowTemplateContext,
): WorkflowDocument {
    const draft = createStructuredWorkflowDraft();
    const approval = types.find((type) => type.name === WORKFLOW_NODE_TYPES.APPROVAL && type.version === 1);

    if (approval) {
        draft.nodes[0].config = {
            inputType: WORKFLOW_INPUT_TYPES.APPROVAL_REQUEST,
            formFields: [
                { key: 'reason', label: context.translate('sw-workflow.inputs.reason'), type: 'text', required: false },
            ],
        };
    }

    return approval ? new ApprovalWorkflowEditingStrategy().insert(draft, approval, 'start-end', 'approval') : draft;
}

/** Expense approval uses separate terminal nodes to preserve the structured tree policy. @private */
export function createExpenseWorkflow(
    types: WorkflowNodeTypeSpecification[],
    context: WorkflowTemplateContext,
): WorkflowDocument {
    if (
        !types.some((type) => type.name === WORKFLOW_NODE_TYPES.APPROVAL && type.version === 1) ||
        !types.some((type) => type.name === WORKFLOW_NODE_TYPES.CONDITION && type.version === 1)
    ) {
        return createStructuredWorkflowDraft();
    }

    const label = (key: string) => context.translate(`sw-workflow.expense.${key}`);
    const approval = (id: string, nodeLabel: string) => ({
        id,
        type: WORKFLOW_NODE_TYPES.APPROVAL,
        typeVersion: 1,
        config: {
            label: nodeLabel,
            assignee: { source: WORKFLOW_ASSIGNEE_SOURCES.USER, config: { userId: null } },
            approval: { mode: WORKFLOW_APPROVAL_MODES.SINGLE, config: {} },
        },
    });
    const edge = (source: string, sourcePort: string, target: string) => ({
        id: `${source}-${sourcePort}-${target}`,
        source,
        sourcePort,
        target,
        targetPort: WORKFLOW_NODE_PORTS.IN,
    });
    return {
        schemaVersion: 1,
        policy: 'core:structured',
        nodes: [
            {
                id: 'start',
                type: WORKFLOW_NODE_TYPES.START,
                typeVersion: 1,
                config: { inputType: WORKFLOW_INPUT_TYPES.APPROVAL_REQUEST, formFields: expenseFields(context) },
            },
            approval('department', label('department')),
            {
                id: 'amount-check',
                type: WORKFLOW_NODE_TYPES.CONDITION,
                typeVersion: 1,
                config: {
                    branches: [
                        {
                            id: 'branch-finance',
                            label: label('threshold'),
                            field: 'request.amount',
                            fieldType: 'amount',
                            operator: 'greaterThan',
                            value: '5000',
                        },
                    ],
                },
            },
            approval('finance', label('finance')),
            ...[
                'department-rejected',
                'finance-rejected',
                'finance-approved',
                'standard-approved',
            ].map((id) => ({
                id,
                type: WORKFLOW_NODE_TYPES.END,
                typeVersion: 1,
                config: {},
            })),
        ],
        connections: [
            edge('start', WORKFLOW_NODE_PORTS.OUT, 'department'),
            edge('department', WORKFLOW_NODE_PORTS.APPROVED, 'amount-check'),
            edge('department', WORKFLOW_NODE_PORTS.REJECTED, 'department-rejected'),
            edge('amount-check', 'branch-finance', 'finance'),
            edge('amount-check', WORKFLOW_NODE_PORTS.FALLBACK, 'standard-approved'),
            edge('finance', WORKFLOW_NODE_PORTS.APPROVED, 'finance-approved'),
            edge('finance', WORKFLOW_NODE_PORTS.REJECTED, 'finance-rejected'),
        ],
    };
}

function expenseFields(context: WorkflowTemplateContext): WorkflowFormField[] {
    const label = (key: string) => context.translate(`sw-workflow.expense.${key}`);
    return [
        { key: 'reason', label: label('reason'), type: 'text', required: true },
        { key: 'amount', label: label('amount'), type: 'amount', required: true },
        { key: 'expenseDate', label: label('expenseDate'), type: 'date', required: true },
        {
            key: 'category',
            label: label('category'),
            type: 'select',
            required: true,
            options: [
                { value: 'travel', label: label('travel') },
                { value: 'supplies', label: label('supplies') },
            ],
        },
        { key: 'quantity', label: label('quantity'), type: 'number', required: false },
    ];
}
