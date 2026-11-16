/** @sw-package framework */
import { computed } from 'vue';
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

describe('release action registration lifecycle', () => {
    it('rejects duplicate publishers and safely disposes contributions across reinstall', () => {
        const service = new WorkflowReleaseActionService();
        const dispose = service.register('example:publisher', 'example-start');
        expect(() => service.register('example:publisher', 'another-start')).toThrow(/Duplicate/);
        expect(service.get('example:publisher')).toBe('example-start');
        dispose();
        expect(service.get('example:publisher')).toBeNull();
        const disposeAgain = service.register('example:publisher', 'example-start');
        dispose();
        expect(service.get('example:publisher')).toBe('example-start');
        disposeAgain();
        expect(service.get('example:publisher')).toBeNull();
    });

    it('updates capabilities already consumed by an open view when a registration is removed', () => {
        const service = new WorkflowReleaseActionService();
        const selected = computed(() => service.get('example:dynamic'));
        expect(selected.value).toBeNull();
        const dispose = service.register('example:dynamic', 'example-start');
        expect(selected.value).toBe('example-start');
        dispose();
        expect(selected.value).toBeNull();
    });
});
