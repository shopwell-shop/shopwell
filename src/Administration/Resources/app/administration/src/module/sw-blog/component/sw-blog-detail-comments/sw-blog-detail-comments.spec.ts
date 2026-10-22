/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

type Comment = {
    id: string;
    blogId: string;
    parentId: string | null;
    customerId: string | null;
    externalUser: string | null;
    content: string;
    status: boolean;
};

type CommentsVm = {
    comments: Comment[];
    customerNames: Record<string, string>;
    deleteTarget: Comment | null;
    isLoading: boolean;
    replyCount: number;
    deleteMessage: string;
    load: () => Promise<void>;
    authorNameOf: (comment: Comment) => string;
    excerptOf: (comment: Comment) => string;
    depthOf: (comment: Comment) => number;
    statusLabelOf: (comment: Comment) => string;
    onApprove: (comment: Comment) => void;
    onHide: (comment: Comment) => void;
    onDelete: (comment: Comment) => void;
    onCloseDeleteModal: () => void;
    onConfirmDelete: () => Promise<void>;
};

type TypedWrapper = { vm: CommentsVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

const comments: Comment[] = [
    {
        id: 'comment-1',
        blogId: 'blog-1',
        parentId: null,
        customerId: 'customer-1',
        externalUser: null,
        content: 'Great article',
        status: true,
    },
    {
        id: 'comment-2',
        blogId: 'blog-1',
        parentId: 'comment-1',
        customerId: null,
        externalUser: 'Ada',
        content: 'Thanks for writing it',
        status: false,
    },
];

const customers = [{ id: 'customer-1', name: 'Grace Hopper', email: 'grace@example.com' }];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(canEdit: boolean = true): Promise<{
    wrapper: TypedWrapper;
    save: jest.Mock;
    remove: jest.Mock;
}> {
    const save = jest.fn(() => Promise.resolve());
    const remove = jest.fn(() => Promise.resolve());

    const commentRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'blog'>>(comments))),
        save,
        delete: remove,
    };

    const customerRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'customer'>>(customers))),
    };

    const wrapper = mount(await wrapTestComponent('sw-blog-detail-comments', { sync: true }), {
        props: { blogId: 'blog-1', canEdit },
        global: {
            stubs: {
                'sw-data-grid': { template: '<div class="grid"><slot></slot></div>' },
                'sw-context-menu-item': true,
                'sw-label': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'mt-button': true,
                'mt-empty-state': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entityName: string) => {
                        return entityName === 'customer' ? customerRepository : commentRepository;
                    },
                },
                blogApiService: new BlogApiService(),
            },
            mocks: {
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return { wrapper: wrapper as unknown as TypedWrapper, save, remove };
}

describe('src/module/sw-blog/component/sw-blog-detail-comments', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.clearAllMocks();
    });

    it('loads the thread and resolves a customer name without an association on the comment', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.comments).toHaveLength(2);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.authorNameOf(comments[0])).toBe('Grace Hopper');
        expect(wrapper.vm.authorNameOf(comments[1])).toBe('Ada');
    });

    it('reads the level and the excerpt of a comment', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.depthOf(comments[0])).toBe(0);
        expect(wrapper.vm.depthOf(comments[1])).toBe(1);
        expect(wrapper.vm.excerptOf(comments[0])).toBe('Great article');
        expect(wrapper.vm.statusLabelOf(comments[1])).toBe('sw-blog.detail.commentsStatusPending');
    });

    it('approves a comment by flipping its status', async () => {
        const { wrapper, save } = await createWrapper();
        await flushPromises();

        wrapper.vm.onApprove(comments[1]);
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'comment-2', status: true }));
    });

    it('hides a comment by flipping its status back', async () => {
        const { wrapper, save } = await createWrapper();
        await flushPromises();

        wrapper.vm.onHide(comments[0]);
        await flushPromises();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'comment-1', status: false }));
    });

    it('names the replies in the confirmation instead of a hard coded number', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        wrapper.vm.onDelete(comments[0]);

        expect(wrapper.vm.replyCount).toBe(1);
        expect(wrapper.vm.deleteMessage).toBe('sw-blog.detail.commentsTextDelete');
    });

    it('deletes the replies together with the comment they answer', async () => {
        const { wrapper, remove } = await createWrapper();
        await flushPromises();

        wrapper.vm.onDelete(comments[0]);
        await wrapper.vm.onConfirmDelete();

        expect(remove).toHaveBeenCalledWith('comment-2');
        expect(remove).toHaveBeenCalledWith('comment-1');
        expect(remove).toHaveBeenCalledTimes(2);
    });

    it('does not delete anything when the modal is closed', async () => {
        const { wrapper, remove } = await createWrapper();
        await flushPromises();

        wrapper.vm.onDelete(comments[0]);
        wrapper.vm.onCloseDeleteModal();

        expect(wrapper.vm.deleteTarget).toBeNull();
        expect(remove).not.toHaveBeenCalled();
    });
});
