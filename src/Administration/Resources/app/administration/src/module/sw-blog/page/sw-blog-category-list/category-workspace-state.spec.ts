/**
 * @sw-package discovery
 */
import { createCategoryWorkspaceState, hasCategoryDraftChanges } from './category-workspace-state';

describe('Category workspace state', () => {
    it('keeps staged assignments and navigation isolated across workspace instances', () => {
        const first = createCategoryWorkspaceState('first', 'Care');
        const second = createCategoryWorkspaceState(null, '');
        first.addedEntries.push({ id: 'entry' } as EntitySchema.Entity<'blog'>);
        first.pendingNavigation = jest.fn();
        expect(second.addedEntries).toEqual([]);
        expect(second.pendingNavigation).toBeNull();
        expect(first.selectedId).toBe('first');
        expect(second.selectedId).toBeNull();
    });

    it('ignores new entity defaults but protects meaningful edits', () => {
        const draft = { name: '', slug: '', active: true };
        const changed = (fields: Partial<typeof draft> & Record<string, unknown> = {}) =>
            hasCategoryDraftChanges({ ...draft, ...fields } as EntitySchema.Entity<'blog_category'>);
        expect(changed()).toBe(false);
        expect(changed({ active: false })).toBe(true);
        expect(changed({ description: 'Introduction' })).toBe(true);
        expect(changed({ metaTitle: 'Search title' })).toBe(true);
        expect(changed({ contentLayoutId: 'layout' })).toBe(true);
    });
});
