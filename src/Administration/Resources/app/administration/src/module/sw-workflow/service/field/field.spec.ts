/** @sw-package framework */
import workflowRequestFieldProvider from './workflow-request-field.provider';
import approvalWorkflowFieldProvider from './approval-workflow-field.provider';
import { createStructuredWorkflowDraft } from '../../util/workflow-template.util';
import { createApprovalWorkflow } from '../../util/approval-workflow-template.util';

const condition = { id: 'condition', type: 'core:condition', typeVersion: 1, config: {} };

describe('workflow request inputs', () => {
    it('only supplies approval inputs when the template declares that input contract', () => {
        expect(workflowRequestFieldProvider(createStructuredWorkflowDraft(), condition)).toEqual([]);
        const document = createApprovalWorkflow([
            {
                name: 'core:approval',
                version: 1,
                kind: 'activity',
                inputPorts: ['in'],
                outputPorts: ['approved', 'rejected'],
            },
        ]);
        const fields = approvalWorkflowFieldProvider(document, condition);
        expect(fields.map((field) => field.path)).toEqual(['request.reason']);
        document.nodes[0].config = {
            inputType: 'core:approval-request',
            formFields: [
                { key: 'amount', label: 'Amount', type: 'amount', required: true },
                {
                    key: 'category',
                    label: 'Category',
                    type: 'select',
                    required: true,
                    options: [{ value: 'travel', label: 'Travel' }],
                },
            ],
        };
        expect(workflowRequestFieldProvider(document, condition)).toEqual([
            { path: 'request.amount', label: 'Amount', type: 'amount' },
            { path: 'request.category', label: 'Category', type: 'select', options: [{ value: 'travel', label: 'Travel' }] },
        ]);
        document.nodes[0].config = { inputType: 'core:approval-request', formFields: [] };
        expect(workflowRequestFieldProvider(document, condition)).toEqual([]);
        expect(workflowRequestFieldProvider(createApprovalWorkflow([]), condition)).toEqual([]);
    });
    it('does not infer inputs from an approval node or unsupported start node versions', () => {
        const document = createStructuredWorkflowDraft();
        document.nodes[0].config = { inputType: 'core:approval-request' };
        document.nodes[0].typeVersion = 2;
        expect(workflowRequestFieldProvider(document, condition)).toEqual([]);
        document.nodes[0].type = 'core:approval';
        document.nodes[0].typeVersion = 1;
        expect(workflowRequestFieldProvider(document, condition)).toEqual([]);
    });
});

describe('workflow extension scenarios', () => {
    it('provides explicitly declared fields for an automatic workflow', () => {
        const document = createStructuredWorkflowDraft();
        document.nodes[0].config = { formFields: [{ key: 'orderId', label: 'Order', type: 'text', required: true }] };

        expect(workflowRequestFieldProvider(document, condition)).toEqual([
            { path: 'request.orderId', label: 'Order', type: 'text' },
        ]);
    });
});
