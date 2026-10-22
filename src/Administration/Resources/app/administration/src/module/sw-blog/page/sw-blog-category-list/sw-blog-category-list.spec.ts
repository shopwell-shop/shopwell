/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

type CategoryRow = {
    id: string;
    name: string | null;
    slug: string | null;
    description: string | null;
    active: boolean;
    assignments?: unknown[];
};

type CategoryListVm = {
    categories: unknown;
    total: number;
    isLoading: boolean;
    deleteTarget: { id: string; name: string | null } | null;
    showCreateModal: boolean;
    newCategoryName: string;
    canCreate: boolean;
    canDelete: boolean;
    canEdit: boolean;
    isCreateDisabled: boolean;
    getList: () => Promise<void>;
    onOpenCreateModal: () => void;
    onCreateCategory: () => void;
    onInlineEditSave: (category: CategoryRow) => void;
    onDelete: (category: CategoryRow) => void;
    onConfirmDelete: () => void;
    onOpenBlogList: () => void;
};

type TypedWrapper = { vm: CategoryListVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

function callArgument<T>(mock: jest.Mock, call = 0, argument = 0): T {
    const calls = mock.mock.calls as unknown as T[][];

    return calls[call][argument];
}

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

const categories: CategoryRow[] = [
    { id: 'category-1', name: 'Guides', slug: 'guides', description: null, active: true, assignments: [{}, {}] },
    { id: 'category-2', name: 'Company news', slug: 'company-news', description: null, active: false, assignments: [] },
];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(can: (privilege: string) => boolean = () => true): Promise<{
    wrapper: TypedWrapper;
    search: jest.Mock;
    save: jest.Mock;
    remove: jest.Mock;
    push: jest.Mock;
}> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_category'>>(categories)));
    const save = jest.fn(() => Promise.resolve());
    const remove = jest.fn(() => Promise.resolve());
    const push = jest.fn(() => Promise.resolve());

    const repository = {
        search,
        save,
        delete: remove,
        create: () => {
            return { id: 'category-new', name: null, slug: null, active: false };
        },
    };

    const wrapper = mount(await wrapTestComponent('sw-blog-category-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot><slot></slot></div>' },
                'sw-search-bar': true,
                'sw-entity-listing': true,
                'sw-sidebar': true,
                'sw-sidebar-item': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'mt-button': true,
                'mt-checkbox': true,
                'mt-icon': true,
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
                $route: { name: 'sw.blog.category.list', meta: { $module: { icon: 'regular-image-text' } } },
                $router: { push, replace: jest.fn(() => Promise.resolve()) },
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return { wrapper: wrapper as unknown as TypedWrapper, search, save, remove, push };
}

describe('src/module/sw-blog/page/sw-blog-category-list', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.clearAllMocks();
    });

    it('reads the categories by name and counts the articles of each', async () => {
        const { wrapper, search } = await createWrapper();
        await flushPromises();

        const criteria = callArgument<{ sortings: Array<{ field: string; order: string }>; associations: unknown[] }>(
            search,
        );

        expect(criteria.sortings).toEqual([expect.objectContaining({ field: 'name', order: 'ASC' })]);
        expect(associationNames(criteria)).toContain('assignments');
        expect(wrapper.vm.total).toBe(2);
    });

    it('creates a category with a generated slug and opens it', async () => {
        const { wrapper, save, push } = await createWrapper();

        wrapper.vm.onOpenCreateModal();
        wrapper.vm.newCategoryName = 'Guides';
        wrapper.vm.onCreateCategory();
        await flushPromises();

        const created = callArgument<{ name: string; slug: string; active: boolean }>(save);

        expect(created.name).toBe('Guides');
        expect(created.active).toBe(true);
        expect(created.slug).toMatch(/^guides-[a-z0-9]+$/);
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.category.detail', params: { id: 'category-new' } });
    });

    it('refuses to create a category without a name', async () => {
        const { wrapper, save } = await createWrapper();

        wrapper.vm.onOpenCreateModal();
        wrapper.vm.newCategoryName = '  ';
        wrapper.vm.onCreateCategory();

        expect(wrapper.vm.isCreateDisabled).toBe(true);
        expect(save).not.toHaveBeenCalled();
    });

    it('hides creating and deleting from a role that may only read', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege === 'blog.viewer');

        expect(wrapper.vm.canCreate).toBe(false);
        expect(wrapper.vm.canDelete).toBe(false);
        expect(wrapper.vm.canEdit).toBe(false);
    });

    it('deletes only after the confirmation of the modal', async () => {
        const { wrapper, remove } = await createWrapper();

        wrapper.vm.onDelete(categories[0]);
        expect(remove).not.toHaveBeenCalled();

        wrapper.vm.onConfirmDelete();

        expect(remove).toHaveBeenCalledWith('category-1');
        expect(wrapper.vm.deleteTarget).toBeNull();
    });

    it('saves an inline edit of a row', async () => {
        const { wrapper, save } = await createWrapper();

        wrapper.vm.onInlineEditSave({ ...categories[0], name: 'How tos' });

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'category-1', name: 'How tos' }));
    });

    it('leads back to the entries the categories belong to', async () => {
        const { wrapper, push } = await createWrapper();

        wrapper.vm.onOpenBlogList();

        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.list' });
    });
});
