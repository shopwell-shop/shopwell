/**
 * @sw-package discovery
 */
import { flushPromises, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

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

const entries = [
    { id: 'blog-1', title: 'Autumn guide', publishedAt: null, updatedAt: '2026-10-01T10:00:00.000Z' },
    {
        id: 'blog-2',
        title: 'Behind the scenes',
        publishedAt: '2026-10-02T10:00:00.000Z',
        updatedAt: '2026-10-02T10:00:00.000Z',
    },
];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    options: {
        can?: (privilege: string) => boolean;
        entry?: Record<string, unknown>;
        hasChanges?: boolean;
    } = {},
): Promise<{
    wrapper: VueWrapper;
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

    const blogRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'blog'>>(entries))),
    };

    const wrapper = shallowMount(await wrapTestComponent('sw-blog-category-detail', { sync: true }), {
        props: { categoryId: 'category-1' },
        global: {
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"></slot><slot name="smart-bar-actions"></slot><slot name="language-switch"></slot><slot name="content"></slot></div>',
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': { template: '<div><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-language-switch': {
                    props: ['saveChangesFunction', 'abortChangeFunction'],
                    template:
                        '<section><output class="unsaved">{{ abortChangeFunction() }}</output><button class="language-save" @click="saveChangesFunction()">Save translation</button><button class="language-change" @click="$emit(\'on-change\', \'language-2\')">Change language</button></section>',
                },
                'sw-entity-single-select': {
                    props: ['value', 'disabled'],
                    template: '<input :value="value" :disabled="disabled">',
                },
                'mt-text-field': {
                    props: ['modelValue', 'disabled'],
                    template:
                        '<input :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)">',
                },
                'mt-textarea': true,
                'mt-switch': {
                    props: ['modelValue', 'disabled'],
                    template:
                        '<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)">',
                },
                'mt-button': {
                    props: ['disabled', 'isLoading'],
                    template: '<button :disabled="disabled" :data-loading="isLoading"><slot></slot></button>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: (entityName: string) => {
                        if (entityName === 'cms_page') {
                            return cmsPageRepository;
                        }

                        if (entityName === 'blog') {
                            return blogRepository;
                        }

                        return categoryRepository;
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
        wrapper,
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
        jest.restoreAllMocks();
    });

    it('loads the category and the layout it holds', async () => {
        const { wrapper, getCategory } = await createWrapper();
        await flushPromises();

        const criteria = (getCategory.mock.calls as unknown as Array<[string, unknown, { ids: string[] }]>)[0][2];

        expect(criteria.ids).toEqual(['category-1']);
        expect((wrapper.get('.sw-blog-category-detail__name').element as HTMLInputElement).value).toBe('Guides');
        expect((wrapper.get('.sw-blog-category-detail__cms-page').element as HTMLInputElement).value).toBe('page-1');
        expect(wrapper.find('.sw-blog-category-detail__loading').exists()).toBe(false);
    });

    it('saves the category with the version of its layout and stays on the page', async () => {
        const { wrapper, saveCategory, getCategory, push } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-category-detail__action-save').trigger('click');
        await flushPromises();

        expect(saveCategory).toHaveBeenCalledWith(
            expect.objectContaining({ id: 'category-1', cmsPageVersionId: 'version-1' }),
        );

        // the page reloads the saved state instead of returning to the list
        expect(getCategory).toHaveBeenCalledTimes(2);
        expect(push).not.toHaveBeenCalled();
    });

    it('gives a new category the active default the definition requires', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, active: null } });
        await flushPromises();

        expect((wrapper.get('.sw-blog-category-detail__active').element as HTMLInputElement).checked).toBe(true);

        await wrapper.get('.sw-blog-category-detail__action-save').trigger('click');
        await flushPromises();

        expect(saveCategory).toHaveBeenCalledWith(expect.objectContaining({ active: true }));
    });

    it('keeps a category that was switched off switched off', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, active: false } });
        await flushPromises();

        expect((wrapper.get('.sw-blog-category-detail__active').element as HTMLInputElement).checked).toBe(false);

        await wrapper.get('.sw-blog-category-detail__action-save').trigger('click');
        await flushPromises();

        expect(saveCategory).toHaveBeenCalledWith(expect.objectContaining({ active: false }));
    });

    it('refuses to save a category without a name', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, name: '  ' } });
        await flushPromises();

        expect((wrapper.get('.sw-blog-category-detail__name').element as HTMLInputElement).value).toBe('  ');

        await wrapper.get('.sw-blog-category-detail__action-save').trigger('click');

        expect(saveCategory).not.toHaveBeenCalled();
    });

    it('refuses to save a category without an url name', async () => {
        const { wrapper, saveCategory } = await createWrapper({ entry: { ...category, slug: '' } });
        await flushPromises();

        expect((wrapper.get('.sw-blog-category-detail__slug').element as HTMLInputElement).value).toBe('');

        await wrapper.get('.sw-blog-category-detail__action-save').trigger('click');

        expect(saveCategory).not.toHaveBeenCalled();
    });

    it('asks before it discards unsaved changes and reloads the category after the switch', async () => {
        const { wrapper, getCategory } = await createWrapper({ hasChanges: true });
        await flushPromises();

        expect(wrapper.get('.unsaved').text() === 'true').toBe(true);

        await wrapper.get('.language-change').trigger('click');
        await flushPromises();

        expect(getCategory).toHaveBeenCalledTimes(2);
    });

    it('stays on the page when the save was triggered by the language switch', async () => {
        const { wrapper, saveCategory, push } = await createWrapper();
        await flushPromises();

        await wrapper.get('.language-save').trigger('click');
        await flushPromises();

        expect(saveCategory).toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('keeps saving and deleting away from a reader', async () => {
        const { wrapper } = await createWrapper({ can: (privilege) => privilege === 'blog.viewer' });
        await flushPromises();

        expect(wrapper.get('.sw-blog-category-detail__action-save').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-blog-category-detail__action-delete').exists()).toBe(false);
    });

    it('deletes the category after the confirmation', async () => {
        const { wrapper, deleteCategory, push } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-category-detail__action-delete').trigger('click');
        await wrapper.get('.sw-blog-category-detail__delete-modal').findAll('button')[1].trigger('click');
        await flushPromises();

        expect(deleteCategory).toHaveBeenCalledWith('category-1');
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.category.list' });
    });
});
