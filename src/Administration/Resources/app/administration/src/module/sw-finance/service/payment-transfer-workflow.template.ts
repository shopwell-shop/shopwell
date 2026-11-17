/** @sw-package framework */
import {
    WORKFLOW_NODE_TYPES,
    WORKFLOW_NODE_PORTS,
    WORKFLOW_ASSIGNEE_SOURCES,
    WORKFLOW_APPROVAL_MODES,
    WORKFLOW_INPUT_TYPES,
} from 'src/core/workflow/workflow-node.constants';
import type { WorkflowDocument, WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

type TransferLabels = {
    finance: string;
    department: string;
    reviewRequired: string;
    copy: string;
    amount: string;
    currency: string;
    reason: string;
    recipient: string;
    paymentAccount: string;
};

/** @private */
export function transferWorkflowAvailable(types: WorkflowNodeTypeSpecification[]): boolean {
    return [WORKFLOW_NODE_TYPES.APPROVAL, WORKFLOW_NODE_TYPES.CONDITION, WORKFLOW_NODE_TYPES.COPY].every((name) =>
        types.some((type) => type.name === name && type.version === 1),
    );
}

const approval = (id: string, label: string): WorkflowNode => ({
    id,
    type: WORKFLOW_NODE_TYPES.APPROVAL,
    typeVersion: 1,
    config: {
        label,
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

function transferRequestNode(labels: TransferLabels): WorkflowNode {
    return {
        id: 'start',
        type: WORKFLOW_NODE_TYPES.START,
        typeVersion: 1,
        config: {
            inputType: WORKFLOW_INPUT_TYPES.APPROVAL_REQUEST,
            requestAction: 'payment:transfer',
            formFields: [
                { key: 'amount', label: labels.amount, type: 'number', required: true },
                {
                    key: 'currency',
                    label: labels.currency,
                    type: 'text',
                    required: true,
                    dataSource: 'core:currency',
                },
                { key: 'recipient', label: labels.recipient, type: 'text', required: true },
                { key: 'paymentAccount', label: labels.paymentAccount, type: 'text', required: true },
                { key: 'reason', label: labels.reason, type: 'text', required: false },
            ],
        },
    };
}

/** Business templates register through the designer service without changing its editing strategies. @private */
export function createTransferWorkflow(labels: TransferLabels): WorkflowDocument {
    return {
        schemaVersion: 1,
        policy: 'core:structured',
        nodes: [
            transferRequestNode(labels),
            {
                id: 'department',
                type: WORKFLOW_NODE_TYPES.APPROVAL,
                typeVersion: 1,
                config: {
                    label: labels.department,
                    assignee: { source: WORKFLOW_ASSIGNEE_SOURCES.POSITIONS, config: { positionIds: [] } },
                    approval: { mode: WORKFLOW_APPROVAL_MODES.ALL, config: {} },
                },
            },
            {
                id: 'review-policy',
                type: WORKFLOW_NODE_TYPES.CONDITION,
                typeVersion: 1,
                config: {
                    // Review foreign currencies without applying an implicit exchange rate.
                    branches: [
                        {
                            id: 'branch-review',
                            label: labels.reviewRequired,
                            operator: 'or',
                            conditions: [
                                { field: 'request.currency', fieldType: 'text', operator: 'notEquals', value: 'CNY' },
                                {
                                    operator: 'and',
                                    conditions: [
                                        { field: 'request.currency', fieldType: 'text', operator: 'equals', value: 'CNY' },
                                        {
                                            field: 'request.amount',
                                            fieldType: 'number',
                                            operator: 'greaterThan',
                                            value: '5000',
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            },
            approval('finance', labels.finance),
            ...['copy-reviewed', 'copy-standard'].map((id) => ({
                id,
                type: WORKFLOW_NODE_TYPES.COPY,
                typeVersion: 1,
                config: { label: labels.copy, userIds: [] },
            })),
            ...[
                'department-rejected',
                'finance-rejected',
                'reviewed-approved',
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
            edge('department', WORKFLOW_NODE_PORTS.APPROVED, 'review-policy'),
            edge('department', WORKFLOW_NODE_PORTS.REJECTED, 'department-rejected'),
            edge('review-policy', 'branch-review', 'finance'),
            edge('review-policy', WORKFLOW_NODE_PORTS.FALLBACK, 'copy-standard'),
            edge('finance', WORKFLOW_NODE_PORTS.APPROVED, 'copy-reviewed'),
            edge('finance', WORKFLOW_NODE_PORTS.REJECTED, 'finance-rejected'),
            edge('copy-reviewed', WORKFLOW_NODE_PORTS.OUT, 'reviewed-approved'),
            edge('copy-standard', WORKFLOW_NODE_PORTS.OUT, 'standard-approved'),
        ],
    };
}
