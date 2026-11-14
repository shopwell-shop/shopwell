/** @sw-package framework */
import WorkflowNodePresentationService from './workflow-node-presentation.service';

describe('WorkflowNodePresentationService', () => {
    it('resolves plugin renderers by type and version with a safe unknown-type fallback', () => {
        const service = new WorkflowNodePresentationService();
        const presentation = {
            icon: 'regular-flow',
            tone: 'blue' as const,
            nodeComponent: 'example-node',
            settingsComponent: 'example-settings',
        };
        service.register('example:task', 2, presentation);
        expect(service.get('example:task', 2)).toEqual(presentation);
        expect(service.get('example:task', 1)).toEqual({ icon: 'regular-flow', tone: 'neutral' });
    });
});

describe('presentation registration ownership', () => {
    it('removes only the owned presentation and rejects accidental replacement', () => {
        const service = new WorkflowNodePresentationService();
        const presentation = { icon: 'regular-cog', tone: 'blue' as const };
        const unregister = service.register('example:automatic', 1, presentation);

        expect(() => service.register('example:automatic', 1, presentation)).toThrow('Duplicate');
        unregister();
        expect(service.get('example:automatic', 1).icon).toBe('regular-flow');

        const removeReplacement = service.register('example:automatic', 1, presentation);
        unregister();
        expect(service.get('example:automatic', 1).icon).toBe('regular-cog');
        removeReplacement();
    });
});
