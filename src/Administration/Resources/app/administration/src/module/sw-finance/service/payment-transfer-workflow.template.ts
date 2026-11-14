/** @sw-package framework */
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
    return ['core:approval', 'core:condition'].every((name) =>
        types.some((type) => type.name === name && type.version === 1),
    );
}

const approval = (id: string, label: string): WorkflowNode => ({
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

/** Business templates register through the designer service without changing its editing strategies. @private */
export function createTransferWorkflow(labels: TransferLabels): WorkflowDocument {
    return {
        schemaVersion: 1,
        policy: 'core:structured',
        nodes: [
            {
                id: 'start',
                type: 'core:start',
                typeVersion: 1,
                config: {
                    inputType: 'core:approval-request',
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
                type: 'core:condition',
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
            ].map((id) => ({ id, type: 'core:end', typeVersion: 1, config: {} })),
        ],
        connections: [
            edge('start', 'out', 'finance'),
            edge('finance', 'approved', 'review-policy'),
            edge('finance', 'rejected', 'finance-rejected'),
            edge('review-policy', 'branch-foreign', 'foreign-review'),
            edge('review-policy', 'branch-large', 'amount-review'),
            edge('review-policy', 'fallback', 'standard-approved'),
            edge('foreign-review', 'approved', 'foreign-approved'),
            edge('foreign-review', 'rejected', 'foreign-rejected'),
            edge('amount-review', 'approved', 'amount-approved'),
            edge('amount-review', 'rejected', 'amount-rejected'),
        ],
    };
}
