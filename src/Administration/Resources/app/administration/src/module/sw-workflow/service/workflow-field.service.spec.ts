/** @sw-package framework */
import WorkflowFieldService from './workflow-field.service';
import { createStructuredWorkflowDraft } from '../util/workflow-template.util';

const condition = { id: 'condition', type: 'core:condition', typeVersion: 1, config: {} };

describe('workflow field providers', () => {
    it('resolves business fields from the current document and node without changing the editor', () => {
        const service = new WorkflowFieldService();
        const document = createStructuredWorkflowDraft();
        const provider = jest.fn((draft, node) =>
            draft === document && node === condition
                ? [{ path: 'custom.reference', type: 'text' as const, label: 'Reference' }]
                : [],
        );
        service.register('custom:request', provider);
        expect(service.getFields(document, condition)).toEqual([
            { path: 'custom.reference', type: 'text', label: 'Reference' },
        ]);
        expect(provider).toHaveBeenCalledWith(document, condition);
        expect(service.getFields(createStructuredWorkflowDraft(), condition)).toEqual([]);
    });

    it('rejects ambiguous field paths from different providers', () => {
        const service = new WorkflowFieldService();
        const field = { path: 'request.reason', type: 'text' as const, label: 'Reason' };
        service.register('example:one', () => [field]);
        service.register('example:two', () => [field]);
        expect(() => service.getFields(createStructuredWorkflowDraft(), condition)).toThrow('request.reason');
    });
});
