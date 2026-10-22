/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
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

type CommentListVm = {
    comments: unknown;
    customerNames: Record<string, string>;
    total: number;
    isLoading: boolean;
    listFilters: FilterDefinition[];
    activeFilterNumber: number;
    deleteTarget: { id: string } | null;
    canEdit: boolean;
    canDelete: boolean;
    buildCriteria: () => { filters: unknown[]; sortings: Array<{ field: string; order: string }> };
    getList: () => Promise<void>;
    updateCriteria: (filters: unknown[]) => void;
    onRefresh: () => void;
    titleOf: (item: CommentRow) => string;
    authorOf: (item: CommentRow) => string;
    blogTitleOf: (item: CommentRow) => string;
    excerptOf: (item: CommentRow) => string;
    onApprove: (item: CommentRow) => void;
    onHide: (item: CommentRow) => void;
    onBulkApprove: () => void;
    onDelete: (item: CommentRow) => void;
    onCloseDeleteModal: () => void;
    onConfirmDelete: () => void;
    onOpenBlogList: () => void;
};

type TypedWrapper = { vm: CommentListVm } & VueWrapper;

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

const customers = [{ id: 'customer-1', firstName: 'Grace', lastName: 'Lee', email: 'grace@example.com' }];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(can: (privilege: string) => boolean = () => true): Promise<{
    wrapper: TypedWrapper;
    search: jest.Mock;
    customerSearch: jest.Mock;
    save: jest.Mock;
    remove: jest.Mock;
    push: jest.Mock;
    mergeWithStoredFilters: jest.Mock;
    setStoredFilters: (filters: unknown[]) => void;
}> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_comment'>>(comments)));
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

    const wrapper = mount(await wrapTestComponent('sw-blog-comment-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot><slot></slot></div>' },
                'sw-search-bar': true,
                // the listing owns the selection in the real administration, so the stub has to own
                // one too - the page reads it from the ref
                'sw-entity-listing': {
                    name: 'SwEntityListingStub',
                    template: '<div></div>',
                    data() {
                        return { selection: {} as Record<string, CommentRow> };
                    },
                },
                'sw-sidebar': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-time-ago': true,
                'sw-context-menu-item': true,
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-button': true,
                'mt-badge': true,
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

    return {
        wrapper: wrapper as unknown as TypedWrapper,
        search,
        customerSearch,
        save,
        remove,
        push,
        mergeWithStoredFilters,
        setStoredFilters: (filters: unknown[]) => {
            storedFilters = filters;
        },
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
        expect(wrapper.vm.activeFilterNumber).toBe(0);
        expect(wrapper.vm.total).toBe(2);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('offers the moderation state and the entry as filters of the sidebar panel', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.listFilters.map((filter) => filter.property)).toEqual(['status', 'blog']);
        expect(wrapper.vm.listFilters.map((filter) => filter.type)).toEqual(['boolean-filter', undefined]);
    });

    it('starts the list over on page one when a filter changes', async () => {
        const { wrapper, search, setStoredFilters } = await createWrapper();
        await flushPromises();

        setStoredFilters([{ field: 'status', value: false, type: 'equals' }]);
        wrapper.vm.updateCriteria([{ field: 'status' }]);
        await flushPromises();

        expect(wrapper.vm.activeFilterNumber).toBe(1);
        expect(search.mock.calls).toHaveLength(2);
    });

    it('names the guest, the customer and the entry of a comment', async () => {
        const { wrapper, customerSearch } = await createWrapper();
        await flushPromises();

        // the customer is read in a second request: the comment has no association into that domain
        expect(customerSearch).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.customerNames).toEqual({ 'customer-1': 'Grace Lee' });

        expect(wrapper.vm.authorOf(comments[0])).toBe('Ada');
        expect(wrapper.vm.authorOf(comments[1])).toBe('Grace Lee');
        expect(wrapper.vm.blogTitleOf(comments[0])).toBe('Winter guide');
        // an entry that is gone does not blank the row
        expect(wrapper.vm.blogTitleOf(comments[1])).toBe('blog-2');
    });

    it('stands in the first words of the text when a comment has no headline', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.titleOf(comments[1])).toBe('Thank you');

        // the column shows a headline, so the stand-in is shorter than the cell of the text
        const flat = comments[0].content.replace(/\s+/g, ' ').trim();

        expect(wrapper.vm.titleOf(comments[0])).toBe(`${flat.slice(0, 60)}…`);
    });

    it('approves a single comment and reloads the list', async () => {
        const { wrapper, search, save } = await createWrapper();
        await flushPromises();

        wrapper.vm.onApprove(comments[0]);
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'comment-1', status: true }));
        // the second search is the reload after the write
        expect(search.mock.calls).toHaveLength(2);
    });

    it('hides a comment that was already approved', async () => {
        const { wrapper, save } = await createWrapper();
        await flushPromises();

        wrapper.vm.onHide(comments[1]);
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'comment-2', status: false }));
    });

    it('takes the selection of the listing for the bulk action', async () => {
        const { wrapper, save } = await createWrapper();
        await flushPromises();

        const listing = wrapper.findComponent({ name: 'SwEntityListingStub' }) as unknown as {
            vm: { $data: { selection: Record<string, CommentRow> } };
        };

        listing.vm.$data.selection = { 'comment-1': comments[0] };

        wrapper.vm.onBulkApprove();
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'comment-1', status: true }));
    });

    it('hides moderation and deleting from a role that may only read', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege === 'blog.viewer');

        expect(wrapper.vm.canEdit).toBe(false);
        expect(wrapper.vm.canDelete).toBe(false);
    });

    it('deletes only after the confirmation of the modal', async () => {
        const { wrapper, remove } = await createWrapper();
        await flushPromises();

        wrapper.vm.onDelete(comments[0]);

        expect(wrapper.vm.deleteTarget).not.toBeNull();
        expect(remove).not.toHaveBeenCalled();

        wrapper.vm.onConfirmDelete();
        await flushPromises();

        expect(remove).toHaveBeenCalledWith('comment-1');
        expect(wrapper.vm.deleteTarget).toBeNull();
    });

    it('does not delete anything when the modal is closed', async () => {
        const { wrapper, remove } = await createWrapper();
        await flushPromises();

        wrapper.vm.onDelete(comments[0]);
        wrapper.vm.onCloseDeleteModal();

        expect(wrapper.vm.deleteTarget).toBeNull();
        expect(remove).not.toHaveBeenCalled();
    });

    it('opens the entry list from the toolbar', async () => {
        const { wrapper, push } = await createWrapper();
        await flushPromises();

        wrapper.vm.onOpenBlogList();

        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.list' });
    });
});
