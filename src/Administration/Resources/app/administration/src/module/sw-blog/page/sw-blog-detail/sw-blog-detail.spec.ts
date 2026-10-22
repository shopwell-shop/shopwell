/**
 * @sw-package discovery
 */
import { flushPromises } from '@vue/test-utils';
import { associationNames, createWrapper, unmountActiveWrapper } from './sw-blog-detail.spec/create-wrapper';
import { blog } from './sw-blog-detail.spec/fixtures';

describe('src/module/sw-blog/page/sw-blog-detail', () => {
    afterEach(() => {
        unmountActiveWrapper();
        jest.clearAllMocks();
    });

    it('loads the entry, the categories it is filed under and its layout', async () => {
        const { wrapper, getBlog } = await createWrapper();
        await flushPromises();

        const criteria = (getBlog.mock.calls as unknown as Array<[string, unknown, { associations: unknown[] }]>)[0][2];

        expect(associationNames(criteria)).toEqual(
            expect.arrayContaining(['author', 'categories', 'categories.blogCategory', 'videos']),
        );
        expect(wrapper.vm.blog?.title).toBe('Autumn guide');
        expect(wrapper.vm.assignments).toHaveLength(1);
        expect(wrapper.vm.selectedCategoryIds).toEqual(['category-1']);
        expect(wrapper.vm.cmsPage?.id).toBe('page-1');
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('creates the entry on the page instead of in a dialog on the list', async () => {
        const { wrapper, getBlog } = await createWrapper({ blogId: null });
        await flushPromises();

        expect(wrapper.vm.blog).toEqual(expect.objectContaining({ type: 'post', title: '' }));
        // nothing exists yet, so nothing is read
        expect(getBlog).not.toHaveBeenCalled();
        expect(wrapper.vm.isNew).toBe(true);
    });

    it('gives a new entry its url name from the title on the first save and fixes the address', async () => {
        const { wrapper, saveBlog, replace, push } = await createWrapper({ blogId: null });
        await flushPromises();

        if (wrapper.vm.blog) {
            wrapper.vm.blog.title = 'Autumn Guide';
            wrapper.vm.blog.slug = '';
        }

        await wrapper.vm.onSave();
        await flushPromises();

        const saved = (saveBlog.mock.calls as unknown as Array<[{ slug: string }]>)[0][0];

        expect(saved.slug).toMatch(/^autumn-guide-/);
        expect(replace).toHaveBeenCalledWith({ name: 'sw.blog.detail', params: { id: 'blog-new' } });
        expect(push).not.toHaveBeenCalled();
    });

    it('asks for the title before anything is written', async () => {
        const { wrapper, saveBlog } = await createWrapper({ blogId: null });
        await flushPromises();

        await wrapper.vm.onSave();

        expect(wrapper.vm.isTitleMissing).toBe(true);
        expect(saveBlog).not.toHaveBeenCalled();
    });

    it('creates the video row of a video entry that has none yet', async () => {
        const { wrapper } = await createWrapper({ entry: { ...blog, type: 'video', videos: [] } });
        await flushPromises();

        expect(wrapper.vm.video).toEqual(expect.objectContaining({ blogId: 'blog-1', provider: 'youtube' }));
    });

    it('keeps the video row of an entry that already has one', async () => {
        const { wrapper } = await createWrapper({
            entry: { ...blog, type: 'video', videos: [{ id: 'video-1', videoUrl: 'https://example.com/v.mp4' }] },
        });
        await flushPromises();

        expect(wrapper.vm.video?.id).toBe('video-1');
    });

    it('writes the entry, the video and the category rows on save, and stays on the page', async () => {
        const { wrapper, saveBlog, saveVideo, saveAssignment, deleteAssignment, push, getBlog } = await createWrapper({
            entry: { ...blog, type: 'video', videos: [] },
        });
        await flushPromises();

        wrapper.vm.selectedCategoryIds = ['category-2'];
        await wrapper.vm.onSave();
        await flushPromises();

        expect(saveVideo).toHaveBeenCalled();
        expect(saveBlog).toHaveBeenCalledWith(expect.objectContaining({ id: 'blog-1' }));
        expect(deleteAssignment).toHaveBeenCalledWith('assignment-1');
        expect(saveAssignment).toHaveBeenCalledWith(
            expect.objectContaining({ blogId: 'blog-1', blogCategoryId: 'category-2' }),
        );

        // the page reloads the saved state instead of returning to the list
        expect(getBlog).toHaveBeenCalledTimes(2);
        expect(push).not.toHaveBeenCalled();
    });

    it('only leaves the page when the caller asks for it', async () => {
        const { wrapper, push } = await createWrapper();
        await flushPromises();

        await wrapper.vm.onSave(true);
        await flushPromises();

        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.list' });
    });

    it('publishes now without leaving the page', async () => {
        const { wrapper, saveBlog, push } = await createWrapper();
        await flushPromises();

        wrapper.vm.onPublishNow();
        await flushPromises();

        const saved = (saveBlog.mock.calls as unknown as Array<[{ publishedAt: string }]>)[0][0];

        expect(saved.publishedAt).toMatch(/^\d{4}-\d{2}-\d{2}T/);
        expect(push).not.toHaveBeenCalled();
    });

    it('clears the publication date, which makes the entry live right away', async () => {
        const { wrapper, saveBlog } = await createWrapper({
            entry: { ...blog, publishedAt: '2999-01-01T00:00:00.000Z' },
        });
        await flushPromises();

        wrapper.vm.onClearSchedule();
        await flushPromises();

        expect(saveBlog).toHaveBeenCalledWith(expect.objectContaining({ publishedAt: null }));
    });

    it('files the entry by ticking and unticking categories', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.isCategorySelected('category-1')).toBe(true);

        wrapper.vm.onToggleCategory('category-1');
        expect(wrapper.vm.selectedCategoryIds).toEqual([]);

        wrapper.vm.onToggleCategory('category-2');
        expect(wrapper.vm.selectedCategoryIds).toEqual(['category-2']);
    });

    it('creates a category while writing and ticks it', async () => {
        const { wrapper, saveBlog } = await createWrapper();
        await flushPromises();

        wrapper.vm.onOpenCategoryModal();
        wrapper.vm.newCategoryName = 'Guides';
        wrapper.vm.onCreateCategory();
        await flushPromises();

        expect(wrapper.vm.showCategoryModal).toBe(false);
        expect(wrapper.vm.selectedCategoryIds).toContain('category-new');
        expect(saveBlog).not.toHaveBeenCalled();
    });

    it('creates an author while writing and selects it', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        wrapper.vm.onOpenAuthorModal();
        wrapper.vm.newAuthorName = 'Anna';
        wrapper.vm.onCreateAuthor();
        await flushPromises();

        expect(wrapper.vm.showAuthorModal).toBe(false);
        expect(wrapper.vm.blog?.authorId).toBe('author-new');
    });

    it('counts the comments that wait for approval', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.pendingCommentCount).toBe(2);
    });

    it('refuses to save an entry without a title', async () => {
        const { wrapper, saveBlog } = await createWrapper({ entry: { ...blog, title: '   ' } });
        await flushPromises();

        expect(wrapper.vm.isTitleMissing).toBe(true);

        await wrapper.vm.onSave();

        expect(saveBlog).not.toHaveBeenCalled();
    });

    it('stays on the page when the save was triggered by the language switch', async () => {
        const { wrapper, saveBlog, push } = await createWrapper();
        await flushPromises();

        await wrapper.vm.saveOnLanguageChange();
        await flushPromises();

        expect(saveBlog).toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('asks before it discards unsaved changes and reloads the entry after the switch', async () => {
        const { wrapper, getBlog } = await createWrapper({ hasChanges: true });
        await flushPromises();

        expect(wrapper.vm.abortOnLanguageChange()).toBe(true);

        wrapper.vm.onChangeLanguage('language-2');
        await flushPromises();

        expect(getBlog).toHaveBeenCalledTimes(2);
    });

    it('keeps saving and deleting away from a reader', async () => {
        const { wrapper } = await createWrapper({ can: (privilege) => privilege === 'blog.viewer' });
        await flushPromises();

        expect(wrapper.vm.canEdit).toBe(false);
        expect(wrapper.vm.canDelete).toBe(false);
    });

    it('writes the chosen cover into the entry', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        wrapper.vm.onCoverSelected([{ id: 'media-1' }]);
        expect(wrapper.vm.blog?.coverMediaId).toBe('media-1');

        wrapper.vm.onCoverRemoved();
        expect(wrapper.vm.blog?.coverMediaId).toBeNull();
    });

    it('deletes the entry after the confirmation and returns to the list', async () => {
        const { wrapper, deleteBlog, push } = await createWrapper();
        await flushPromises();

        wrapper.vm.onOpenDeleteModal();
        expect(wrapper.vm.showDeleteModal).toBe(true);

        wrapper.vm.onDelete();
        await flushPromises();

        expect(deleteBlog).toHaveBeenCalledWith('blog-1');
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.list' });
    });
});
