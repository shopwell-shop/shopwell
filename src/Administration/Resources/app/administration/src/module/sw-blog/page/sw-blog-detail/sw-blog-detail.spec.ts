/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

type Association = { association: string; criteria: { associations: Association[] } };

/**
 * The names of the associations a criteria reads. `addAssociation` stores an association object, not
 * the bare name, and a nested path becomes a nested association.
 */
function associationNames(criteria: { associations: unknown }, prefix: string = ''): string[] {
    return (criteria.associations as Association[]).flatMap((association) => {
        const name = `${prefix}${association.association}`;

        return [name, ...associationNames(association.criteria, `${name}.`)];
    });
}

type DetailVm = {
    blog: Record<string, unknown> | null;
    video: Record<string, unknown> | null;
    cmsPage: Record<string, unknown> | null;
    assignments: Array<{ id: string; blogCategoryId: string | null }>;
    selectedCategoryIds: string[];
    categories: unknown[];
    pendingCommentCount: number;
    isLoading: boolean;
    isSaving: boolean;
    showDeleteModal: boolean;
    showCategoryModal: boolean;
    showAuthorModal: boolean;
    newCategoryName: string;
    newAuthorName: string;
    canEdit: boolean;
    canDelete: boolean;
    isTitleMissing: boolean;
    isCreateCategoryDisabled: boolean;
    isCreateAuthorDisabled: boolean;
    title: string;
    loadBlog: () => Promise<void>;
    ensureVideo: () => void;
    isCategorySelected: (categoryId: string) => boolean;
    onToggleCategory: (categoryId: string) => void;
    onOpenCategoryModal: () => void;
    onCreateCategory: () => void;
    onOpenAuthorModal: () => void;
    onCreateAuthor: () => void;
    onPublishNow: () => void;
    onClearSchedule: () => void;
    onCoverSelected: (media: Array<{ id: string }>) => void;
    onCoverRemoved: () => void;
    onSave: (redirect?: boolean) => Promise<void>;
    onCancel: () => void;
    onOpenDeleteModal: () => void;
    onCloseDeleteModal: () => void;
    onDelete: () => void;
    abortOnLanguageChange: () => boolean;
    saveOnLanguageChange: () => Promise<void>;
    onChangeLanguage: (languageId: string) => void;
};

