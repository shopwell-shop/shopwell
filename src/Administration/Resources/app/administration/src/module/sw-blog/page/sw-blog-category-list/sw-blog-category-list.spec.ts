/**
 * @sw-package discovery
 */
import { flushPromises } from '@vue/test-utils';
import { category, collection, createWrapper, cleanupWrapper } from './sw-blog-category-list.spec/create-wrapper';

describe('src/module/sw-blog/page/sw-blog-category-list', () => {
    afterEach(cleanupWrapper);

    it('loads the category and the layout it holds', async () => {
        const { wrapper, getCategory } = await createWrapper();
        await flushPromises();

        const criteria = (getCategory.mock.calls as unknown as Array<[string, unknown, { ids: string[] }]>)[0][2];

        expect(criteria.ids).toEqual(['category-1']);
        expect((wrapper.get('.sw-blog-category-list__name').element as HTMLInputElement).value).toBe('Guides');
        await wrapper.get('[data-tab="layout"]').trigger('click');
        expect(wrapper.get('.sw-blog-category-list__layout').text()).toBe('page-1');
        expect(wrapper.find('.sw-blog-category-list__loading').exists()).toBe(false);
    });

    it('filters the tree from the page search and preserves the term when selecting a category', async () => {
        const { wrapper, push, getCategory } = await createWrapper();
        await wrapper.get('.category-search').setValue('Care');
        expect(wrapper.findComponent({ name: 'sw-blog-category-navigation' }).props('searchTerm')).toBe('Care');
        await wrapper.get('.select-category').trigger('click');
        expect(getCategory).toHaveBeenLastCalledWith('category-2', Shopwell.Context.api, expect.anything());
        expect(push).not.toHaveBeenCalled();
        expect(wrapper.findComponent({ name: 'sw-blog-category-navigation' }).props('searchTerm')).toBe('Care');
    });

    it('saves the category with the version of its layout and stays on the page', async () => {
        const { wrapper, saveCategory, getCategory, push } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();

        expect(saveCategory).toHaveBeenCalledWith(expect.objectContaining({ id: 'category-1', contentLayoutId: 'page-1' }));

        // the page reloads the saved state instead of returning to the list
        expect(getCategory).toHaveBeenCalledTimes(2);
        expect(wrapper.get('.select-category').attributes('data-refresh')).toBe('1');
        expect(push).not.toHaveBeenCalled();
    });

    it('gives a new category the active default the definition requires', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, active: null } });
        await flushPromises();

        expect((wrapper.get('.sw-blog-category-list__active').element as HTMLInputElement).checked).toBe(true);

        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();

        expect(saveCategory).toHaveBeenCalledWith(expect.objectContaining({ active: true }));
    });

    it('keeps a category that was switched off switched off', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, active: false } });
        await flushPromises();

        expect((wrapper.get('.sw-blog-category-list__active').element as HTMLInputElement).checked).toBe(false);

        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();

        expect(saveCategory).toHaveBeenCalledWith(expect.objectContaining({ active: false }));
    });

    it('refuses to save a category without a name', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, name: '  ' } });
        await flushPromises();

        expect((wrapper.get('.sw-blog-category-list__name').element as HTMLInputElement).value).toBe('  ');

        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');

        expect(saveCategory).not.toHaveBeenCalled();
    });

    it('refuses to save a category without an url name', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, slug: '' } });
        await flushPromises();

        expect((wrapper.get('.sw-blog-category-list__slug').element as HTMLInputElement).value).toBe('');

        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');

        expect(saveCategory).not.toHaveBeenCalled();
    });

    it('asks before it discards unsaved changes and reloads the category after the switch', async () => {
        const { wrapper, getCategory, blogSearch } = await createWrapper({ hasChanges: true });
        await flushPromises();

        expect(wrapper.get('.unsaved').text() === 'true').toBe(true);

        await wrapper.get('.language-change').trigger('click');
        await flushPromises();

        expect(getCategory).toHaveBeenCalledTimes(2);
        expect(blogSearch).toHaveBeenCalledTimes(2);
        expect(wrapper.get('.select-category').attributes('data-refresh')).toBe('1');
    });

    it('stays on the page when the save was triggered by the language switch', async () => {
        const { wrapper, saveCategory, push } = await createWrapper();
        await flushPromises();

        await wrapper.get('.language-save').trigger('click');
        await flushPromises();

        expect(saveCategory).toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('loads the newly selected category when the detail route reuses the workspace', async () => {
        const { wrapper, getCategory } = await createWrapper();
        await flushPromises();
        getCategory.mockResolvedValueOnce({ ...category, id: 'category-2', name: 'News' });
        await wrapper.setProps({ categoryId: 'category-2' });
        await flushPromises();
        expect(getCategory).toHaveBeenLastCalledWith('category-2', Shopwell.Context.api, expect.anything());
        expect((wrapper.get('.sw-blog-category-list__name').element as HTMLInputElement).value).toBe('News');
    });

    it('keeps saving and deleting away from a reader', async () => {
        const { wrapper } = await createWrapper({ can: (privilege) => privilege === 'blog.viewer' });
        await flushPromises();

        expect(wrapper.get('.sw-blog-category-list__action-save').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-blog-category-list__action-delete').exists()).toBe(false);
    });

    it('keeps category navigation visible when switching editor tabs', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        expect(wrapper.find('.select-category').exists()).toBe(true);
        expect(wrapper.find('.sw-blog-category-list__general').isVisible()).toBe(true);
        await wrapper.get('[data-tab="content"]').trigger('click');
        expect(wrapper.find('.sw-blog-category-list__general').exists()).toBe(false);
        expect(wrapper.find('.sw-blog-category-list__entries').isVisible()).toBe(true);
        expect(wrapper.find('.select-category').exists()).toBe(true);
    });

    it('asks before switching a category with unsaved changes and allows cancellation', async () => {
        const { wrapper, push, getCategory } = await createWrapper({ hasChanges: true });
        await flushPromises();
        await wrapper.get('.select-category').trigger('click');
        expect(push).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-blog-category-list__unsaved-modal').exists()).toBe(true);
        await wrapper.get('.sw-blog-category-list__unsaved-cancel').trigger('click');
        expect(push).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-blog-category-list__unsaved-modal').exists()).toBe(false);
        await wrapper.get('.select-category').trigger('click');
        await wrapper.get('.sw-blog-category-list__unsaved-discard').trigger('click');
        await flushPromises();
        expect(getCategory).toHaveBeenLastCalledWith('category-2', Shopwell.Context.api, expect.anything());
        expect(push).not.toHaveBeenCalled();
    });

    it('adds video assignments and removes only the category association on save', async () => {
        const { wrapper, saveCategory, deleteCategory, deleteAssignment } = await createWrapper();
        await flushPromises();
        await wrapper.get('[data-tab="content"]').trigger('click');
        await wrapper.get('.sw-blog-category-list__entry-select').setValue('blog-video');
        await flushPromises();
        expect(wrapper.get('.sw-blog-category-list__entries').text()).toContain('Video');
        await wrapper.get('[data-entry-id="blog-1"]').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        const saved = (saveCategory.mock.calls as Array<[{ assignments: Array<{ blogId: string }> }]>)[0][0];
        expect([...saved.assignments].map((assignment) => assignment.blogId)).toEqual(['blog-video']);
        expect(deleteCategory).not.toHaveBeenCalled();
        expect(deleteAssignment).toHaveBeenCalledWith('assignment-1', Shopwell.Context.api);
    });

    it('paginates entries beyond the previous 100-entry boundary and resolves removal mappings on demand', async () => {
        const { wrapper, blogSearch, searchAssignments, deleteAssignment } = await createWrapper({ entryTotal: 126 });
        await flushPromises();
        await wrapper.get('[data-tab="content"]').trigger('click');
        await wrapper.get('.next-entry-page').trigger('click');
        await flushPromises();
        expect(blogSearch).toHaveBeenLastCalledWith(expect.objectContaining({ page: 6, limit: 25 }), Shopwell.Context.api);
        await wrapper.get('[data-entry-id="blog-1"]').trigger('click');
        await flushPromises();
        expect(searchAssignments).toHaveBeenCalledWith(
            expect.objectContaining({
                limit: 1,
                filters: [
                    { type: 'equals', field: 'blogCategoryId', value: 'category-1' },
                    { type: 'equals', field: 'blogId', value: 'blog-1' },
                ],
            }),
            Shopwell.Context.api,
        );
        expect(deleteAssignment).not.toHaveBeenCalled();
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        expect(deleteAssignment).toHaveBeenCalledWith('assignment-1', Shopwell.Context.api);
    });

    it('does not add a duplicate assignment when the selected entry is on another page', async () => {
        const { wrapper, searchAssignments, saveCategory } = await createWrapper();
        await flushPromises();
        searchAssignments.mockResolvedValueOnce(
            collection<EntityCollection<'blog_category_assignment'>>([{ id: 'existing-video', blogId: 'blog-video' }]),
        );
        await wrapper.get('[data-tab="content"]').trigger('click');
        await wrapper.get('.sw-blog-category-list__entry-select').setValue('blog-video');
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        const saved = (saveCategory.mock.calls as Array<[{ assignments: unknown[] }]>)[0][0];
        expect(saved.assignments).toHaveLength(0);
    });

    it('keeps staged mapping removals after a failed save so the merchant can retry', async () => {
        const { wrapper, deleteAssignment, push } = await createWrapper();
        await flushPromises();
        await wrapper.get('[data-tab="content"]').trigger('click');
        await wrapper.get('[data-entry-id="blog-1"]').trigger('click');
        await flushPromises();
        deleteAssignment.mockRejectedValueOnce(new Error('mapping write failed'));
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        expect(push).not.toHaveBeenCalled();
        expect(wrapper.find('[data-entry-id="blog-1"]').exists()).toBe(false);
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        expect(deleteAssignment).toHaveBeenCalledTimes(2);
    });

    it('can undo a newly added entry before saving without writing a mapping', async () => {
        const { wrapper, saveCategory, deleteAssignment } = await createWrapper();
        await flushPromises();
        await wrapper.get('[data-tab="content"]').trigger('click');
        await wrapper.get('.sw-blog-category-list__entry-select').setValue('blog-video');
        await flushPromises();
        await wrapper.get('[data-entry-id="blog-video"]').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        expect((saveCategory.mock.calls as Array<[{ assignments: unknown[] }]>)[0][0].assignments).toHaveLength(0);
        expect(deleteAssignment).not.toHaveBeenCalled();
    });

    it('removes an addition that was already persisted by a partially failed save', async () => {
        const { wrapper, deleteAssignment, searchAssignments } = await createWrapper();
        await flushPromises();
        await wrapper.get('[data-tab="content"]').trigger('click');
        await wrapper.get('.sw-blog-category-list__entry-select').setValue('blog-video');
        await flushPromises();
        await wrapper.get('[data-entry-id="blog-1"]').trigger('click');
        await flushPromises();
        deleteAssignment.mockRejectedValueOnce(new Error('mapping deletion failed'));
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        // The nested addition succeeded even though deleting the original mapping failed.
        searchAssignments.mockResolvedValueOnce(
            collection<EntityCollection<'blog_category_assignment'>>([
                { id: 'assignment-new', blogId: 'blog-video', blogCategoryId: 'category-1' },
            ]),
        );
        await wrapper.get('[data-entry-id="blog-video"]').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        expect(deleteAssignment).toHaveBeenCalledWith('assignment-new', Shopwell.Context.api);
    });

    it('rejects a failed language save and keeps the edited category visible', async () => {
        const { wrapper, saveCategory, getCategory } = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__name').setValue('Edited name');
        saveCategory.mockRejectedValueOnce(new Error('save failed'));
        await wrapper.get('.language-save').trigger('click');
        await flushPromises();
        expect(wrapper.get('.language-save-failed').text()).toBe('true');
        expect(getCategory).toHaveBeenCalledTimes(1);
        expect((wrapper.get('.sw-blog-category-list__name').element as HTMLInputElement).value).toBe('Edited name');
    });

    it('blocks language changes while an assignment lookup is pending', async () => {
        const { wrapper, searchAssignments, getCategory } = await createWrapper();
        await flushPromises();
        let resolveMapping: ((value: unknown) => void) | undefined;
        searchAssignments.mockReturnValueOnce(
            new Promise((resolve) => {
                resolveMapping = resolve;
            }),
        );
        await wrapper.get('[data-tab="content"]').trigger('click');
        await wrapper.get('[data-entry-id="blog-1"]').trigger('click');
        await wrapper.get('.language-change').trigger('click');
        await flushPromises();
        expect(getCategory).toHaveBeenCalledTimes(1);
        resolveMapping?.(
            collection<EntityCollection<'blog_category_assignment'>>([
                { id: 'assignment-1', blogId: 'blog-1', blogCategoryId: 'category-1' },
            ]),
        );
        await flushPromises();
        expect(wrapper.find('[data-entry-id="blog-1"]').exists()).toBe(false);
    });

    it('does not switch categories while a category save is in progress', async () => {
        const { wrapper, saveCategory, push } = await createWrapper();
        await flushPromises();
        let finishSave: (() => void) | undefined;
        saveCategory.mockReturnValueOnce(
            new Promise<void>((resolve) => {
                finishSave = resolve;
            }),
        );
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await wrapper.get('.select-category').trigger('click');
        expect(push).not.toHaveBeenCalled();
        finishSave?.();
        await flushPromises();
    });

    it('does not reload a newly opened category when an older category finishes saving', async () => {
        const { wrapper, saveCategory, getCategory } = await createWrapper();
        await flushPromises();
        let finishSave: (() => void) | undefined;
        saveCategory.mockReturnValueOnce(
            new Promise<void>((resolve) => {
                finishSave = resolve;
            }),
        );
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        getCategory.mockResolvedValueOnce({ ...category, id: 'category-2', name: 'News' });
        await wrapper.setProps({ categoryId: 'category-2' });
        await flushPromises();
        finishSave?.();
        await flushPromises();
        expect(getCategory).toHaveBeenCalledTimes(2);
        expect((wrapper.get('.sw-blog-category-list__name').element as HTMLInputElement).value).toBe('News');
    });

    it('keeps protecting new edits when navigation is cancelled during saving', async () => {
        const { wrapper, saveCategory, router } = await createWrapper({ hasChanges: true, realRouter: true });
        await flushPromises();
        let finishSave: (() => void) | undefined;
        saveCategory.mockReturnValueOnce(
            new Promise<void>((resolve) => {
                finishSave = resolve;
            }),
        );
        await wrapper.get('.select-category').trigger('click');
        await wrapper.get('.sw-blog-category-list__unsaved-save').trigger('click');
        await wrapper.get('.sw-blog-category-list__unsaved-cancel').trigger('click');
        finishSave?.();
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__name').setValue('Another edit');
        const navigation = router?.push('/outside');
        await flushPromises();
        expect(wrapper.find('.outside').exists()).toBe(false);
        expect(wrapper.find('.sw-blog-category-list__unsaved-modal').exists()).toBe(true);
        await wrapper.get('.sw-blog-category-list__unsaved-cancel').trigger('click');
        await navigation;
    });

    it('keeps unsaved changes when saving before a category switch fails', async () => {
        const { wrapper, saveCategory, push } = await createWrapper({ hasChanges: true });
        await flushPromises();
        saveCategory.mockRejectedValueOnce(new Error('save failed'));
        await wrapper.get('.select-category').trigger('click');
        await wrapper.get('.sw-blog-category-list__unsaved-save').trigger('click');
        await flushPromises();
        expect(push).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-blog-category-list__unsaved-modal').exists()).toBe(true);
    });

    it('keeps the draft when a tree operation is cancelled', async () => {
        const { wrapper, saveCategory } = await createWrapper({ hasChanges: true });
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__name').setValue('Draft name');
        await wrapper.get('.change-structure').trigger('click');
        await wrapper.get('.sw-blog-category-list__unsaved-cancel').trigger('click');
        await flushPromises();
        expect(wrapper.get('.structure-approved').text()).toBe('false');
        expect((wrapper.get('.sw-blog-category-list__name').element as HTMLInputElement).value).toBe('Draft name');
        expect(saveCategory).not.toHaveBeenCalled();
    });

    it('discards the draft before changing the tree without bypassing future route guards', async () => {
        const { wrapper, router } = await createWrapper({ hasChanges: true, realRouter: true });
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__name').setValue('Draft name');
        await wrapper.get('.change-structure').trigger('click');
        await wrapper.get('.sw-blog-category-list__unsaved-discard').trigger('click');
        await flushPromises();
        expect(wrapper.get('.structure-approved').text()).toBe('true');
        expect((wrapper.get('.sw-blog-category-list__name').element as HTMLInputElement).value).toBe('Guides');
        await wrapper.get('.sw-blog-category-list__name').setValue('New draft');
        const navigation = router?.push('/outside');
        await flushPromises();
        expect(wrapper.find('.outside').exists()).toBe(false);
        expect(wrapper.find('.sw-blog-category-list__unsaved-modal').exists()).toBe(true);
        await wrapper.get('.sw-blog-category-list__unsaved-cancel').trigger('click');
        await navigation;
    });

    it('saves the editor before allowing a tree operation', async () => {
        const { wrapper, saveCategory } = await createWrapper({ hasChanges: true });
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__name').setValue('Saved name');
        await wrapper.get('.change-structure').trigger('click');
        await wrapper.get('.sw-blog-category-list__unsaved-save').trigger('click');
        await flushPromises();
        expect(saveCategory).toHaveBeenCalledWith(expect.objectContaining({ name: 'Saved name' }));
        expect(wrapper.get('.structure-approved').text()).toBe('true');
    });

    it('blocks deletion of a category that still has subcategories', async () => {
        const { wrapper, deleteCategory } = await createWrapper({ entry: { ...category, childCount: 2 } });
        await flushPromises();
        const action = wrapper.get('.sw-blog-category-list__action-delete');
        expect(action.attributes('disabled')).toBeDefined();
        expect(action.attributes('title')).toBe('sw-blog.category.deleteHasChildren');
        await action.trigger('click');
        expect(wrapper.find('.sw-blog-category-list__delete-modal').exists()).toBe(false);
        expect(deleteCategory).not.toHaveBeenCalled();
    });

    it('deletes the category after the confirmation', async () => {
        const { wrapper, deleteCategory, push } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-category-list__action-delete').trigger('click');
        await wrapper.get('.sw-blog-category-list__delete-modal').findAll('button')[1].trigger('click');
        await flushPromises();

        expect(deleteCategory).toHaveBeenCalledWith('category-1');
        expect(push).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-blog-category-list__selection').exists()).toBe(true);
    });
});
