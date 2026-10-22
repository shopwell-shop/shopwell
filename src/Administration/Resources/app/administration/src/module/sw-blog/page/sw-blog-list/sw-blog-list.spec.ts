/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';
import { registerBlogType, resetBlogTypes } from '../../service/blog-type.registry';

type BlogRow = {
    id: string;
    title: string | null;
    slug: string | null;
    type: string;
    publishedAt: string | null;
    updatedAt?: string | null;
    categories?: Array<{ blogCategory?: { name: string } | null }>;
    author?: { name: string } | null;
};

type FilterDefinition = { name?: string; property: string; type: string };

type BlogListVm = {
    blogs: unknown;
    total: number;
    isLoading: boolean;
    listFilters: FilterDefinition[];
    activeFilterNumber: number;
    pendingCommentCount: number;
    presetAuthorId: string | null;
    hasActiveSearchOrFilter: boolean;
    deleteTarget: { id: string; name: string | null } | null;
    showCreateModal: boolean;
    newBlogTitle: string;
    canCreate: boolean;
    canDelete: boolean;
    canEdit: boolean;
    isCreateDisabled: boolean;
    getList: () => Promise<void>;
    loadCategories: () => Promise<void>;
    loadAuthors: () => Promise<void>;
    updateCriteria: (filters: unknown[]) => void;
    onRefresh: () => void;
    onOpenCreateModal: () => void;
    onCreateBlog: () => void;
    onInlineEditSave: (blog: BlogRow) => void;
    onDelete: (blog: BlogRow) => void;
    onConfirmDelete: () => void;
    onOpenCategoryList: () => void;
    onOpenCommentList: () => void;
    categoryNamesOf: (blog: BlogRow) => string[];
};

type TypedWrapper = { vm: BlogListVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

function callArgument<T>(mock: jest.Mock, call = 0, argument = 0): T {
    const calls = mock.mock.calls as unknown as T[][];

    return calls[call][argument];
}

type Association = { association: string; criteria: { associations: Association[] } };

function associationNames(criteria: { associations: unknown }, prefix: string = ''): string[] {
    return (criteria.associations as Association[]).flatMap((association) => {
        const name = `${prefix}${association.association}`;

        return [name, ...associationNames(association.criteria, `${name}.`)];
    });
}

const blogs: BlogRow[] = [
    {
        id: 'blog-1',
        title: 'Autumn guide',
        slug: 'autumn-guide',
        type: 'post',
        publishedAt: null,
        updatedAt: '2026-10-01T10:00:00.000Z',
        categories: [{ blogCategory: { name: 'Guides' } }, { blogCategory: { name: 'News' } }, { blogCategory: { name: 'Shop' } }],
        author: { name: 'Grace' },
    },
    {
        id: 'blog-2',
        title: 'Behind the scenes',
        slug: 'behind-the-scenes',
        type: 'video',
        publishedAt: '2999-01-01T00:00:00.000Z',
        updatedAt: '2026-10-02T10:00:00.000Z',
    },
];

const categories = [{ id: 'category-1', name: 'Guides' }];
const authors = [{ id: 'author-1', name: 'Grace' }];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    can: (privilege: string) => boolean = () => true,
    query: Record<string, string> = {},
): Promise<{
    wrapper: TypedWrapper;
    search: jest.Mock;
    save: jest.Mock;
    remove: jest.Mock;
    push: jest.Mock;
    mergeWithStoredFilters: jest.Mock;
}> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'blog'>>(blogs)));
    const categorySearch = jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_category'>>(categories)));
    const authorSearch = jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_author'>>(authors)));
    const commentSearch = jest.fn(() => Promise.resolve(Object.assign([], { total: 3 })));

    const save = jest.fn(() => Promise.resolve());
    const remove = jest.fn(() => Promise.resolve());
    const push = jest.fn(() => Promise.resolve());

    const blogRepository = {
        search,
        save,
        delete: remove,
        create: () => {
            return { id: 'blog-new', title: null, slug: null, type: 'post' };
        },
    };

    const repositoryFactory = {
        create: (entityName: string) => {
            if (entityName === 'blog_category') {
                return { search: categorySearch };
            }

            if (entityName === 'blog_author') {
                return { search: authorSearch };
            }

            if (entityName === 'blog_comment') {
                return { search: commentSearch };
            }

            return blogRepository;
        },
    };

    // the panel merges the stored filters into the criteria before the search runs; the page has to
    // await that, and the test hands the criteria back untouched
    const mergeWithStoredFilters = jest.fn((storeKey: string, criteria: unknown) => Promise.resolve(criteria));

    jest.spyOn(Shopwell, 'Service').mockImplementation(((name: string) => {
        if (name === 'filterService') {
            return { mergeWithStoredFilters };
        }

        return {};
    }) as unknown as typeof Shopwell.Service);

    const wrapper = mount(await wrapTestComponent('sw-blog-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot><slot></slot></div>' },
                'sw-search-bar': true,
                'sw-entity-listing': true,
                'sw-sidebar': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-media-preview-v2': true,
                'sw-time-ago': true,
                'mt-badge': true,
                'sw-blog-status-badge': true,
                'mt-button': true,
                'mt-text-field': true,
                'mt-empty-state': true,
            },
            provide: {
                repositoryFactory,
                acl: { can },
                blogApiService: new BlogApiService(),
                filterFactory: {
                    create: (entityName: string, filters: Record<string, FilterDefinition>) => {
                        return Object.entries(filters).map(([name, filter]) => ({ ...filter, name }));
                    },
                },
                searchRankingService: {
                    isValidTerm: () => true,
                    getSearchFieldsByEntity: () => Promise.resolve({}),
                    buildSearchQueriesForEntity: () => Promise.resolve([]),
                },
                feature: { isActive: () => false },
            },
            mocks: {
                $route: { name: 'sw.blog.list', query, meta: { $module: { icon: 'regular-image-text' } } },
                $router: { push, replace: jest.fn(() => Promise.resolve()) },
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return { wrapper: wrapper as unknown as TypedWrapper, search, save, remove, push, mergeWithStoredFilters };
}

