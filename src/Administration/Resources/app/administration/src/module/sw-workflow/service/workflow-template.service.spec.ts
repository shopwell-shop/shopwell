/** @sw-package framework */
import WorkflowTemplateService from './workflow-template.service';
import { createWorkflowDraft } from 'src/core/workflow/workflow-document';

describe('workflow template extensions', () => {
    it('registers an independent template and preserves its graph policy', () => {
        const service = new WorkflowTemplateService();
        service.register({
            name: 'example:retry',
            titleKey: 'example.title',
            descriptionKey: 'example.description',
            icon: 'regular-flow',
            available: () => true,
            create: () => ({ ...createWorkflowDraft(), policy: 'example:retry' }),
        });
        const template = service.get('example:retry');
        expect(service.all()).toHaveLength(1);
        expect(template?.create([], { translate: (key) => key }).policy).toBe('example:retry');
    });
});
