/**
 * @sw-package discovery
 */
import { flushPromises, shallowMount } from '@vue/test-utils';
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
    wrapper: VueWrapper;
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

    const wrapper = shallowMount(await wrapTestComponent('sw-blog-category-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"/><slot name="smart-bar-actions"/><slot name="content"/></div>',
                },
                'sw-search-bar': true,
                'sw-entity-listing': {
                    props: [
                        'dataSource',
                        'repository',
                        'allowDelete',
                        'allowInlineEdit',
                    ],
                    emits: ['inline-edit-save'],
                    template: `<table><tbody><tr v-for="item in dataSource" :key="item.id" :data-row="item.id">
                        <td><slot name="column-mediaId" :item="item" /></td>
                        <td><slot name="column-name" :item="item" :is-inline-edit="false">{{ item.name }}</slot>
                            <input v-if="allowInlineEdit" class="inline-name" :value="item.name" @input="item.name = $event.target.value" />
                            <button v-if="allowInlineEdit" class="inline-save" @click="$emit('inline-edit-save', item)">Save row</button>
                        </td>
                        <td><slot name="column-email" :item="item" /></td>
                        <td><slot name="column-entries" :item="item" /><slot name="column-assignments" :item="item" /></td>
                        <td><slot name="column-active" :item="item" :is-inline-edit="false" /></td>
                        <td class="delete-action"><slot name="delete-action" :item="item"><button :disabled="!allowDelete">Delete</button></slot></td>
                    </tr></tbody></table>`,
                },
                'sw-sidebar': true,
                'sw-sidebar-item': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'mt-button': {
                    props: ['disabled', 'isLoading'],
                    template: '<button :disabled="disabled" :data-loading="isLoading"><slot /></button>',
                },
                'sw-context-menu-item': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-checkbox': true,
                'mt-icon': true,
                'mt-text-field': {
                    props: ['modelValue'],
                    emits: ['update:modelValue'],
                    template: `<input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />`,
                },
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
    await flushPromises();

    return { wrapper, search, save, remove, push };
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
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(2)');
        expect(wrapper.get('.sw-blog-category-list__entry-count').text()).toBe('2');
    });

    it('creates a category with a generated slug and opens it', async () => {
        const { wrapper, save, push } = await createWrapper();

        await wrapper.get('.sw-blog-category-list__button-create').trigger('click');
        await wrapper.get('.sw-blog-category-list__create-name').setValue('Guides');
        await wrapper.findAll('.sw-blog-category-list__create-modal button')[1].trigger('click');
        await flushPromises();

        const created = callArgument<{ name: string; slug: string; active: boolean }>(save);

        expect(created.name).toBe('Guides');
        expect(created.active).toBe(true);
        expect(created.slug).toMatch(/^guides-[a-z0-9]+$/);
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.category.detail', params: { id: 'category-new' } });
    });

    it('refuses to create a category without a name', async () => {
        const { wrapper, save } = await createWrapper();

        await wrapper.get('.sw-blog-category-list__button-create').trigger('click');
        await wrapper.get('.sw-blog-category-list__create-name').setValue('  ');

        expect(wrapper.findAll('.sw-blog-category-list__create-modal button')[1].attributes('disabled')).toBeDefined();
        expect(save).not.toHaveBeenCalled();
    });

    it('hides creating and deleting from a role that may only read', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege === 'blog.viewer');

        expect(wrapper.get('.sw-blog-category-list__button-create').attributes('disabled')).toBeDefined();
        expect(wrapper.findAll('.delete-action button').every((button) => button.attributes('disabled') !== undefined)).toBe(
            true,
        );
        expect(wrapper.find('.inline-name').exists()).toBe(false);
    });

    it('deletes only after the confirmation of the modal', async () => {
        const { wrapper, remove } = await createWrapper();

        await wrapper.get('[data-row="category-1"] .delete-action button').trigger('click');
        expect(wrapper.find('.sw-blog-category-list__delete-modal').exists()).toBe(true);
        expect(remove).not.toHaveBeenCalled();

        await wrapper.findAll('.sw-blog-category-list__delete-modal button')[1].trigger('click');
        await flushPromises();

        expect(remove).toHaveBeenCalledWith('category-1');
        expect(wrapper.find('.sw-blog-category-list__delete-modal').exists()).toBe(false);
    });

    it('saves an inline edit of a row', async () => {
        const { wrapper, save } = await createWrapper();

        await wrapper.get('[data-row="category-1"] .inline-name').setValue('How tos');
        await wrapper.get('[data-row="category-1"] .inline-save').trigger('click');

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'category-1', name: 'How tos' }));
    });

    it('leads back to the entries the categories belong to', async () => {
        const { wrapper, push } = await createWrapper();

        await wrapper.get('.sw-blog-category-list__button-back').trigger('click');

        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.list' });
    });
});
