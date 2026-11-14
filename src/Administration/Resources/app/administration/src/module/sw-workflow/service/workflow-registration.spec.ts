/** @sw-package framework */
import { registerWorkflowContribution } from './workflow-registration';

describe('workflow contribution ownership', () => {
    it('rejects unnamespaced registrations before changing the registry', () => {
        const registry = new Map<string, object>();

        expect(() => registerWorkflowContribution(registry, 'unscoped', {})).toThrow(/namespace/);
        expect(registry.size).toBe(0);
    });

    it('rejects collisions while preserving the first contribution', () => {
        const registry = new Map<string, object>();
        const first = { label: 'First' };
        registerWorkflowContribution(registry, 'example:tool', first);

        expect(() => registerWorkflowContribution(registry, 'example:tool', { label: 'Second' })).toThrow(/Duplicate/);
        expect(registry.get('example:tool')).toBe(first);
    });

    it('keeps a later registration intact when an earlier disposer runs again', () => {
        const registry = new Map<string, object>();
        const contribution = { label: 'Shared definition' };
        const removeFirst = registerWorkflowContribution(registry, 'example:tool', contribution);
        removeFirst();
        const removeSecond = registerWorkflowContribution(registry, 'example:tool', contribution);

        removeFirst();
        expect(registry.get('example:tool')).toBe(contribution);

        removeSecond();
        expect(registry.has('example:tool')).toBe(false);
    });

    it('does not remove a contribution that another owner replaced', () => {
        const registry = new Map<string, object>();
        const remove = registerWorkflowContribution(registry, 'example:tool', { label: 'Original' });
        const replacement = { label: 'Replacement' };
        registry.set('example:tool', replacement);

        remove();

        expect(registry.get('example:tool')).toBe(replacement);
    });
});
