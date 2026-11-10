/**
 * @sw-package discovery
 */
import { flushPromises, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';
import { registerBlogType, resetBlogTypes } from '../../service/blog-type.registry';

type BlogRow = {
    id: string;
    title: string | null;
    slug: string | null;
    type: string;
    active: boolean;
    publishedAt: string | null;
    updatedAt?: string | null;
    categories?: Array<{ blogCategory?: { name: string } | null }>;
    author?: { name: string } | null;
};

type FilterDefinition = { name?: string; property: string; type: string };

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
        active: false,
        publishedAt: null,
        updatedAt: '2026-10-01T10:00:00.000Z',
        categories: [
            { blogCategory: { name: 'Guides' } },
            { blogCategory: { name: 'News' } },
            { blogCategory: { name: 'Shop' } },
        ],
        author: { name: 'Grace' },
    },
    {
        id: 'blog-2',
        title: 'Behind the scenes',
        slug: 'behind-the-scenes',
        type: 'video',
        active: true,
        publishedAt: '2999-01-01T00:00:00.000Z',
        updatedAt: null,
    },
];

const categories = [{ id: 'category-1', name: 'Guides' }];
const authors = [{ id: 'author-1', name: 'Grace' }];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    can: (privilege: string) => boolean = () => true,
    query: Record<string, string> = {},
): Promise<{
    wrapper: VueWrapper;
    search: jest.Mock;
    save: jest.Mock;
    remove: jest.Mock;
    push: jest.Mock;
    mergeWithStoredFilters: jest.Mock;
    categorySearch: jest.Mock;
    authorSearch: jest.Mock;
}> {
    const rows = blogs.map((row) => ({ ...row }));
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'blog'>>(rows)));
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

    const wrapper = shallowMount(await wrapTestComponent('sw-blog-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"/><slot name="smart-bar-actions"/><slot name="content"/><slot name="sidebar"/></div>',
                },
                'sw-search-bar': true,
                'sw-entity-listing': {
                    props: [
                        'dataSource',
                        'repository',
                        'allowDelete',
                        'allowInlineEdit',
                        'allowView',
                    ],
                    emits: ['inline-edit-save'],
                    template: `<table><tbody><tr v-for="item in dataSource" :key="item.id" :data-row="item.id">
                        <td><slot name="column-coverMediaId" :item="item" /></td>
                        <td><a v-if="allowView" class="detail-link" :href="'/sw/blog/' + item.id">{{ item.title }}</a><slot name="column-title" :item="item" :is-inline-edit="false">{{ item.title }}</slot>
                            <input v-if="allowInlineEdit" class="inline-title" :value="item.title" @input="item.title = $event.target.value" />
                            <button v-if="allowInlineEdit" class="inline-save" @click="$emit('inline-edit-save', item)">Save row</button>
                        </td>
                        <td class="type"><slot name="column-type" :item="item" /></td><td class="updated"><slot name="column-updatedAt" :item="item" /></td>
                        <td><slot name="column-categories" :item="item" /></td>
                        <td><slot name="column-status" :item="item" :is-inline-edit="false" /></td>
                        <td class="delete-action"><slot name="delete-action" :item="item"><button :disabled="!allowDelete">Delete</button></slot></td>
                    </tr></tbody></table>`,
                },
                'mt-popover': {
                    template:
                        '<div><slot name="trigger" :toggle-floating-ui="() => {}" /><slot name="popover-items__base" :toggle-floating-ui="() => {}" /></div>',
                },
                'mt-popover-item': {
                    props: ['label', 'disabled', 'onLabelClick'],
                    template: '<button :disabled="disabled" @click="onLabelClick">{{ label }}</button>',
                },
                'mt-icon': true,
                'sw-sidebar': { template: '<aside><slot /></aside>' },
                'sw-sidebar-item': { props: ['title'], template: '<button>{{ title }}</button>' },
                'sw-sidebar-filter-panel': {
                    props: ['filters', 'activeFilterNumber'],
                    template: `<div class="filter-panel" :data-active-count="activeFilterNumber"><span v-for="filter in filters" :key="filter.property" class="filter-property">{{ filter.property }}</span></div>`,
                },
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-media-preview-v2': true,
                'sw-time-ago': { props: ['date'], template: '<time>{{ date }}</time>' },
                'mt-badge': { template: '<span class="badge"><slot /></span>' },
                'sw-blog-status-badge': true,
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'sw-context-menu-item': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
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
    await flushPromises();

    return { wrapper, search, save, remove, push, mergeWithStoredFilters, categorySearch, authorSearch };
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

    it('only renders a relative update time when the entry has been updated', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        expect(wrapper.get('[data-row="blog-1"] time').text()).toBe('2026-10-01T10:00:00.000Z');
        expect(wrapper.find('[data-row="blog-2"] time').exists()).toBe(false);
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
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(2)');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    });

    it('merges the stored filters before it searches, and counts them for the panel', async () => {
        const { wrapper, mergeWithStoredFilters } = await createWrapper();
        await flushPromises();

        expect(mergeWithStoredFilters).toHaveBeenCalledWith('grid.filter.blog', expect.anything());
        expect(wrapper.get('.filter-panel').attributes('data-active-count')).toBe('0');
    });

    it('offers status, type, categories, authors and native tags as filters', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('.filter-property').map((filter) => filter.text())).toEqual([
            'active',
            'type',
            'categories.blogCategory',
            'author',
            'tags',
        ]);
    });

    it('narrows the list to the author the page was opened with', async () => {
        const { wrapper, search } = await createWrapper(() => true, { authorId: 'author-1' });
        await flushPromises();

        const criteria = callArgument<{ filters: Array<{ type: string; field: string; value: string }> }>(search);

        expect(wrapper.find('.sw-blog-list__preset-badge').exists()).toBe(true);
        expect(criteria.filters).toEqual([
            expect.objectContaining({ type: 'equals', field: 'authorId', value: 'author-1' }),
        ]);
        expect(wrapper.find('.sw-blog-list__preset-reset').exists()).toBe(true);
    });

    it('keeps moderation out of the entry list', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        // the queue lives on its own page, like the customer group registration review
        expect(wrapper.find('.sw-blog-comment-list__status').exists()).toBe(false);
        expect(wrapper.find('.sw-blog-comment-list__action-approve').exists()).toBe(false);
    });

    it('hides creating and deleting from a role that may only read', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege === 'blog.viewer');

        expect(wrapper.get('.sw-blog-list__button-create').attributes('disabled')).toBeDefined();
        expect(wrapper.findAll('.delete-action button').every((button) => button.attributes('disabled') !== undefined)).toBe(
            true,
        );
        expect(wrapper.find('.inline-title').exists()).toBe(false);
    });

    it('opens the create page instead of asking for anything in a dialog', async () => {
        const { wrapper, push, save } = await createWrapper();

        await wrapper.get('[data-blog-type="post"]').trigger('click');
        await flushPromises();

        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.create', query: { type: 'post' } });
        // nothing is written before the entry page is saved
        expect(save).not.toHaveBeenCalled();
    });

    it('labels the type column from the snippet and falls back to the raw value', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        // $te answers false in the tests, so the raw value is the label
        expect(wrapper.get('[data-row="blog-2"] .type').text()).toBe('video');
    });

    it('deletes only after the confirmation of the modal', async () => {
        const { wrapper, remove } = await createWrapper();

        await wrapper.get('[data-row="blog-1"] .delete-action button').trigger('click');

        expect(wrapper.find('.sw-blog-list__delete-modal').exists()).toBe(true);
        expect(remove).not.toHaveBeenCalled();

        await wrapper.findAll('.sw-blog-list__delete-modal button')[1].trigger('click');
        await flushPromises();

        expect(remove).toHaveBeenCalledWith('blog-1');
        expect(wrapper.find('.sw-blog-list__delete-modal').exists()).toBe(false);
    });

    it('opens details for viewers and keeps inline title editing disabled', async () => {
        const { wrapper, save } = await createWrapper((privilege) => privilege === 'blog.viewer');
        expect(wrapper.get('[data-row="blog-1"] .detail-link').attributes('href')).toBe('/sw/blog/blog-1');
        expect(wrapper.find('.inline-title').exists()).toBe(false);
        expect(save).not.toHaveBeenCalled();
    });

    it('creates videos and plugin types directly from the type menu', async () => {
        registerBlogType('podcast', () => Promise.resolve({ default: { name: 'podcast' } }));
        const { wrapper, push } = await createWrapper();
        await wrapper.get('[data-blog-type="video"]').trigger('click');
        expect(push).toHaveBeenLastCalledWith({ name: 'sw.blog.create', query: { type: 'video' } });
        await wrapper.get('[data-blog-type="podcast"]').trigger('click');
        expect(push).toHaveBeenLastCalledWith({ name: 'sw.blog.create', query: { type: 'podcast' } });
    });

    it('loads category and author options on demand through entity filters', async () => {
        const { categorySearch, authorSearch } = await createWrapper();
        expect(categorySearch).not.toHaveBeenCalled();
        expect(authorSearch).not.toHaveBeenCalled();
    });

    it('turns stored draft status into an active flag filter before searching', async () => {
        const { wrapper, search, mergeWithStoredFilters } = await createWrapper();
        mergeWithStoredFilters.mockImplementation((key: string, criteria: { addFilter: (filter: unknown) => void }) => {
            criteria.addFilter(Shopwell.Data.Criteria.equalsAny('active', ['draft']));
            return Promise.resolve(criteria);
        });
        search.mockClear();
        await wrapper.get('aside button').trigger('click');
        await flushPromises();
        expect(callArgument<{ filters: unknown[] }>(search).filters).toEqual([
            expect.objectContaining({
                type: 'multi',
                operator: 'OR',
                queries: [
                    expect.objectContaining({
                        type: 'multi',
                        operator: 'AND',
                        queries: [
                            expect.objectContaining({ type: 'equals', field: 'active', value: false }),
                        ],
                    }),
                ],
            }),
        ]);
    });

    it('names the categories of a row, so the column can badge the first two', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('[data-row="blog-1"] .sw-blog-list__categories .badge').map((badge) => badge.text())).toEqual(
            ['Guides', 'News'],
        );
        expect(wrapper.get('.sw-blog-list__categories-more').text()).toBe('+1');
        expect(wrapper.get('[data-row="blog-2"] .sw-blog-list__categories-empty').text()).toBe(
            'sw-blog.list.textNoCategories',
        );
    });

    it('keeps only refresh and filters in the sidebar', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.findAll('aside button')).toHaveLength(1);
        expect(wrapper.findAll('aside button')[0].text()).toBe('sw-blog.list.buttonRefresh');
    });
    it('shows the list results without duplicating the main menu', async () => {
        const { wrapper } = await createWrapper();
        const content = wrapper.get('.sw-blog-list__content');
        expect(content.find('sw-blog-navigation-stub').exists()).toBe(false);
        expect(content.find('.sw-blog-list__results').exists()).toBe(true);
        expect(content.find('.sw-blog-list__results sw-blog-navigation-stub').exists()).toBe(false);
    });
});
