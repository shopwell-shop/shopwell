/**
 * @sw-package discovery
 */
import { flushPromises, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

const author = { id: 'author-1', name: 'Anna', mediaId: null, active: true };

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    entries: unknown[] = [{ id: 'blog-1' }],
    can: (privilege: string) => boolean = () => true,
): Promise<{ wrapper: VueWrapper; save: jest.Mock; remove: jest.Mock; push: jest.Mock }> {
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

    const wrapper = shallowMount(await wrapTestComponent('sw-blog-author-detail', { sync: true }), {
        props: { authorId: 'author-1' },
        global: {
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"></slot><slot name="smart-bar-actions"></slot><slot name="language-switch"></slot><slot name="content"></slot></div>',
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-language-switch': {
                    props: ['saveChangesFunction', 'abortChangeFunction'],
                    template:
                        '<section><output class="unsaved">{{ abortChangeFunction() }}</output><button class="language-save" @click="saveChangesFunction()">Save translation</button><button class="language-change" @click="$emit(\'on-change\', \'language-2\')">Change language</button></section>',
                },
                'sw-skeleton': true,
                'sw-media-compact-upload-v2': {
                    props: ['source'],
                    template:
                        '<section><output class="avatar-source">{{ source }}</output><button class="select-avatar" @click="$emit(\'selection-change\', [{ id: \'media-1\' }])">Select portrait</button><button class="remove-avatar" @click="$emit(\'delete-item\')">Remove portrait</button></section>',
                },
                'sw-data-grid': {
                    props: ['dataSource'],
                    template:
                        '<table><tbody><tr v-for="item in dataSource" :key="item.id"><td>{{ item.id }}</td></tr></tbody></table>',
                },
                'sw-time-ago': true,
                'sw-blog-status-badge': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'mt-card': { template: '<div><slot></slot></div>', props: ['title'] },
                'mt-button': {
                    props: ['disabled', 'isLoading'],
                    template: '<button :disabled="disabled" :data-loading="isLoading"><slot></slot></button>',
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
                $t: (key: string, payload?: Record<string, unknown>) =>
                    payload ? `${key}:${JSON.stringify(payload)}` : key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return { wrapper, save, remove, push };
}

describe('src/module/sw-blog/page/sw-blog-author-detail', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.restoreAllMocks();
    });

    it('loads the author and the entries that carry the byline', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect((wrapper.get('.sw-blog-author-detail__name').element as HTMLInputElement).value).toBe('Anna');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
    });

    it('saves without leaving the page', async () => {
        const { wrapper, save, push } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-author-detail__action-save').trigger('click');
        await flushPromises();

        expect(save).toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('refuses to save an author without a name', async () => {
        const { wrapper, save } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-author-detail__name').setValue('   ');

        await wrapper.get('.sw-blog-author-detail__action-save').trigger('click');

        expect((wrapper.get('.sw-blog-author-detail__name').element as HTMLInputElement).value).toBe('   ');
        expect(save).not.toHaveBeenCalled();
    });

    it('writes a chosen portrait and clears it with an explicit null', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        await wrapper.get('.select-avatar').trigger('click');
        expect(wrapper.get('.avatar-source').text()).toBe('media-1');

        await wrapper.get('.remove-avatar').trigger('click');
        expect(wrapper.get('.avatar-source').text()).toBe('');
    });

    it('blocks the delete while entries still carry the byline', async () => {
        const { wrapper, remove } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-author-detail__action-delete').trigger('click');
        expect(wrapper.find('.sw-blog-author-detail__delete-confirm').exists()).toBe(false);

        expect(remove).not.toHaveBeenCalled();
        expect(wrapper.get('.sw-blog-author-detail__delete-message').text()).toContain('textDeleteInUse');
    });

    it('retires an author in use by switching them off', async () => {
        const { wrapper, save, remove } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-author-detail__action-delete').trigger('click');
        await wrapper.get('.sw-blog-author-detail__delete-deactivate').trigger('click');
        await flushPromises();

        expect(remove).not.toHaveBeenCalled();
        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'author-1', active: false }));
    });

    it('deletes an author nobody signs with', async () => {
        const { wrapper, remove, push } = await createWrapper([]);
        await flushPromises();

        await wrapper.get('.sw-blog-author-detail__action-delete').trigger('click');
        expect(wrapper.find('.sw-blog-author-detail__delete-confirm').exists()).toBe(true);

        await wrapper.get('.sw-blog-author-detail__delete-confirm').trigger('click');
        await flushPromises();

        expect(remove).toHaveBeenCalledWith('author-1');
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.author.list' });
    });
});
