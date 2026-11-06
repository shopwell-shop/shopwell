/**
 * @sw-package discovery
 */
import { flushPromises, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

type CommentRow = {
    id: string;
    title: string | null;
    content: string;
    status: boolean;
    externalUser: string | null;
    customerId: string | null;
    blogId: string;
    blog: { translated: { title: string } } | null;
};

type FilterDefinition = { name?: string; property: string; type?: string };

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

function callArgument<T>(mock: jest.Mock, call = 0, argument = 0): T {
    const calls = mock.mock.calls as unknown as T[][];

    return calls[call][argument];
}

const comments: CommentRow[] = [
    {
        id: 'comment-1',
        title: null,
        content: 'When does the restock arrive?  '.repeat(4).trim(),
        status: false,
        externalUser: 'Ada',
        customerId: null,
        blogId: 'blog-1',
        blog: { translated: { title: 'Winter guide' } },
    },
    {
        id: 'comment-2',
        title: 'Thank you',
        content: 'Great article.',
        status: true,
        externalUser: null,
        customerId: 'customer-1',
        blogId: 'blog-2',
        blog: null,
    },
];

const customers = [{ id: 'customer-1', name: 'Grace Lee', email: 'grace@example.com' }];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(can: (privilege: string) => boolean = () => true): Promise<{
    wrapper: VueWrapper;
    search: jest.Mock;
    customerSearch: jest.Mock;
    save: jest.Mock;
    remove: jest.Mock;
    push: jest.Mock;
    mergeWithStoredFilters: jest.Mock;
}> {
    const rows = comments.map((row) => ({ ...row }));
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_comment'>>(rows)));
    const customerSearch = jest.fn(() => Promise.resolve(collection<EntityCollection<'customer'>>(customers)));
    const save = jest.fn(() => Promise.resolve());
    const remove = jest.fn(() => Promise.resolve());
    const push = jest.fn(() => Promise.resolve());

    const repositoryFactory = {
        create: (entityName: string) => {
            if (entityName === 'customer') {
                return { search: customerSearch };
            }

            return { search, save, delete: remove };
        },
    };

    // the panel stores the filters and then hands the criteria to the page, which merges what the
    // panel stored - the mock does the same, so a filter the test sets is the filter that is counted
    let storedFilters: unknown[] = [];
    const mergeWithStoredFilters = jest.fn((storeKey: string, criteria: { filters: unknown[] }) => {
        criteria.filters.push(...storedFilters);

        return Promise.resolve(criteria);
    });

    jest.spyOn(Shopwell, 'Service').mockImplementation(((name: string) => {
        if (name === 'filterService') {
            return { mergeWithStoredFilters };
        }

        return {};
    }) as unknown as typeof Shopwell.Service);

    const wrapper = shallowMount(await wrapTestComponent('sw-blog-comment-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"/><slot name="smart-bar-actions"/><slot name="content"/><slot name="sidebar"/></div>',
                },
                'sw-search-bar': true,
                // the listing owns the selection in the real administration, so the stub has to own
                // one too - the page reads it from the ref
                'sw-entity-listing': {
                    props: ['dataSource', 'allowDelete', 'showSelection'],
                    data() {
                        return { selection: {} as Record<string, CommentRow> };
                    },
                    template: `<div><table><tbody><tr v-for="item in dataSource" :key="item.id" :data-row="item.id">
                        <td><input v-if="showSelection" type="checkbox" :checked="Boolean(selection[item.id])" @change="$event.target.checked ? selection[item.id] = item : delete selection[item.id]" /></td>
                        <td class="title"><slot name="column-title" :item="item" /></td>
                        <td class="content"><slot name="column-content" :item="item" /></td>
                        <td class="blog"><slot name="column-blog" :item="item" /></td>
                        <td class="author"><slot name="column-author" :item="item" /></td>
                        <td><slot name="column-status" :item="item" /></td>
                        <td><slot name="more-actions" :item="item" /></td>
                        <td class="delete-action"><slot name="delete-action" :item="item"><button :disabled="!allowDelete">Delete</button></slot></td>
                    </tr></tbody></table><slot name="bulk-additional" :selection-count="Object.keys(selection).length" /></div>`,
                },
                'sw-sidebar': { template: '<aside><slot /></aside>' },
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': {
                    props: ['filters', 'activeFilterNumber'],
                    emits: ['criteria-changed'],
                    methods: {
                        applyFilter() {
                            storedFilters = [{ field: 'status', value: false, type: 'equals' }];
                        },
                    },
                    template: `<div class="filter-panel" :data-active-count="activeFilterNumber"><span v-for="filter in filters" :key="filter.property" class="filter-property" :data-filter-type="filter.type">{{ filter.property }}</span><select class="status-filter" @change="applyFilter(); $emit('criteria-changed', [{field: 'status'}])"><option value="">All</option><option value="pending">Pending</option></select></div>`,
                },
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-time-ago': true,
                'sw-context-menu-item': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-badge': { props: ['variant'], template: '<span :data-variant="variant"><slot /></span>' },
                'mt-tooltip': { template: '<span><slot></slot></span>', props: ['content'] },
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
                // the listing mixin reads the search configuration of the entity, which is served
                // by the backend in the real administration
                searchRankingService: {
                    isValidTerm: () => true,
                    getSearchFieldsByEntity: () => Promise.resolve({}),
                    buildSearchQueriesForEntity: () => Promise.resolve([]),
                },
                feature: { isActive: () => false },
            },
            mocks: {
                $route: { name: 'sw.blog.comment.list', meta: { $module: { icon: 'regular-image-text' } } },
                $router: { push, replace: jest.fn(() => Promise.resolve()) },
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;
    await flushPromises();

    return {
        wrapper,
        search,
        customerSearch,
        save,
        remove,
        push,
        mergeWithStoredFilters,
    };
}

describe('src/module/sw-blog/page/sw-blog-comment-list', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.restoreAllMocks();
        jest.clearAllMocks();
    });

    it('reads the comments newest first and merges the stored filters before it searches', async () => {
        const { wrapper, search, mergeWithStoredFilters } = await createWrapper();
        await flushPromises();

        const criteria = callArgument<{ sortings: Array<{ field: string; order: string }> }>(search);

        expect(mergeWithStoredFilters).toHaveBeenCalledTimes(1);
        expect(mergeWithStoredFilters).toHaveBeenCalledWith('grid.filter.blog_comment', expect.anything());
        expect(criteria.sortings).toEqual([expect.objectContaining({ field: 'createdAt', order: 'DESC' })]);
        expect(wrapper.get('.filter-panel').attributes('data-active-count')).toBe('0');
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(2)');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    });

    it('offers the moderation state and the entry as filters of the sidebar panel', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('.filter-property').map((filter) => filter.text())).toEqual(['status', 'blog']);
        expect(wrapper.findAll('.filter-property').map((filter) => filter.attributes('data-filter-type'))).toEqual([
            'boolean-filter',
            undefined,
        ]);
    });

    it('starts the list over on page one when a filter changes', async () => {
        const { wrapper, search } = await createWrapper();
        await flushPromises();

        await wrapper.get('.status-filter').setValue('pending');
        await flushPromises();

        expect(wrapper.get('.filter-panel').attributes('data-active-count')).toBe('1');
        expect(search).toHaveBeenLastCalledWith(
            expect.objectContaining({ page: 1, filters: [expect.objectContaining({ field: 'status', value: false })] }),
        );
        expect(search.mock.calls).toHaveLength(2);
    });

    it('names the guest, the customer and the entry of a comment', async () => {
        const { wrapper, customerSearch } = await createWrapper();
        await flushPromises();

        // the customer is read in a second request: the comment has no association into that domain
        expect(customerSearch).toHaveBeenCalledTimes(1);
        expect(wrapper.get('[data-row="comment-2"] .author').text()).toBe('Grace Lee');

        expect(wrapper.get('[data-row="comment-1"] .author').text()).toBe('Ada');
        expect(wrapper.get('[data-row="comment-1"] .blog').text()).toBe('Winter guide');
        // an entry that is gone does not blank the row
        expect(wrapper.get('[data-row="comment-2"] .blog').text()).toBe('blog-2');
    });

    it('stands in the first words of the text when a comment has no headline', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.get('[data-row="comment-2"] .title').text()).toBe('Thank you');

        // the column shows a headline, so the stand-in is shorter than the cell of the text
        const flat = comments[0].content.replace(/\s+/g, ' ').trim();

        expect(wrapper.get('[data-row="comment-1"] .title').text()).toBe(`${flat.slice(0, 60)}…`);
    });

    it('approves a single comment and reloads the list', async () => {
        const { wrapper, search, save } = await createWrapper();
        await flushPromises();

        await wrapper.get('[data-row="comment-1"] .sw-blog-comment-list__action-approve').trigger('click');
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'comment-1', status: true }));
        // the second search is the reload after the write
        expect(search.mock.calls).toHaveLength(2);
    });

    it('hides a comment that was already approved', async () => {
        const { wrapper, save } = await createWrapper();
        await flushPromises();

        await wrapper.get('[data-row="comment-2"] .sw-blog-comment-list__action-hide').trigger('click');
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'comment-2', status: false }));
    });

    it('takes the selection of the listing for the bulk action', async () => {
        const { wrapper, save } = await createWrapper();
        await flushPromises();

        await wrapper.get('[data-row="comment-1"] input[type="checkbox"]').setValue(true);
        await wrapper.get('.sw-blog-comment-list__bulk-approve').trigger('click');
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'comment-1', status: true }));
    });

    it('hides moderation and deleting from a role that may only read', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege === 'blog.viewer');

        expect(wrapper.find('.sw-blog-comment-list__action-approve').exists()).toBe(false);
        expect(wrapper.find('.sw-blog-comment-list__action-hide').exists()).toBe(false);
        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);
        expect(wrapper.findAll('.delete-action button').every((button) => button.attributes('disabled') !== undefined)).toBe(
            true,
        );
    });

    it('deletes only after the confirmation of the modal', async () => {
        const { wrapper, remove } = await createWrapper();
        await flushPromises();

        await wrapper.get('[data-row="comment-1"] .delete-action button').trigger('click');

        expect(wrapper.find('.sw-blog-comment-list__delete-modal').exists()).toBe(true);
        expect(remove).not.toHaveBeenCalled();

        await wrapper.findAll('.sw-blog-comment-list__delete-modal button')[1].trigger('click');
        await flushPromises();

        expect(remove).toHaveBeenCalledWith('comment-1');
        expect(wrapper.find('.sw-blog-comment-list__delete-modal').exists()).toBe(false);
    });

    it('does not delete anything when the modal is closed', async () => {
        const { wrapper, remove } = await createWrapper();
        await flushPromises();

        await wrapper.get('[data-row="comment-1"] .delete-action button').trigger('click');
        await wrapper.findAll('.sw-blog-comment-list__delete-modal button')[0].trigger('click');

        expect(wrapper.find('.sw-blog-comment-list__delete-modal').exists()).toBe(false);
        expect(remove).not.toHaveBeenCalled();
    });

    it('opens the entry list from the toolbar', async () => {
        const { wrapper, push } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-comment-list__button-entries').trigger('click');

        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.list' });
    });
});
