/** @sw-package framework */
import type { WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import transferTemplate from '../../../../../../../../../tests/integration/Core/System/Payment/Transfer/_fixtures/transfer-workflow-template.json';
import { createTransferWorkflow, transferWorkflowAvailable } from './payment-transfer-workflow.template';

const types: WorkflowNodeTypeSpecification[] = ['core:approval', 'core:condition', 'core:copy'].map((name) => ({
    name,
    version: 1,
    kind: 'activity',
    inputPorts: ['in'],
    outputPorts: [],
}));
const labels = {
    department: 'Department position review',
    finance: 'Finance review',
    reviewRequired: 'Non-CNY or CNY over 5000',
    copy: 'Notify recipients',
    amount: 'Amount',
    currency: 'Currency',
    reason: 'Note',
    recipient: 'Recipient',
    paymentAccount: 'Payment account',
};

describe('transfer approval template', () => {
    it('matches the document exercised through the real backend runtime', () => {
        expect(createTransferWorkflow(labels)).toEqual(transferTemplate);
    });

    it.each(types.map((type) => type.name))(
        'requires the supported %s capability before offering the template',
        (missing) => {
            expect(transferWorkflowAvailable(types)).toBe(true);
            expect(transferWorkflowAvailable(types.filter((type) => type.name !== missing))).toBe(false);
            expect(transferWorkflowAvailable(types.map((type) => ({ ...type, version: 2 })))).toBe(false);
        },
    );

    it('starts with explicit department position selection and preserves the transfer input contract', () => {
        const document = createTransferWorkflow(labels);
        expect(document.nodes.find((node) => node.id === 'start')?.config).toMatchObject({
            requestAction: 'payment:transfer',
            formFields: [
                { key: 'amount', label: 'Amount', type: 'number', required: true },
                { key: 'currency', label: 'Currency', type: 'text', required: true, dataSource: 'core:currency' },
                { key: 'recipient', label: 'Recipient', type: 'text', required: true },
                { key: 'paymentAccount', label: 'Payment account', type: 'text', required: true },
                { key: 'reason', label: 'Note', type: 'text', required: false },
            ],
        });
        expect(document.connections.find((edge) => edge.source === 'start')?.target).toBe('department');
        expect(document.nodes.find((node) => node.id === 'department')?.config).toMatchObject({
            assignee: { source: 'core:positions', config: { positionIds: [] } },
            approval: { mode: 'core:all' },
        });
    });

    it('requires finance for non-CNY transfers or a CNY amount strictly above the threshold', () => {
        const document = createTransferWorkflow(labels);
        expect(document.nodes.find((node) => node.id === 'review-policy')?.config).toEqual({
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
                                { field: 'request.amount', fieldType: 'number', operator: 'greaterThan', value: '5000' },
                            ],
                        },
                    ],
                },
            ],
        });
        expect(document.nodes.find((node) => node.id === 'finance')?.config).toMatchObject({
            assignee: { source: 'core:user', config: { userId: null } },
        });
        expect(document.connections).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ source: 'department', sourcePort: 'approved', target: 'review-policy' }),
                expect.objectContaining({ source: 'review-policy', sourcePort: 'branch-review', target: 'finance' }),
                expect.objectContaining({ source: 'review-policy', sourcePort: 'fallback', target: 'copy-standard' }),
                expect.objectContaining({ source: 'finance', sourcePort: 'approved', target: 'copy-reviewed' }),
            ]),
        );
    });

    it('copies only approved routes and keeps distinct terminals for the structured policy', () => {
        const document = createTransferWorkflow(labels);
        const copies = document.nodes.filter((node) => node.type === 'core:copy');
        expect(copies.map((node) => node.id)).toEqual(['copy-reviewed', 'copy-standard']);
        copies.forEach((node) => expect(node.config).toEqual({ label: labels.copy, userIds: [] }));
        const approvals = document.nodes.filter((node) => node.type === 'core:approval');
        approvals.forEach((node) => {
            const refusal = document.connections.find((edge) => edge.source === node.id && edge.sourcePort === 'rejected');
            expect(document.nodes.find((target) => target.id === refusal?.target)?.type).toBe('core:end');
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
