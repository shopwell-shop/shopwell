/**
 * @sw-package discovery
 */
import { flushPromises } from '@vue/test-utils';
import { createWrapper, cleanupWrapper } from './create-wrapper';

describe('Category workspace creation', () => {
    afterEach(cleanupWrapper);

    it('opens the full form and switches selection without a write or navigation', async () => {
        const { wrapper, getCategory, blogSearch, saveCategory, push } = await createWrapper({ categoryId: null });
        expect(wrapper.find('.sw-blog-category-list__selection').exists()).toBe(true);
        await wrapper.get('.sw-blog-category-list__action-create').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-blog-category-list__description').exists()).toBe(true);
        expect(wrapper.find('.sw-blog-category-list__action-delete').exists()).toBe(false);
        expect(wrapper.find('.sw-blog-category-list__language-switch').exists()).toBe(false);
        expect(wrapper.find('[data-tab="content"]').exists()).toBe(false);
        expect(getCategory).not.toHaveBeenCalled();
        expect(blogSearch).not.toHaveBeenCalled();
        await wrapper.get('.sw-blog-category-list__name').setValue('Unsaved category');
        expect(wrapper.find('.sw-blog-category-list__action-cancel').exists()).toBe(false);
        await wrapper.get('.select-category').trigger('click');
        await flushPromises();
        expect(saveCategory).not.toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('saves a child draft once with its parent, order and generated URL name', async () => {
        const { wrapper, saveCategory, getCategory, push } = await createWrapper({ categoryId: null });
        await wrapper.get('.create-child').trigger('click');
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        expect(saveCategory).not.toHaveBeenCalled();
        await wrapper.get('.sw-blog-category-list__name').setValue('Care guides');
        await wrapper.get('.sw-blog-category-list__description').setValue('Useful guides');
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        expect(saveCategory).toHaveBeenCalledTimes(1);
        expect(saveCategory).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'Care guides',
                description: 'Useful guides',
                active: true,
                parentId: 'category-1',
                afterCategoryId: null,
                slug: expect.stringMatching(/^care-guides-/) as unknown as string,
            }),
        );
        expect(getCategory).toHaveBeenCalledWith('category-new', Shopwell.Context.api, expect.anything());
        expect(push).not.toHaveBeenCalled();
    });

    it('retains the draft after a failed save and lets the user retry', async () => {
        const { wrapper, saveCategory } = await createWrapper({ categoryId: null });
        await wrapper.get('.sw-blog-category-list__action-create').trigger('click');
        await wrapper.get('.sw-blog-category-list__name').setValue('New category');
        saveCategory.mockRejectedValueOnce(new Error('Failed save'));
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        expect((wrapper.get('.sw-blog-category-list__name').element as HTMLInputElement).value).toBe('New category');
        await wrapper.get('.sw-blog-category-list__action-save').trigger('click');
        await flushPromises();
        expect(saveCategory).toHaveBeenCalledTimes(2);
    });

    it('guards creation while editing and does not route away from the workspace', async () => {
        const { wrapper, saveCategory, push } = await createWrapper({ hasChanges: true });
        await wrapper.get('.sw-blog-category-list__name').setValue('Edited category');
        await wrapper.get('.sw-blog-category-list__action-create').trigger('click');
        expect(wrapper.find('.sw-blog-category-list__unsaved-modal').exists()).toBe(true);
        await wrapper.get('.sw-blog-category-list__unsaved-cancel').trigger('click');
        expect((wrapper.get('.sw-blog-category-list__name').element as HTMLInputElement).value).toBe('Edited category');
        expect(saveCategory).not.toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('requires creator permission to start and save a new category', async () => {
        const { wrapper, saveCategory } = await createWrapper({
            categoryId: null,
            can: (privilege) => privilege === 'blog.editor',
        });
        expect(wrapper.get('.sw-blog-category-list__action-create').attributes('disabled')).toBeDefined();
        await wrapper.get('.create-child').trigger('click');
        expect(wrapper.find('.sw-blog-category-list__name').exists()).toBe(false);
        expect(saveCategory).not.toHaveBeenCalled();
    });

    it('switches away from an untouched draft without an unsaved dialog', async () => {
        const { wrapper, saveCategory } = await createWrapper({ categoryId: null, hasChanges: true });
        await wrapper.get('.sw-blog-category-list__action-create').trigger('click');
        await wrapper.get('.select-category').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-blog-category-list__unsaved-modal').exists()).toBe(false);
        expect(saveCategory).not.toHaveBeenCalled();
    });

    it('renders newly assigned articles without passing missing dates to the time component', async () => {
        const { wrapper } = await createWrapper();
        await wrapper.get('[data-tab="content"]').trigger('click');
        await wrapper.get('.sw-blog-category-list__entry-select').setValue('blog-video');
        await flushPromises();
        const grid = wrapper.get('.sw-blog-category-list__entries-grid');
        expect(grid.text()).toContain('sw-blog.status.draft');
        expect(grid.text()).toContain('—');
        expect(grid.findAll('time').every((time) => Boolean(time.attributes('datetime')))).toBe(true);
    });
});
