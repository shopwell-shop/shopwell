/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

type CommentRow = {
    id: string;
    content: string;
    status: boolean;
    externalUser: string | null;
    customerId: string | null;
    customer: { firstName: string; lastName: string } | null;
    blogId: string;
    blog: { translated: { title: string } } | null;
};

type CommentListVm = {
    comments: unknown;
    total: number;
    isLoading: boolean;
    statusFilter: 'pending' | 'approved' | 'all';
    deleteTarget: { id: string } | null;
    canEdit: boolean;
    canDelete: boolean;
    authorOf: (item: CommentRow) => string;
    blogTitleOf: (item: CommentRow) => string;
    excerptOf: (item: CommentRow) => string;
    getList: () => Promise<void>;
    onRefresh: () => void;
    onChangeStatusFilter: () => void;
    onChangeStatus: (item: CommentRow) => void;
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
        content: 'When does the restock arrive?  '.repeat(4).trim(),
        status: false,
        externalUser: 'Ada',
        customerId: null,
        customer: null,
        blogId: 'blog-1',
        blog: { translated: { title: 'Winter guide' } },
    },
    {
        id: 'comment-2',
        content: 'Great article.',
        status: true,
        externalUser: null,
        customerId: 'customer-1',
        customer: { firstName: 'Grace', lastName: 'Lee' },
        blogId: 'blog-2',
        blog: null,
    },
];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(can: (privilege: string) => boolean = () => true): Promise<{
    wrapper: TypedWrapper;
    search: jest.Mock;
    save: jest.Mock;
    remove: jest.Mock;
    push: jest.Mock;
}> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_comment'>>(comments)));
    const save = jest.fn(() => Promise.resolve());
    const remove = jest.fn(() => Promise.resolve());
    const push = jest.fn(() => Promise.resolve());

    const wrapper = mount(await wrapTestComponent('sw-blog-comment-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot><slot></slot></div>' },
                'sw-search-bar': true,
                'sw-entity-listing': true,
                'sw-sidebar': true,
                'sw-sidebar-item': true,
                'sw-modal': { template: '<div class="sw-modal-stub"><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-data-grid-column-boolean': true,
                'sw-time-ago': true,
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-button': true,
                'mt-select': true,
                'mt-switch': true,
                'mt-tooltip': { template: '<span><slot></slot></span>', props: ['content'] },
                'mt-empty-state': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search,
                        save,
                        delete: remove,
                    }),
                },
                acl: { can },
                blogApiService: new BlogApiService(),
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

    return { wrapper: wrapper as unknown as TypedWrapper, search, save, remove, push };
}

describe('src/module/sw-blog/page/sw-blog-comment-list', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.clearAllMocks();
    });

    it('opens on the comments that wait for approval, newest first', async () => {
        const { wrapper, search } = await createWrapper();
        await flushPromises();

        const criteria = callArgument<{ filters: Array<{ type: string; field: string; value: boolean }>; sortings: Array<{ field: string; order: string }> }>(search);

        expect(wrapper.vm.statusFilter).toBe('pending');
        expect(criteria.sortings).toEqual([expect.objectContaining({ field: 'createdAt', order: 'DESC' })]);
        expect(criteria.filters).toEqual([expect.objectContaining({ field: 'status', value: false })]);
        expect(wrapper.vm.total).toBe(2);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('removes the status filter when the queue shows all states', async () => {
        const { wrapper, search } = await createWrapper();
        await flushPromises();

        search.mockClear();
        wrapper.vm.statusFilter = 'all';
        wrapper.vm.onChangeStatusFilter();
        await flushPromises();

        const criteria = callArgument<{ filters: Array<{ field: string }> }>(search);

        expect(criteria.filters).toEqual([]);
    });

    it('shows the guest name, the customer name and the entry of a comment', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.authorOf(comments[0])).toBe('Ada');
        expect(wrapper.vm.authorOf(comments[1])).toBe('Grace Lee');
        expect(wrapper.vm.blogTitleOf(comments[0])).toBe('Winter guide');
        // an entry that is gone does not blank the row
        expect(wrapper.vm.blogTitleOf(comments[1])).toBe('blog-2');
        expect(wrapper.vm.excerptOf(comments[0])).toBe(comments[0].content.replace(/\s+/g, ' ').trim());
    });

    it('saves the moderation state through the switch and reloads', async () => {
        const { wrapper, search, save } = await createWrapper();
        await flushPromises();

        const approved = { ...comments[0], status: true };

        wrapper.vm.onChangeStatus(approved);
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'comment-1', status: true }));
        // the second search is the reload after the save
        expect(search.mock.calls).toHaveLength(2);
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
