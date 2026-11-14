/** @sw-package framework */
import type { WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import { createTransferWorkflow, transferWorkflowAvailable } from './payment-transfer-workflow.template';

const types: WorkflowNodeTypeSpecification[] = ['core:approval', 'core:condition'].map((name) => ({
    name,
    version: 1,
    kind: 'activity',
    inputPorts: ['in'],
    outputPorts: [],
}));
const labels = {
    finance: 'Finance',
    manager: 'Amount review',
    foreignReview: 'Foreign currency review',
    foreignCurrency: 'Non-CNY',
    threshold: 'CNY over 5000',
    amount: 'Amount',
    currency: 'Currency',
    reason: 'Note',
    recipient: 'Recipient',
    paymentAccount: 'Payment account',
};

describe('transfer approval template', () => {
    it('requires both supported node capabilities before offering the template', () => {
        expect(transferWorkflowAvailable(types)).toBe(true);
        expect(transferWorkflowAvailable(types.slice(0, 1))).toBe(false);
        expect(transferWorkflowAvailable(types.map((type) => ({ ...type, version: 2 })))).toBe(false);
    });

    it('routes every transfer through finance, and foreign currency through review before testing the CNY threshold', () => {
        const document = createTransferWorkflow(labels);
        expect(document.nodes.find((node) => node.id === 'start')?.config).toMatchObject({
            formFields: [
                { key: 'amount', label: 'Amount', type: 'number', required: true },
                { key: 'currency', label: 'Currency', type: 'text', required: true, dataSource: 'core:currency' },
                { key: 'recipient', label: 'Recipient', type: 'text', required: true },
                { key: 'paymentAccount', label: 'Payment account', type: 'text', required: true },
                { key: 'reason', label: 'Note', type: 'text', required: false },
            ],
        });
        expect(document.connections.find((edge) => edge.source === 'start')?.target).toBe('finance');
        expect(document.nodes.find((node) => node.id === 'review-policy')?.config).toMatchObject({
            branches: [
                { id: 'branch-foreign', field: 'request.currency', fieldType: 'text', operator: 'notEquals', value: 'CNY' },
                { id: 'branch-large', field: 'request.amount', fieldType: 'number', operator: 'greaterThan', value: '5000' },
            ],
        });
        expect(document.connections).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ source: 'finance', sourcePort: 'approved', target: 'review-policy' }),
                expect.objectContaining({ source: 'review-policy', sourcePort: 'branch-foreign', target: 'foreign-review' }),
                expect.objectContaining({ source: 'review-policy', sourcePort: 'branch-large', target: 'amount-review' }),
                expect.objectContaining({ source: 'review-policy', sourcePort: 'fallback', target: 'standard-approved' }),
            ]),
        );
    });

    it('requires explicit reviewers and gives each decision a distinct terminal for the structured policy', () => {
        const document = createTransferWorkflow(labels);
        const approvals = document.nodes.filter((node) => node.type === 'core:approval');
        expect(approvals).toHaveLength(3);
        approvals.forEach((node) => {
            expect(node.config).toMatchObject({ assignee: { config: { userId: null } } });
            expect(document.connections.filter((edge) => edge.source === node.id).map((edge) => edge.sourcePort)).toEqual([
                'approved',
                'rejected',
            ]);
        });
        document.nodes
            .filter((node) => node.id !== 'start')
            .forEach((node) => {
                expect(document.connections.filter((edge) => edge.target === node.id)).toHaveLength(1);
            });
        const second = createTransferWorkflow(labels);
        document.nodes[0].config = {};
        expect(second.nodes[0].config).toHaveProperty('formFields');
    });
});
