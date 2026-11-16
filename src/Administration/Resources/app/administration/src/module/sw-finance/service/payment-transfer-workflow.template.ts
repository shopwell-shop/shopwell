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
    manager: string;
    foreignReview: string;
    foreignCurrency: string;
    threshold: string;
    amount: string;
    currency: string;
    reason: string;
    recipient: string;
    paymentAccount: string;
};

/** @private */
export function transferWorkflowAvailable(types: WorkflowNodeTypeSpecification[]): boolean {
    return [WORKFLOW_NODE_TYPES.APPROVAL, WORKFLOW_NODE_TYPES.CONDITION].every((name) =>
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

/** Business templates register through the designer service without changing its editing strategies. @private */
export function createTransferWorkflow(labels: TransferLabels): WorkflowDocument {
    return {
        schemaVersion: 1,
        policy: 'core:structured',
        nodes: [
            {
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
            },
            approval('finance', labels.finance),
            {
                id: 'review-policy',
                type: WORKFLOW_NODE_TYPES.CONDITION,
                typeVersion: 1,
                config: {
                    // Foreign currencies always receive another review; no implicit exchange rate is applied.
                    branches: [
                        {
                            id: 'branch-foreign',
                            label: labels.foreignCurrency,
                            field: 'request.currency',
                            fieldType: 'text',
                            operator: 'notEquals',
                            value: 'CNY',
                        },
                        {
                            id: 'branch-large',
                            label: labels.threshold,
                            field: 'request.amount',
                            fieldType: 'number',
                            operator: 'greaterThan',
                            value: '5000',
                        },
                    ],
                },
            },
            approval('foreign-review', labels.foreignReview),
            approval('amount-review', labels.manager),
            ...[
                'finance-rejected',
                'foreign-approved',
                'foreign-rejected',
                'amount-approved',
                'amount-rejected',
                'standard-approved',
            ].map((id) => ({ id, type: WORKFLOW_NODE_TYPES.END, typeVersion: 1, config: {} })),
        ],
        connections: [
            edge('start', WORKFLOW_NODE_PORTS.OUT, 'finance'),
            edge('finance', WORKFLOW_NODE_PORTS.APPROVED, 'review-policy'),
            edge('finance', WORKFLOW_NODE_PORTS.REJECTED, 'finance-rejected'),
            edge('review-policy', 'branch-foreign', 'foreign-review'),
            edge('review-policy', 'branch-large', 'amount-review'),
            edge('review-policy', WORKFLOW_NODE_PORTS.FALLBACK, 'standard-approved'),
            edge('foreign-review', WORKFLOW_NODE_PORTS.APPROVED, 'foreign-approved'),
            edge('foreign-review', WORKFLOW_NODE_PORTS.REJECTED, 'foreign-rejected'),
            edge('amount-review', WORKFLOW_NODE_PORTS.APPROVED, 'amount-approved'),
            edge('amount-review', WORKFLOW_NODE_PORTS.REJECTED, 'amount-rejected'),
        ],
    };
}
