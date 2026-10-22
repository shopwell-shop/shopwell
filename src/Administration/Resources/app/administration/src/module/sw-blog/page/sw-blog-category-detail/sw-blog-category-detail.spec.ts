/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

type CategoryDetailVm = {
    category: Record<string, unknown> | null;
    cmsPage: Record<string, unknown> | null;
    isLoading: boolean;
    isSaving: boolean;
    showDeleteModal: boolean;
    canEdit: boolean;
    canDelete: boolean;
    name: string;
    isNameMissing: boolean;
    isSlugMissing: boolean;
    loadCategory: () => Promise<void>;
    loadCmsPage: () => Promise<void>;
    applyActiveDefault: () => void;
    onCmsPageChanged: () => void;
    onSave: (redirect?: boolean) => Promise<void>;
    onCancel: () => void;
    onOpenDeleteModal: () => void;
    onCloseDeleteModal: () => void;
    onDelete: () => void;
    abortOnLanguageChange: () => boolean;
    saveOnLanguageChange: () => Promise<void>;
    onChangeLanguage: (languageId: string) => void;
};

type TypedWrapper = { vm: CategoryDetailVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

const category = {
    id: 'category-1',
    name: 'Guides',
    slug: 'guides',
    description: null,
    active: true,
    cmsPageId: 'page-1',
};

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    options: {
        can?: (privilege: string) => boolean;
        entry?: Record<string, unknown>;
        hasChanges?: boolean;
    } = {},
): Promise<{
    wrapper: TypedWrapper;
    getCategory: jest.Mock;
    saveCategory: jest.Mock;
    deleteCategory: jest.Mock;
    getCmsPage: jest.Mock;
    push: jest.Mock;
}> {
    const { can = () => true, entry = category, hasChanges = false } = options;

    const getCategory = jest.fn(() => Promise.resolve({ ...entry }));
    const saveCategory = jest.fn(() => Promise.resolve());
    const deleteCategory = jest.fn(() => Promise.resolve());
    const getCmsPage = jest.fn(() => Promise.resolve({ id: 'page-1', versionId: 'version-1' }));
    const push = jest.fn(() => Promise.resolve());

    const categoryRepository = {
        get: getCategory,
        save: saveCategory,
        delete: deleteCategory,
        hasChanges: jest.fn(() => hasChanges),
        create: () => {
            return { ...category };
        },
    };

    const cmsPageRepository = {
        get: getCmsPage,
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'cms_page'>>([]))),
    };

    const wrapper = mount(await wrapTestComponent('sw-blog-category-detail', { sync: true }), {
        props: { categoryId: 'category-1' },
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot><slot></slot></div>' },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': { template: '<div><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-language-switch': true,
                'sw-entity-single-select': true,
                'mt-text-field': true,
                'mt-textarea': true,
                'mt-switch': true,
                'mt-button': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entityName: string) => {
                        return entityName === 'cms_page' ? cmsPageRepository : categoryRepository;
                    },
                },
                acl: { can },
                blogApiService: new BlogApiService(),
            },
            mocks: {
                $route: { name: 'sw.blog.category.detail', meta: { $module: { icon: 'regular-image-text' } } },
                $router: { push, replace: jest.fn(() => Promise.resolve()) },
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return {
        wrapper: wrapper as unknown as TypedWrapper,
        getCategory,
        saveCategory,
        deleteCategory,
        getCmsPage,
        push,
    };
}

describe('src/module/sw-blog/page/sw-blog-category-detail', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.clearAllMocks();
    });

    it('loads the category and the layout it holds', async () => {
        const { wrapper, getCategory } = await createWrapper();
        await flushPromises();

        const criteria = (getCategory.mock.calls as unknown as Array<[string, unknown, { ids: string[] }]>)[0][2];

        expect(criteria.ids).toEqual(['category-1']);
        expect(wrapper.vm.category?.name).toBe('Guides');
        expect(wrapper.vm.cmsPage?.id).toBe('page-1');
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('saves the category with the version of its layout and returns to the list', async () => {
        const { wrapper, saveCategory, push } = await createWrapper();
        await flushPromises();

        await wrapper.vm.onSave();
        await flushPromises();

        expect(saveCategory).toHaveBeenCalledWith(
            expect.objectContaining({ id: 'category-1', cmsPageVersionId: 'version-1' }),
        );
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.category.list' });
    });

    it('gives a new category the active default the definition requires', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, active: null } });
        await flushPromises();

        expect(wrapper.vm.category?.active).toBe(true);

        await wrapper.vm.onSave();
        await flushPromises();

        expect(saveCategory).toHaveBeenCalledWith(expect.objectContaining({ active: true }));
    });

    it('keeps a category that was switched off switched off', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, active: false } });
        await flushPromises();

        expect(wrapper.vm.category?.active).toBe(false);

        await wrapper.vm.onSave();
        await flushPromises();

        expect(saveCategory).toHaveBeenCalledWith(expect.objectContaining({ active: false }));
    });

    it('refuses to save a category without a name', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, name: '  ' } });
        await flushPromises();

        expect(wrapper.vm.isNameMissing).toBe(true);

        await wrapper.vm.onSave();

        expect(saveCategory).not.toHaveBeenCalled();
    });

    it('refuses to save a category without an url name', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, slug: '' } });
        await flushPromises();

        expect(wrapper.vm.isSlugMissing).toBe(true);

        await wrapper.vm.onSave();

        expect(saveCategory).not.toHaveBeenCalled();
    });

    it('asks before it discards unsaved changes and reloads the category after the switch', async () => {
        const { wrapper, getCategory } = await createWrapper({ hasChanges: true });
        await flushPromises();

        expect(wrapper.vm.abortOnLanguageChange()).toBe(true);

        wrapper.vm.onChangeLanguage('language-2');
        await flushPromises();

        expect(getCategory).toHaveBeenCalledTimes(2);
    });

    it('stays on the page when the save was triggered by the language switch', async () => {
        const { wrapper, saveCategory, push } = await createWrapper();
        await flushPromises();

        await wrapper.vm.saveOnLanguageChange();
        await flushPromises();

        expect(saveCategory).toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('keeps saving and deleting away from a reader', async () => {
        const { wrapper } = await createWrapper({ can: (privilege) => privilege === 'blog.viewer' });
        await flushPromises();

        expect(wrapper.vm.canEdit).toBe(false);
        expect(wrapper.vm.canDelete).toBe(false);
    });

    it('deletes the category after the confirmation', async () => {
        const { wrapper, deleteCategory, push } = await createWrapper();
        await flushPromises();

        wrapper.vm.onOpenDeleteModal();
        wrapper.vm.onDelete();
        await flushPromises();

        expect(deleteCategory).toHaveBeenCalledWith('category-1');
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.category.list' });
    });
});
