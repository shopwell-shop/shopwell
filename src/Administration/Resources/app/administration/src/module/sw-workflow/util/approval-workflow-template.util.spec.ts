/** @sw-package framework */
import en from '../snippet/en.json';
import zh from '../snippet/zh.json';
import { createApprovalWorkflow, createExpenseWorkflow } from './approval-workflow-template.util';

describe('approval workflow template', () => {
    it('uses distinct end nodes for approved and rejected outcomes', () => {
        const draft = createApprovalWorkflow(
            [
                {
                    name: 'core:approval',
                    version: 1,
                    kind: 'activity',
                    inputPorts: ['in'],
                    outputPorts: ['approved', 'rejected'],
                },
            ],
            { translate: (key) => key },
        );
        const branches = draft.connections.filter((edge) => edge.source === 'approval');
        expect(branches.map((edge) => edge.sourcePort)).toEqual(['approved', 'rejected']);
        expect(new Set(branches.map((edge) => edge.target)).size).toBe(2);
    });
    it('creates an editable empty workflow when the approval capability is unavailable', () => {
        expect(createApprovalWorkflow([], { translate: (key) => key }).nodes.map((node) => node.type)).toEqual([
            'core:start',
            'core:end',
        ]);
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
            { translate: (key) => key },
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

describe('template authoring language', () => {
    const types = ['core:approval', 'core:condition'].map((name) => ({
        name,
        version: 1,
        kind: 'activity' as const,
        inputPorts: ['in'],
        outputPorts: ['approved', 'rejected'],
    }));
    it.each([en, zh])('initializes authored labels from the current translation context', (snippets) => {
        const labels: Record<string, string> = Object.fromEntries(
            Object.entries(snippets['sw-workflow'].expense).map(([key, value]) => [`sw-workflow.expense.${key}`, value]),
        );
        labels['sw-workflow.inputs.reason'] = snippets['sw-workflow'].inputs.reason;
        const translate = (key: string) => labels[key];
        const document = createExpenseWorkflow(types, { translate });
        expect(document.nodes.find((node) => node.id === 'department')!.config).toMatchObject({
            label: snippets['sw-workflow'].expense.department,
        });
        expect(document.nodes[0].config).toHaveProperty(
            'formFields',
            expect.arrayContaining([
                expect.objectContaining({ key: 'reason', label: snippets['sw-workflow'].expense.reason }),
            ]),
        );
        const approval = createApprovalWorkflow(types, { translate });
        expect(approval.nodes[0].config).toMatchObject({
            formFields: [{ key: 'reason', label: snippets['sw-workflow'].inputs.reason, type: 'text', required: false }],
        });
    });
});
