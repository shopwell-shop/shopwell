/**
 * @sw-package discovery
 */
import { flushPromises } from '@vue/test-utils';
import { category, createWrapper, cleanupWrapper } from './create-wrapper';

describe('Category workspace asynchronous navigation', () => {
    afterEach(cleanupWrapper);

    it('locks editing, saving and creation until a tree move completes', async () => {
        const { wrapper, saveCategory, getCategory } = await createWrapper();
        await wrapper.get('.start-tree-move').trigger('click');
        expect(wrapper.get('.sw-blog-category-list__name').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.sw-blog-category-list__action-create').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.sw-blog-category-list__action-save').attributes('disabled')).toBeDefined();
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await wrapper.get('.select-category').trigger('click');
        expect(saveCategory).not.toHaveBeenCalled();
        expect(getCategory).toHaveBeenCalledTimes(1);
        await wrapper.get('.finish-tree-move').trigger('click');
        expect(wrapper.get('.sw-blog-category-list__name').attributes('disabled')).toBeUndefined();
    });

    it('blocks switching and creation during deletion and ignores a stale completion', async () => {
        const { wrapper, deleteCategory, getCategory } = await createWrapper();
        let finishDelete: (() => void) | undefined;
        deleteCategory.mockReturnValueOnce(
            new Promise<void>((resolve) => {
                finishDelete = resolve;
            }),
        );
        await wrapper.get('.sw-blog-category-list__action-delete').trigger('click');
        await wrapper.get('.sw-blog-category-list__delete-modal').findAll('button')[1].trigger('click');
        await wrapper.get('.select-category').trigger('click');
        await wrapper.get('.create-child').trigger('click');
        expect(getCategory).toHaveBeenCalledTimes(1);
        expect(wrapper.get('.sw-blog-category-list__action-create').attributes('disabled')).toBeDefined();
        getCategory.mockResolvedValueOnce({ ...category, id: 'category-2', name: 'News' });
        await wrapper.setProps({ categoryId: 'category-2' });
        await flushPromises();
        finishDelete?.();
        await flushPromises();
        expect((wrapper.get('.sw-blog-category-list__name').element as HTMLInputElement).value).toBe('News');
    });

    it('protects edits after accepting a query selection change in the same route', async () => {
        const { wrapper, router } = await createWrapper({ hasChanges: true, realRouter: true });
        const changeSelection = router?.push('/category?id=category-2');
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__unsaved-discard').trigger('click');
        await changeSelection;
        await flushPromises();
        await wrapper.get('.sw-blog-category-list__name').setValue('Another draft');
        const leave = router?.push('/outside');
        await flushPromises();
        expect(wrapper.find('.outside').exists()).toBe(false);
        expect(wrapper.find('.sw-blog-category-list__unsaved-modal').exists()).toBe(true);
        await wrapper.get('.sw-blog-category-list__unsaved-cancel').trigger('click');
        await leave;
    });
});
