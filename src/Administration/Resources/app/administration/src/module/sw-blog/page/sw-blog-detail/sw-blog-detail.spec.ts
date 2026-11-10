/**
 * @sw-package discovery
 */
import { flushPromises } from '@vue/test-utils';
import ShopwellError from 'src/core/data/ShopwellError';
import { createWrapper, unmountActiveWrapper } from './sw-blog-detail.spec/create-wrapper';
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
        Shopwell.Store.get('error').resetApiErrors();
        jest.restoreAllMocks();
    });

    it('creates content in the system language even when a translation was previously selected', async () => {
        const context = Shopwell.Store.get('context');
        const resetLanguage = jest.spyOn(context, 'resetLanguageToDefault');
        context.setApiLanguageId('translation-language' as EntitySchema.EntityKey<'language'>);

        await createWrapper({ blogId: null });
        await flushPromises();

        expect(resetLanguage).toHaveBeenCalledTimes(1);
        expect(context.isSystemDefaultLanguage).toBe(true);
    });

    it('preserves the selected translation when editing existing content', async () => {
        const context = Shopwell.Store.get('context');
        const resetLanguage = jest.spyOn(context, 'resetLanguageToDefault');
        context.setApiLanguageId('translation-language' as EntitySchema.EntityKey<'language'>);

        await createWrapper();
        await flushPromises();

        expect(resetLanguage).not.toHaveBeenCalled();
        expect(Shopwell.Context.api.languageId).toBe('translation-language');
        context.resetLanguageToDefault();
    });

    it('opens new video content as an unpublished draft', async () => {
        const { wrapper, getBlog, saveBlog } = await createWrapper({ blogId: null, blogType: 'video' });
        await flushPromises();
        expect(wrapper.get('.video-id').text()).toBe('video-new');
        expect(wrapper.get('.sw-blog-detail__type').text()).toBe('video');
        expect(getBlog).not.toHaveBeenCalled();
        await wrapper.get('.sw-blog-detail__title').setValue('Video draft');
        await wrapper.get('.sw-blog-detail__action-save').trigger('click');
        await flushPromises();
        expect(saveBlog).toHaveBeenCalledWith(expect.objectContaining({ active: false, type: 'video' }));
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

    it('saves video and category changes in the blog aggregate before reloading', async () => {
        const { wrapper, saveBlog, saveVideo, saveAssignment, deleteAssignment, getBlog } = await createWrapper({
            entry: { ...blog, type: 'video', videos: [] },
        });
        await flushPromises();
        await wrapper.get('.select-category').trigger('click');
        await wrapper.get('.sw-blog-detail__action-save').trigger('click');
        await flushPromises();
        const saved = (saveBlog.mock.calls as Array<[EntitySchema.Entity<'blog'>]>)[0][0];
        expect(Array.from(saved.videos ?? [], (video) => ({ id: video.id, blogId: video.blogId }))).toEqual([
            { id: 'video-new', blogId: 'blog-1' },
        ]);
        expect(Array.from(saved.categories ?? [], (assignment) => assignment.blogCategoryId)).toEqual(['category-2']);
        expect(saveVideo).not.toHaveBeenCalled();
        expect(saveAssignment).not.toHaveBeenCalled();
        expect(deleteAssignment).not.toHaveBeenCalled();
        expect(getBlog).toHaveBeenCalledTimes(2);
    });

    it('publishes immediately without leaving the editor', async () => {
        const { wrapper, saveBlog, push } = await createWrapper({ entry: { ...blog, active: false } });
        await flushPromises();
        await wrapper.get('.sw-blog-detail__publish-now').trigger('click');
        await flushPromises();
        const saved = (saveBlog.mock.calls as unknown as Array<[{ publishedAt: string; active: boolean }]>)[0][0];
        expect(saved.active).toBe(true);
        expect(saved.publishedAt).toMatch(/^\d{4}-\d{2}-\d{2}T/);
        expect(push).not.toHaveBeenCalled();
    });

    it('clears the scheduled publication date through the explicit action', async () => {
        const { wrapper, saveBlog } = await createWrapper({ entry: { ...blog, publishedAt: '2999-01-01T00:00:00.000Z' } });
        await flushPromises();
        await wrapper.get('.sw-blog-detail__clear-schedule').trigger('click');
        await flushPromises();
        expect(saveBlog).toHaveBeenCalledWith(expect.objectContaining({ publishedAt: null, active: false }));
    });

    it('keeps SEO and layout fields in their own tabs', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        expect(wrapper.find('.sw-blog-detail__meta-title').exists()).toBe(false);
        await wrapper.get('[data-tab="seo"]').trigger('click');
        expect(wrapper.find('.sw-blog-detail__meta-title').exists()).toBe(true);
        expect(wrapper.find('.content-editor').isVisible()).toBe(false);
        await wrapper.get('[data-tab="layout"]').trigger('click');
        expect(wrapper.find('sw-blog-detail-layout-stub').exists()).toBe(true);
    });

    it('does not redirect when the aggregate save fails', async () => {
        const { wrapper, saveBlog, push } = await createWrapper();
        saveBlog.mockRejectedValueOnce(new Error('Save failed'));
        await flushPromises();
        await wrapper.get('.sw-blog-detail__action-save').trigger('click');
        await flushPromises();
        expect(push).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-blog-detail__title').exists()).toBe(true);
    });

    it('shows DAL validation errors on the matching native field', async () => {
        const error = new ShopwellError({ code: 'CONTENT__INVALID_TITLE', detail: 'Title is invalid' });
        Shopwell.Store.get('error').addApiError({ expression: 'blog.blog-1.title', error });
        const { wrapper } = await createWrapper({ entry: { ...blog, getEntityName: () => 'blog' } });
        await flushPromises();

        expect(wrapper.get('.sw-blog-detail__title').attributes('data-error')).toBe(error.code);
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