describe('src/module/sw-blog/page/sw-blog-list', () => {
    beforeEach(() => {
        registerBlogType('post', () => Promise.resolve({ default: { name: 'post' } }));
        registerBlogType('video', () => Promise.resolve({ default: { name: 'video' } }));
    });

    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        resetBlogTypes();
        jest.restoreAllMocks();
        jest.clearAllMocks();
    });

    it('reads the entries newest publication first, with author and categories', async () => {
        const { wrapper, search } = await createWrapper();
        await flushPromises();

        const criteria = callArgument<{
            sortings: Array<{ field: string; order: string }>;
            associations: unknown[];
        }>(search);

        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'publishedAt', order: 'DESC' }),
            expect.objectContaining({ field: 'createdAt', order: 'DESC' }),
        ]);
        expect(associationNames(criteria)).toEqual(['author', 'categories', 'categories.blogCategory']);
        expect(wrapper.vm.total).toBe(2);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('merges the stored filters before it searches, and counts them for the panel', async () => {
        const { wrapper, mergeWithStoredFilters } = await createWrapper();
        await flushPromises();

        expect(mergeWithStoredFilters).toHaveBeenCalledWith('grid.filter.blog', expect.anything());
        expect(wrapper.vm.activeFilterNumber).toBe(0);
    });

    it('offers type, category and author as filters of the sidebar panel', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.listFilters.map((filter) => filter.property)).toEqual([
            'type',
            'categories.blogCategoryId',
            'authorId',
        ]);
    });

    it('narrows the list to the author the page was opened with', async () => {
        const { wrapper, search } = await createWrapper(() => true, { authorId: 'author-1' });
        await flushPromises();

        const criteria = callArgument<{ filters: Array<{ type: string; field: string; value: string }> }>(search);

        expect(wrapper.vm.presetAuthorId).toBe('author-1');
        expect(criteria.filters).toEqual([
            expect.objectContaining({ type: 'equals', field: 'authorId', value: 'author-1' }),
        ]);
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(true);
    });

    it('counts the comments that wait, for the door to the moderation queue', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.pendingCommentCount).toBe(3);
    });

    it('hides creating and deleting from a role that may only read', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege === 'blog.viewer');

        expect(wrapper.vm.canCreate).toBe(false);
        expect(wrapper.vm.canDelete).toBe(false);
        expect(wrapper.vm.canEdit).toBe(false);
    });

    it('creates an entry with a generated slug and opens it', async () => {
        const { wrapper, save, push } = await createWrapper();

        wrapper.vm.onOpenCreateModal();
        wrapper.vm.newBlogTitle = 'Autumn Guide';

        expect(wrapper.vm.isCreateDisabled).toBe(false);

        wrapper.vm.onCreateBlog();
        await flushPromises();

        const created = callArgument<{ slug: string; type: string; title: string }>(save);

        expect(created.title).toBe('Autumn Guide');
        expect(created.type).toBe('post');
        expect(created.slug).toMatch(/^autumn-guide-[a-z0-9]+$/);
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.detail', params: { id: 'blog-new' } });
    });

    it('refuses to create an entry without a title', async () => {
        const { wrapper, save } = await createWrapper();

        wrapper.vm.onOpenCreateModal();
        wrapper.vm.newBlogTitle = '   ';
        wrapper.vm.onCreateBlog();

        expect(wrapper.vm.isCreateDisabled).toBe(true);
        expect(save).not.toHaveBeenCalled();
    });

    it('deletes only after the confirmation of the modal', async () => {
        const { wrapper, remove } = await createWrapper();

        wrapper.vm.onDelete(blogs[0]);

        expect(wrapper.vm.deleteTarget).toEqual({ id: 'blog-1', name: 'Autumn guide' });
        expect(remove).not.toHaveBeenCalled();

        wrapper.vm.onConfirmDelete();

        expect(remove).toHaveBeenCalledWith('blog-1');
        expect(wrapper.vm.deleteTarget).toBeNull();
    });

    it('saves an inline edit of a row', async () => {
        const { wrapper, save } = await createWrapper();

        wrapper.vm.onInlineEditSave({ ...blogs[0], title: 'Autumn guide 2026' });

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'blog-1', title: 'Autumn guide 2026' }));
    });

    it('names the categories of a row, so the column can badge the first two', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.categoryNamesOf(blogs[0])).toEqual(['Guides', 'News', 'Shop']);
        expect(wrapper.vm.categoryNamesOf(blogs[1])).toEqual([]);
    });

    it('opens the category list and the comment queue from the sidebar', async () => {
        const { wrapper, push } = await createWrapper();

        wrapper.vm.onOpenCategoryList();
        wrapper.vm.onOpenCommentList();

        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.category.list' });
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.comment.list' });
    });
});
