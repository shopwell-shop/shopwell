/** @sw-package framework */
import WorkflowReleaseActionService from './workflow-release-action.service';
import './index';

describe('workflow release actions', () => {
    it('starts a generic published workflow through the shared request action', () => {
        expect(Shopwell.Service('workflowReleaseActionService').get('core:workflow')).toBe('sw-workflow-start');
    });

    it('resolves registered publishers and preserves an unavailable fallback', () => {
        const service = new WorkflowReleaseActionService();
        expect(service.get('plugin:removed')).toBeNull();
        service.register('example:approval', 'example-start');
        expect(service.get('example:approval')).toBe('example-start');
        expect(service.get('plugin:removed')).toBeNull();
    });
});
