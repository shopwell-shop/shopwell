/**
 * @sw-package discovery
 */
import { flushPromises, shallowMount } from '@vue/test-utils';
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
    wrapper: VueWrapper;
    search: jest.Mock;
    save: jest.Mock;
    remove: jest.Mock;
    push: jest.Mock;
}> {
    const rows = authors.map((row) => ({ ...row }));
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_author'>>(rows)));
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

    const wrapper = shallowMount(await wrapTestComponent('sw-blog-author-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="search-bar"/><slot name="smart-bar-header"/><slot name="smart-bar-actions"/><slot name="content"/></div>',
                },
                'sw-search-bar': {
                    template: '<input class="search-input" @input="$emit(\'search\', $event.target.value)" />',
                },
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
                'sw-media-preview-v2': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'mt-button': {
                    props: ['disabled', 'isLoading'],
                    template: '<button :disabled="disabled" :data-loading="isLoading"><slot /></button>',
                },
                'sw-context-menu-item': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-switch': {
                    props: ['modelValue', 'disabled'],
                    emits: ['update:modelValue', 'change'],
                    template: `<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.checked); $emit('change', $event.target.checked)" />`,
                },
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
                $route: { name: 'sw.blog.author.list', meta: { $module: { icon: 'regular-image-text' } } },
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
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(2)');
        expect(wrapper.get('.sw-blog-author-list__entry-count').text()).toBe('3');
    });

    it('opens the author creation page without creating or saving a record', async () => {
        const { wrapper, save, push } = await createWrapper();
        await wrapper.get('.sw-blog-author-list__button-create').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.author.create' });
        expect(save).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-blog-author-list__create-modal').exists()).toBe(false);
    });

    it('does not delete an author who still signs entries', async () => {
        const { wrapper, remove } = await createWrapper();

        await wrapper.get('[data-row="author-1"] .delete-action button').trigger('click');

        expect(wrapper.find('.sw-blog-author-list__delete-modal').exists()).toBe(true);
        expect(wrapper.find('.sw-blog-author-list__delete-deactivate').exists()).toBe(true);
        expect(wrapper.find('.sw-blog-author-list__delete-confirm').exists()).toBe(false);

        expect(remove).not.toHaveBeenCalled();
    });

    it('switches an author off instead of deleting them', async () => {
        const { wrapper, save, remove } = await createWrapper();

        await wrapper.get('[data-row="author-1"] .delete-action button').trigger('click');
        await wrapper.get('.sw-blog-author-list__delete-deactivate').trigger('click');
        await flushPromises();

        expect(remove).not.toHaveBeenCalled();
        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'author-1', active: false }));
    });

    it('deletes an author without entries after the confirmation', async () => {
        const { wrapper, remove } = await createWrapper();

        await wrapper.get('[data-row="author-2"] .delete-action button').trigger('click');
        expect(remove).not.toHaveBeenCalled();
        await wrapper.get('.sw-blog-author-list__delete-confirm').trigger('click');
        await flushPromises();

        expect(remove).toHaveBeenCalledWith('author-2');
    });

    it('opens the entries of one author in the filtered entry list', async () => {
        const { wrapper, push } = await createWrapper();

        await wrapper.get('.sw-blog-author-list__entry-count').trigger('click');

        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.list', query: { authorId: 'author-1' } });
    });

    it('falls back to the first letter when an author has no portrait', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.findAll('.sw-blog-author-list__avatar-initial')[0].text()).toBe('A');
    });

    it('hides creating and deleting from a role that may only read', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege === 'blog.viewer');

        expect(wrapper.get('.sw-blog-author-list__button-create').attributes('disabled')).toBeDefined();
        expect(wrapper.findAll('.delete-action button').every((button) => button.attributes('disabled') !== undefined)).toBe(
            true,
        );
        expect(wrapper.findAll('input[type="checkbox"]').every((input) => input.attributes('disabled') !== undefined)).toBe(
            true,
        );
    });

    it('saves the switch of the active column', async () => {
        const { wrapper, save } = await createWrapper();

        await wrapper.get('[data-row="author-2"] input[type="checkbox"]').setValue(true);

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'author-2', active: true }));
    });
    it('uses the Blog navigation without a duplicate return button', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-blog-author-list__button-entries').exists()).toBe(false);
    });
    it('shows the list results without duplicating the main menu', async () => {
        const { wrapper } = await createWrapper();
        const content = wrapper.get('.sw-blog-author-list__content');
        expect(content.find('sw-blog-navigation-stub').exists()).toBe(false);
        expect(content.find('.sw-blog-author-list__results').exists()).toBe(true);
        expect(content.find('.sw-blog-author-list__results sw-blog-navigation-stub').exists()).toBe(false);
    });
    it('shows no matches after a search instead of the initial empty state', async () => {
        const { wrapper, search } = await createWrapper();
        await flushPromises();
        search.mockResolvedValue(collection<EntityCollection<'blog_author'>>([]));
        await wrapper.setData({ disableRouteParams: true });
        await wrapper.get('.search-input').setValue('Missing');
        await flushPromises();
        expect(wrapper.get('mt-empty-state-stub').attributes('headline')).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.get('mt-empty-state-stub').attributes('description')).toBe(
            'sw-empty-state.messageNoResultSublineSimple',
        );
    });
});
