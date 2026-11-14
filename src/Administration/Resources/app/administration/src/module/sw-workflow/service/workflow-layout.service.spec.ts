/** @sw-package framework */
import WorkflowLayoutService from './workflow-layout.service';
import { createStructuredWorkflowDraft } from '../util/workflow-template.util';

describe('workflow layout extensions', () => {
    it('registers another layout without changing the graph or canvas adapter', () => {
        const service = new WorkflowLayoutService();
        service.register('example:horizontal', (document) =>
            Object.fromEntries(document.nodes.map((node, index) => [node.id, { x: index * 250, y: 0 }])),
        );
        service.registerPolicyLayout('example:loop', 'example:horizontal');
        const document = createStructuredWorkflowDraft();
        const original = JSON.stringify(document);
        expect(service.get('example:horizontal')(document, []).end).toEqual({ x: 250, y: 0 });
        expect(service.forPolicy('example:loop')(document, []).end).toEqual({ x: 250, y: 0 });
        expect(JSON.stringify(document)).toBe(original);
        expect(() => service.get('missing')).toThrow('Unknown workflow layout');
    });
});
