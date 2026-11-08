/**
 * @sw-package discovery
 */
import { flushPromises } from '@vue/test-utils';
import { associationNames, createWrapper, unmountActiveWrapper } from './sw-blog-detail.spec/create-wrapper';
import { blog } from './sw-blog-detail.spec/fixtures';
import { registerBlogType, resetBlogTypes } from '../../service/blog-type.registry';

describe('src/module/sw-blog/page/sw-blog-detail', () => {
    beforeEach(() => {
        registerBlogType('post', () => Promise.resolve({ default: { name: 'post' } }));
        registerBlogType('video', () => Promise.resolve({ default: { name: 'video' } }));
    });

    afterEach(() => {
        unmountActiveWrapper();
        resetBlogTypes();
        jest.restoreAllMocks();
    });

    it('loads the entry, selected categories and its selected content layout', async () => {
        const { wrapper, getBlog } = await createWrapper();
        await flushPromises();
        const criteria = (getBlog.mock.calls as unknown as Array<[string, unknown, { associations: unknown[] }]>)[0][2];
        expect(associationNames(criteria)).toEqual(
            expect.arrayContaining([
                'author',
                'categories',
                'categories.blogCategory',
                'videos',
            ]),
        );
        expect((wrapper.get('.sw-blog-detail__title').element as HTMLInputElement).value).toBe('Autumn guide');
        expect(
            wrapper
                .findAll('.sw-blog-detail__category-option input')
                .map((input) => (input.element as HTMLInputElement).checked),
        ).toEqual([true, false]);
        expect((wrapper.get('.sw-blog-detail__content-layout').element as HTMLInputElement).value).toBe('page-1');
        expect(wrapper.find('.sw-blog-detail__loading').exists()).toBe(false);
    });

    it('creates a new entry directly in the editor without reading a nonexistent entity', async () => {
        const { wrapper, getBlog } = await createWrapper({ blogId: null });
        await flushPromises();
        expect((wrapper.get('.sw-blog-detail__title').element as HTMLInputElement).value).toBe('');
        expect((wrapper.get('.sw-blog-detail__type').element as HTMLSelectElement).value).toBe('post');
        expect(wrapper.get('.sw-blog-detail__type').attributes('disabled')).toBeUndefined();
        expect(getBlog).not.toHaveBeenCalled();
    });

    it('locks the type of an existing entry', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        expect((wrapper.get('.sw-blog-detail__type').element as HTMLSelectElement).value).toBe('post');
        expect(wrapper.get('.sw-blog-detail__type').attributes('disabled')).toBeDefined();
    });

    it('generates a new entry URL name from its title and replaces the create route after saving', async () => {
        const { wrapper, saveBlog, replace, push } = await createWrapper({ blogId: null });
        await flushPromises();
        await wrapper.get('.sw-blog-detail__title').setValue('Autumn Guide');
        await wrapper.get('.sw-blog-detail__action-save').trigger('click');
        await flushPromises();
        const saved = (saveBlog.mock.calls as unknown as Array<[{ slug: string }]>)[0][0];
        expect(saved.slug).toMatch(/^autumn-guide-/);
        expect(replace).toHaveBeenCalledWith({ name: 'sw.blog.detail', params: { id: 'blog-new' } });
        expect(push).not.toHaveBeenCalled();
    });

    it.each(['', '   '])('asks for a title before writing an entry with title %s', async (title) => {
        const { wrapper, saveBlog } = await createWrapper({ entry: { ...blog, title } });
        const notify = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        await flushPromises();
        await wrapper.get('.sw-blog-detail__action-save').trigger('click');
        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ message: 'sw-blog.detail.messageTitleRequired' }));
        expect(saveBlog).not.toHaveBeenCalled();
    });

    it('provides a fresh video row to the video editor when none exists', async () => {
        const { wrapper } = await createWrapper({ entry: { ...blog, type: 'video', videos: [] } });
        await flushPromises();
        expect(wrapper.get('.video-id').text()).toBe('video-new');
        expect(wrapper.get('.video-blog').text()).toBe('blog-1');
        expect(wrapper.get('.video-provider').text()).toBe('youtube');
    });

    it('retains the existing video row in the editor', async () => {
        const { wrapper } = await createWrapper({
            entry: { ...blog, type: 'video', videos: [{ id: 'video-1', videoUrl: 'https://example.com/v.mp4' }] },
        });
        await flushPromises();
        expect(wrapper.get('.video-id').text()).toBe('video-1');
    });

    it('writes entry/video/category changes and reloads the saved entry without leaving', async () => {
        const { wrapper, saveBlog, saveVideo, saveAssignment, deleteAssignment, push, getBlog } = await createWrapper({
            entry: { ...blog, type: 'video', videos: [] },
        });
        await flushPromises();
        await wrapper.findAll('.sw-blog-detail__category-option input')[0].setValue(false);
        await wrapper.findAll('.sw-blog-detail__category-option input')[1].setValue(true);
        await wrapper.get('.sw-blog-detail__action-save').trigger('click');
        await flushPromises();
        expect(saveVideo).toHaveBeenCalled();
        expect(saveBlog).toHaveBeenCalledWith(expect.objectContaining({ id: 'blog-1' }));
        expect(deleteAssignment).toHaveBeenCalledWith('assignment-1');
        expect(saveAssignment).toHaveBeenCalledWith(
            expect.objectContaining({ blogId: 'blog-1', blogCategoryId: 'category-2' }),
        );
        expect(getBlog).toHaveBeenCalledTimes(2);
        expect(push).not.toHaveBeenCalled();
    });

    it('leaves the editor when the operator cancels', async () => {
        const { wrapper, push } = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-blog-detail__action-cancel').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.list' });
    });

    it('publishes immediately without leaving the editor', async () => {
        const { wrapper, saveBlog, push } = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-blog-detail__publish-now').trigger('click');
        await flushPromises();
        const saved = (saveBlog.mock.calls as unknown as Array<[{ publishedAt: string }]>)[0][0];
        expect(saved.publishedAt).toMatch(/^\d{4}-\d{2}-\d{2}T/);
        expect(push).not.toHaveBeenCalled();
    });

    it('clears the scheduled publication date through the explicit action', async () => {
        const { wrapper, saveBlog } = await createWrapper({ entry: { ...blog, publishedAt: '2999-01-01T00:00:00.000Z' } });
        await flushPromises();
        await wrapper.get('.sw-blog-detail__clear-schedule').trigger('click');
        await flushPromises();
        expect(saveBlog).toHaveBeenCalledWith(expect.objectContaining({ publishedAt: null }));
    });

    it('files the entry by checking and unchecking categories', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        const categories = wrapper.findAll('.sw-blog-detail__category-option input');
        expect((categories[0].element as HTMLInputElement).checked).toBe(true);
        await categories[0].setValue(false);
        await categories[1].setValue(true);
        expect(categories.map((input) => (input.element as HTMLInputElement).checked)).toEqual([false, true]);
    });

    it('creates a category in the editor and selects it without prematurely saving the entry', async () => {
        const { wrapper, saveBlog } = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-blog-detail__add-category').trigger('click');
        await wrapper.get('.sw-blog-detail__category-name').setValue('Guides');
        await wrapper.get('.sw-blog-detail__category-modal').findAll('button')[1].trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-blog-detail__category-modal').exists()).toBe(false);
        const guides = wrapper.findAll('.sw-blog-detail__category-option').find((option) => option.text() === 'Guides');
        expect(guides).toBeDefined();
        expect(
            wrapper
                .findAll('.sw-blog-detail__category-option')
                .filter((option) => option.text() === 'Guides')[0]
                .get('input').element,
        ).toHaveProperty('checked', true);
        expect(saveBlog).not.toHaveBeenCalled();
    });

    it('creates an author in the editor and selects the new author', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-blog-detail__add-author').trigger('click');
        await wrapper.get('.sw-blog-detail__author-name').setValue('Anna');
        await wrapper.get('.sw-blog-detail__author-modal').findAll('button')[1].trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-blog-detail__author-modal').exists()).toBe(false);
        expect((wrapper.get('.sw-blog-detail__author').element as HTMLInputElement).value).toBe('author-new');
    });

    it('keeps moderation controls off the entry editing page', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        expect(wrapper.find('[href*="comment"]').exists()).toBe(false);
        expect(wrapper.find('.sw-blog-detail__comments').exists()).toBe(false);
    });

    it('saves a pending translation without leaving the editor', async () => {
        const { wrapper, saveBlog, push } = await createWrapper();
        await flushPromises();
        await wrapper.get('.language-save').trigger('click');
        await flushPromises();
        expect(saveBlog).toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('warns the language switch about unsaved changes and reloads after language changes', async () => {
        const { wrapper, getBlog } = await createWrapper({ hasChanges: true });
        await flushPromises();
        expect(wrapper.get('.unsaved').text()).toBe('true');
        await wrapper.get('.language-change').trigger('click');
        await flushPromises();
        expect(getBlog).toHaveBeenCalledTimes(2);
    });

    it('keeps writing and deletion unavailable to readers', async () => {
        const { wrapper } = await createWrapper({ can: (privilege) => privilege === 'blog.viewer' });
        await flushPromises();
        expect(wrapper.get('.sw-blog-detail__action-save').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-blog-detail__action-delete').exists()).toBe(false);
        expect(wrapper.get('.sw-blog-detail__title').attributes('disabled')).toBeDefined();
    });

    it('selects and removes the visible cover reference', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        await wrapper.get('.select-cover').trigger('click');
        expect(wrapper.get('.cover-source').text()).toBe('media-1');
        await wrapper.get('.remove-cover').trigger('click');
        expect(wrapper.get('.cover-source').text()).toBe('');
    });

    it('deletes only after the confirmation and returns to the list', async () => {
        const { wrapper, deleteBlog, push } = await createWrapper();
        await flushPromises();
        await wrapper.get('.sw-blog-detail__action-delete').trigger('click');
        expect(wrapper.find('.sw-blog-detail__delete-modal').exists()).toBe(true);
        expect(deleteBlog).not.toHaveBeenCalled();
        await wrapper.get('.sw-blog-detail__delete-modal').findAll('button')[1].trigger('click');
        await flushPromises();
        expect(deleteBlog).toHaveBeenCalledWith('blog-1');
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.list' });
    });
});
