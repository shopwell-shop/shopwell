/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

type AuthorRow = {
    id: string;
    name: string;
    email: string | null;
    mediaId: string | null;
    active: boolean;
    entries?: unknown[];
};

type AuthorListVm = {
    authors: unknown;
    total: number;
    canCreate: boolean;
    canDelete: boolean;
    canEdit: boolean;
    isCreateDisabled: boolean;
    isDeleteBlocked: boolean;
    deleteTarget: { id: string; name: string; entryCount: number } | null;
    newAuthorName: string;
    getList: () => Promise<void>;
    entryCountOf: (author: AuthorRow) => number;
    initialOf: (author: AuthorRow) => string;
    onOpenCreateModal: () => void;
    onCreateAuthor: () => void;
    onDelete: (author: AuthorRow) => void;
    onConfirmDelete: () => void;
    onDeactivateInstead: () => void;
    onOpenAuthorEntries: (author: AuthorRow) => void;
    onChangeActive: (author: AuthorRow) => void;
};

type TypedWrapper = { vm: AuthorListVm } & VueWrapper;

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

const authors: AuthorRow[] = [
    { id: 'author-1', name: 'Anna', email: 'anna@example.com', mediaId: null, active: true, entries: [{}, {}, {}] },
    { id: 'author-2', name: 'Bo', email: null, mediaId: null, active: false, entries: [] },
];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(can: (privilege: string) => boolean = () => true): Promise<{
    wrapper: TypedWrapper;
    search: jest.Mock;
    save: jest.Mock;
    remove: jest.Mock;
    push: jest.Mock;
}> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_author'>>(authors)));
    const save = jest.fn(() => Promise.resolve());
    const remove = jest.fn(() => Promise.resolve());
    const push = jest.fn(() => Promise.resolve());

    const repository = {
        search,
        save,
        delete: remove,
        get: (id: string) => Promise.resolve({ ...authors.find((author) => author.id === id) }),
        create: () => {
            return { id: 'author-new', name: null, active: false };
        },
    };

    const wrapper = mount(await wrapTestComponent('sw-blog-author-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot><slot></slot></div>' },
                'sw-search-bar': true,
                'sw-entity-listing': true,
                'sw-sidebar': true,
                'sw-sidebar-item': true,
                'sw-media-preview-v2': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'mt-button': true,
                'mt-switch': true,
                'mt-text-field': true,
                'mt-empty-state': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => repository,
                },
                acl: { can },
                blogApiService: new BlogApiService(),
                searchRankingService: {
                    isValidTerm: () => true,
                    getSearchFieldsByEntity: () => Promise.resolve({}),
                    buildSearchQueriesForEntity: () => Promise.resolve([]),
                },
                feature: { isActive: () => false },
            },
            mocks: {
                $route: { name: 'sw.blog.author.list', meta: { $module: { icon: 'regular-image-text' } } },
                $router: { push, replace: jest.fn(() => Promise.resolve()) },
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return { wrapper: wrapper as unknown as TypedWrapper, search, save, remove, push };
}

describe('src/module/sw-blog/page/sw-blog-author-list', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.clearAllMocks();
    });

    it('reads the authors by name and counts their entries', async () => {
        const { wrapper, search } = await createWrapper();
        await flushPromises();

        const criteria = callArgument<{ sortings: Array<{ field: string; order: string }>; associations: unknown[] }>(
            search,
        );

        expect(criteria.sortings).toEqual([expect.objectContaining({ field: 'name', order: 'ASC' })]);
        expect(associationNames(criteria)).toContain('entries');
        expect(wrapper.vm.total).toBe(2);
        expect(wrapper.vm.entryCountOf(authors[0])).toBe(3);
    });

    it('creates an author with a name and opens it, because a byline needs a place to be written', async () => {
        const { wrapper, save, push } = await createWrapper();

        wrapper.vm.onOpenCreateModal();
        wrapper.vm.newAuthorName = 'Anna Schmidt';
        wrapper.vm.onCreateAuthor();
        await flushPromises();

        const created = callArgument<{ name: string; active: boolean }>(save);

        expect(created.name).toBe('Anna Schmidt');
        expect(created.active).toBe(true);
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.author.detail', params: { id: 'author-new' } });
    });

    it('refuses to create an author without a name', async () => {
        const { wrapper, save } = await createWrapper();

        wrapper.vm.onOpenCreateModal();
        wrapper.vm.newAuthorName = '   ';
        wrapper.vm.onCreateAuthor();

        expect(wrapper.vm.isCreateDisabled).toBe(true);
        expect(save).not.toHaveBeenCalled();
    });

    it('does not delete an author who still signs entries', async () => {
        const { wrapper, remove } = await createWrapper();

        wrapper.vm.onDelete(authors[0]);

        expect(wrapper.vm.isDeleteBlocked).toBe(true);

        wrapper.vm.onConfirmDelete();

        expect(remove).not.toHaveBeenCalled();
    });

    it('switches an author off instead of deleting them', async () => {
        const { wrapper, save, remove } = await createWrapper();

        wrapper.vm.onDelete(authors[0]);
        wrapper.vm.onDeactivateInstead();
        await flushPromises();

        expect(remove).not.toHaveBeenCalled();
        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'author-1', active: false }));
    });

    it('deletes an author without entries after the confirmation', async () => {
        const { wrapper, remove } = await createWrapper();

        wrapper.vm.onDelete(authors[1]);
        wrapper.vm.onConfirmDelete();
        await flushPromises();

        expect(remove).toHaveBeenCalledWith('author-2');
    });

    it('opens the entries of one author in the filtered entry list', async () => {
        const { wrapper, push } = await createWrapper();

        wrapper.vm.onOpenAuthorEntries(authors[0]);

        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.list', query: { authorId: 'author-1' } });
    });

    it('falls back to the first letter when an author has no portrait', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.initialOf(authors[0])).toBe('A');
    });

    it('hides creating and deleting from a role that may only read', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege === 'blog.viewer');

        expect(wrapper.vm.canCreate).toBe(false);
        expect(wrapper.vm.canDelete).toBe(false);
        expect(wrapper.vm.canEdit).toBe(false);
    });

    it('saves the switch of the active column', async () => {
        const { wrapper, save } = await createWrapper();

        wrapper.vm.onChangeActive({ ...authors[1], active: true });

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'author-2', active: true }));
    });
});
