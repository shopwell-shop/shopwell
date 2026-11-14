/** @sw-package framework */
import { createApprovalWorkflow, createExpenseWorkflow } from './approval-workflow-template.util';

describe('approval workflow template', () => {
    it('uses distinct end nodes for approved and rejected outcomes', () => {
        const draft = createApprovalWorkflow([
            {
                name: 'core:approval',
                version: 1,
                kind: 'activity',
                inputPorts: ['in'],
                outputPorts: ['approved', 'rejected'],
            },
        ]);
        const branches = draft.connections.filter((edge) => edge.source === 'approval');
        expect(branches.map((edge) => edge.sourcePort)).toEqual(['approved', 'rejected']);
        expect(new Set(branches.map((edge) => edge.target)).size).toBe(2);
    });
    it('creates an editable empty workflow when the approval capability is unavailable', () => {
        expect(createApprovalWorkflow([]).nodes.map((node) => node.type)).toEqual(['core:start', 'core:end']);
    });
});

describe('expense workflow template', () => {
    it('routes department approval to a finance threshold with distinct end paths and no selected users', () => {
        const document = createExpenseWorkflow(
            ['core:approval', 'core:condition'].map((name) => ({
                name,
                version: 1,
                kind: 'activity',
                inputPorts: ['in'],
                outputPorts: [],
            })),
        );
        expect(document.nodes[0].config).toMatchObject({
            formFields: [
                expect.objectContaining({ key: 'reason', type: 'text', required: true }),
                expect.objectContaining({ key: 'amount', type: 'amount', required: true }),
                expect.objectContaining({ key: 'expenseDate', type: 'date', required: true }),
                expect.objectContaining({ key: 'category', type: 'select', required: true }),
                expect.objectContaining({ key: 'quantity', type: 'number', required: false }),
            ],
        });
        expect(document.nodes.find((node) => node.type === 'core:condition')!.config).toMatchObject({
            branches: [
                expect.objectContaining({
                    field: 'request.amount',
                    fieldType: 'amount',
                    operator: 'greaterThan',
                    value: '5000',
                }),
            ],
        });
        expect(document.connections).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ source: 'department', sourcePort: 'approved', target: 'amount-check' }),
                expect.objectContaining({ source: 'amount-check', sourcePort: 'branch-finance', target: 'finance' }),
                expect.objectContaining({ source: 'amount-check', sourcePort: 'fallback', target: 'standard-approved' }),
            ]),
        );
        const ends = document.nodes.filter((node) => node.type === 'core:end');
        expect(ends).toHaveLength(4);
        ends.forEach((end) => expect(document.connections.filter((edge) => edge.target === end.id)).toHaveLength(1));
        document.nodes
            .filter((node) => node.type === 'core:approval')
            .forEach((node) => {
                expect(node.config).toMatchObject({ assignee: { config: { userId: null } } });
            });
    });
});
