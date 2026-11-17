/** @sw-package framework */
import en from '../snippet/en.json';
import zh from '../snippet/zh.json';
import { createApprovalWorkflow } from './approval-workflow-template.util';

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

describe('template authoring language', () => {
    const types = ['core:approval', 'core:condition'].map((name) => ({
        name,
        version: 1,
        kind: 'activity' as const,
        inputPorts: ['in'],
        outputPorts: ['approved', 'rejected'],
    }));
    it.each([en, zh])('initializes authored labels from the current translation context', (snippets) => {
        const translate = (key: string) =>
            key === 'sw-workflow.inputs.reason' ? snippets['sw-workflow'].inputs.reason : key;
        const approval = createApprovalWorkflow(types, { translate });
        expect(approval.nodes[0].config).toMatchObject({
            formFields: [{ key: 'reason', label: snippets['sw-workflow'].inputs.reason, type: 'text', required: false }],
        });
    });
});
