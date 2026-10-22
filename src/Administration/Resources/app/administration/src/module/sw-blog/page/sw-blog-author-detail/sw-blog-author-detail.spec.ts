/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

type AuthorVm = {
    author: { id: string; name: string; mediaId: string | null; active: boolean } | null;
    entries: unknown;
    entryCount: number;
    isDeleteBlocked: boolean;
    name: string;
    isNameMissing: boolean;
    canEdit: boolean;
    canDelete: boolean;
    loadAuthor: () => Promise<void>;
    loadEntries: () => Promise<void>;
    onSave: (redirect?: boolean) => Promise<void>;
    onAvatarSelected: (media: Array<{ id: string }>) => void;
    onAvatarRemoved: () => void;
    onDelete: () => void;
    onDeactivateInstead: () => void;
    deleteMessage: string;
};

type TypedWrapper = { vm: AuthorVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

const author = { id: 'author-1', name: 'Anna', mediaId: null, active: true };

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    entries: unknown[] = [{ id: 'blog-1' }],
    can: (privilege: string) => boolean = () => true,
): Promise<{ wrapper: TypedWrapper; save: jest.Mock; remove: jest.Mock; push: jest.Mock }> {
    const save = jest.fn(() => Promise.resolve());
    const remove = jest.fn(() => Promise.resolve());
    const push = jest.fn(() => Promise.resolve());

    const authorRepository = {
        get: () => Promise.resolve({ ...author }),
        save,
        delete: remove,
        hasChanges: () => false,
    };

    const blogRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'blog'>>(entries))),
    };

    const wrapper = mount(await wrapTestComponent('sw-blog-author-detail', { sync: true }), {
        props: { authorId: 'author-1' },
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot><slot></slot></div>' },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-language-switch': true,
                'sw-skeleton': true,
                'sw-media-compact-upload-v2': true,
                'sw-data-grid': true,
                'sw-time-ago': true,
                'sw-blog-status-badge': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'mt-card': { template: '<div><slot></slot></div>', props: ['title'] },
                'mt-button': true,
                'mt-text-field': true,
                'mt-textarea': true,
                'mt-switch': true,
                'mt-empty-state': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity: string) => (entity === 'blog' ? blogRepository : authorRepository),
                },
                acl: { can },
                blogApiService: new BlogApiService(),
            },
            mocks: {
                $route: { name: 'sw.blog.author.detail', meta: { $module: { icon: 'regular-image-text' } } },
                $router: { push, replace: jest.fn(() => Promise.resolve()) },
                $t: (key: string, payload?: Record<string, unknown>) => (payload ? `${key}:${JSON.stringify(payload)}` : key),
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return { wrapper: wrapper as unknown as TypedWrapper, save, remove, push };
}

describe('src/module/sw-blog/page/sw-blog-author-detail', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.clearAllMocks();
    });

    it('loads the author and the entries that carry the byline', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.author?.name).toBe('Anna');
        expect(wrapper.vm.entryCount).toBe(1);
    });

    it('saves without leaving the page', async () => {
        const { wrapper, save, push } = await createWrapper();
        await flushPromises();

        await wrapper.vm.onSave();
        await flushPromises();

        expect(save).toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('refuses to save an author without a name', async () => {
        const { wrapper, save } = await createWrapper();
        await flushPromises();

        if (wrapper.vm.author) {
            wrapper.vm.author.name = '   ';
        }

        await wrapper.vm.onSave();

        expect(wrapper.vm.isNameMissing).toBe(true);
        expect(save).not.toHaveBeenCalled();
    });

    it('writes a chosen portrait and clears it with an explicit null', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        wrapper.vm.onAvatarSelected([{ id: 'media-1' }]);
        expect(wrapper.vm.author?.mediaId).toBe('media-1');

        wrapper.vm.onAvatarRemoved();
        expect(wrapper.vm.author?.mediaId).toBeNull();
    });

    it('blocks the delete while entries still carry the byline', async () => {
        const { wrapper, remove } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.isDeleteBlocked).toBe(true);

        wrapper.vm.onDelete();

        expect(remove).not.toHaveBeenCalled();
        expect(wrapper.vm.deleteMessage).toContain('textDeleteInUse');
    });

    it('retires an author in use by switching them off', async () => {
        const { wrapper, save, remove } = await createWrapper();
        await flushPromises();

        wrapper.vm.onDeactivateInstead();
        await flushPromises();

        expect(remove).not.toHaveBeenCalled();
        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'author-1', active: false }));
    });

    it('deletes an author nobody signs with', async () => {
        const { wrapper, remove, push } = await createWrapper([]);
        await flushPromises();

        expect(wrapper.vm.isDeleteBlocked).toBe(false);

        wrapper.vm.onDelete();
        await flushPromises();

        expect(remove).toHaveBeenCalledWith('author-1');
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.author.list' });
    });
});