type TypedWrapper = { vm: DetailVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

const blog = {
    id: 'blog-1',
    type: 'post',
    title: 'Autumn guide',
    slug: 'autumn-guide',
    teaser: 'Teaser',
    content: '<p>Body</p>',
    metaTitle: null,
    metaDescription: null,
    publishedAt: null,
    authorId: 'author-1',
    coverMediaId: null,
    cmsPageId: 'page-1',
    categories: [],
    videos: [],
};

const assignments = [{ id: 'assignment-1', blogId: 'blog-1', blogCategoryId: 'category-1' }];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    options: {
        can?: (privilege: string) => boolean;
        entry?: Record<string, unknown>;
        hasChanges?: boolean;
    } = {},
): Promise<{
    wrapper: TypedWrapper;
    getBlog: jest.Mock;
    saveBlog: jest.Mock;
    deleteBlog: jest.Mock;
    saveVideo: jest.Mock;
    saveAssignment: jest.Mock;
    deleteAssignment: jest.Mock;
    push: jest.Mock;
}> {
    const { can = () => true, entry = blog, hasChanges = false } = options;

    const saveBlog = jest.fn(() => Promise.resolve());
    const deleteBlog = jest.fn(() => Promise.resolve());
    const getBlog = jest.fn(() => Promise.resolve({ ...entry }));
    const saveVideo = jest.fn(() => Promise.resolve());
    const saveAssignment = jest.fn(() => Promise.resolve());
    const deleteAssignment = jest.fn(() => Promise.resolve());
    const push = jest.fn(() => Promise.resolve());

    const blogRepository = {
        get: getBlog,
        save: saveBlog,
        delete: deleteBlog,
        hasChanges: jest.fn(() => hasChanges),
        create: () => {
            return { ...blog };
        },
    };

    const videoRepository = {
        get: jest.fn(() => Promise.resolve(null)),
        save: saveVideo,
        delete: jest.fn(() => Promise.resolve()),
        create: () => {
            return {
                id: 'video-new',
                blogId: null,
                videoUrl: '',
                durationSeconds: null,
                posterMediaId: null,
                provider: null,
            };
        },
    };

    const assignmentRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_category_assignment'>>(assignments))),
        save: saveAssignment,
        delete: deleteAssignment,
        create: () => {
            return { id: 'assignment-new', blogId: null, blogCategoryId: null };
        },
    };

    const cmsPageRepository = {
        get: jest.fn(() => Promise.resolve({ id: 'page-1', versionId: 'version-1' })),
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'cms_page'>>([]))),
    };

    const categoryRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_category'>>([]))),
        save: jest.fn(() => Promise.resolve()),
        create: () => {
            return { id: 'category-new', name: null, slug: null, active: true };
        },
    };

    const authorRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_author'>>([]))),
        save: jest.fn(() => Promise.resolve()),
        create: () => {
            return { id: 'author-new', name: null, active: true };
        },
    };

    const commentRepository = {
        search: jest.fn(() => Promise.resolve(Object.assign([], { total: 2 }))),
    };

    const wrapper = mount(await wrapTestComponent('sw-blog-detail', { sync: true }), {
        props: { blogId: 'blog-1' },
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot><slot name="sidebar"></slot><slot></slot></div>' },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': { template: '<div><slot name="toolbar"></slot><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-sidebar': { template: '<div><slot></slot></div>' },
                'sw-sidebar-item': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-language-switch': true,
                'sw-media-compact-upload-v2': true,
                'sw-entity-single-select': true,
                'sw-entity-multi-id-select': true,
                'sw-cms-page-form': true,
                'sw-blog-detail-content': true,
                'sw-blog-detail-comments': true,
                'sw-blog-status-badge': true,
                'mt-text-field': true,
                'mt-textarea': true,
                'mt-select': true,
                'mt-switch': true,
                'mt-checkbox': true,
                'mt-badge': true,
                'mt-datepicker': true,
                'mt-button': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entityName: string) => {
                        if (entityName === 'blog_video') {
                            return videoRepository;
                        }

                        if (entityName === 'blog_category_assignment') {
                            return assignmentRepository;
                        }

                        if (entityName === 'cms_page') {
                            return cmsPageRepository;
                        }

                        if (entityName === 'blog_category') {
                            return categoryRepository;
                        }

                        if (entityName === 'blog_author') {
                            return authorRepository;
                        }

                        if (entityName === 'blog_comment') {
                            return commentRepository;
                        }

                        return blogRepository;
                    },
                },
                acl: { can },
                blogApiService: new BlogApiService(),
            },
            mocks: {
                $route: { name: 'sw.blog.detail', meta: { $module: { icon: 'regular-image-text' } } },
                $router: { push, replace: jest.fn(() => Promise.resolve()) },
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return {
        wrapper: wrapper as unknown as TypedWrapper,
        getBlog,
        saveBlog,
        deleteBlog,
        saveVideo,
        saveAssignment,
        deleteAssignment,
        push,
    };
}

describe('src/module/sw-blog/page/sw-blog-detail', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.clearAllMocks();
    });

    it('loads the entry, the categories it is filed under and its layout', async () => {
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
        expect(wrapper.vm.blog?.title).toBe('Autumn guide');
        expect(wrapper.vm.assignments).toHaveLength(1);
        expect(wrapper.vm.selectedCategoryIds).toEqual(['category-1']);
        expect(wrapper.vm.cmsPage?.id).toBe('page-1');
        expect(wrapper.vm.isLoading).toBe(false);
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

        expect(saveBlog).toHaveBeenCalledWith(
            expect.objectContaining({ publishedAt: expect.stringMatching(/^\d{4}-\d{2}-\d{2}T/) }),
        );
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
