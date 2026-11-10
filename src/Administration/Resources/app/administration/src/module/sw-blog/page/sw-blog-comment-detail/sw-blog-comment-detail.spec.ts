/**
 * @sw-package discovery
 */
import { flushPromises, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../service/blog.api.service';

type Association = { association: string; criteria: { associations: Association[] } };

function associationNames(criteria: { associations: unknown }, prefix: string = ''): string[] {
    return (criteria.associations as Association[]).flatMap((association) => {
        const name = `${prefix}${association.association}`;

        return [name, ...associationNames(association.criteria, `${name}.`)];
    });
}

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

function callArgument<T>(mock: jest.Mock, call = 0, argument = 0): T {
    const calls = mock.mock.calls as unknown as T[][];

    return calls[call][argument];
}

const comment = {
    id: 'comment-1',
    blogId: 'blog-1',
    title: null,
    content: 'When does the restock arrive?',
    status: false,
    languageId: 'language-1',
    externalUser: 'Ada',
    externalEmail: 'ada@example.com',
    customerId: null,
    blog: { id: 'blog-1', translated: { title: 'Winter guide' } },
};

const customerComment = {
    ...comment,
    id: 'comment-2',
    externalUser: null,
    externalEmail: null,
    customerId: 'customer-1',
};

const customer = { id: 'customer-1', name: 'Grace Lee', email: 'grace@example.com' };

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    options: {
        can?: (privilege: string) => boolean;
        entry?: Record<string, unknown>;
    } = {},
): Promise<{
    wrapper: VueWrapper;
    getComment: jest.Mock;
    saveComment: jest.Mock;
    push: jest.Mock;
}> {
    const { can = () => true, entry = comment } = options;

    const getComment = jest.fn(() => Promise.resolve({ ...entry }));
    const saveComment = jest.fn(() => Promise.resolve());
    const customerSearch = jest.fn(() => Promise.resolve(collection<EntityCollection<'customer'>>([customer])));
    const push = jest.fn(() => Promise.resolve());

    const repositoryFactory = {
        create: (entityName: string) => {
            if (entityName === 'customer') {
                return { search: customerSearch };
            }

            return { get: getComment, save: saveComment };
        },
    };

    const wrapper = shallowMount(await wrapTestComponent('sw-blog-comment-detail', { sync: true }), {
        props: { commentId: 'comment-1' },
        global: {
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"></slot><slot name="smart-bar-actions"></slot><slot name="language-switch"></slot><slot name="content"></slot></div>',
                },
                'sw-card-view': { template: '<div><slot /></div>' },
                'mt-card': { template: '<div><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-description-list': { template: '<dl><slot></slot></dl>' },
                'sw-time-ago': true,
                'sw-skeleton': true,
                'sw-entity-single-select': true,
                'mt-badge': { props: ['variant'], template: '<span :data-variant="variant"><slot></slot></span>' },
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
                repositoryFactory,
                acl: { can },
                blogApiService: new BlogApiService(),
            },
            mocks: {
                $route: { name: 'sw.blog.comment.detail', params: { id: 'comment-1' }, meta: {} },
                $router: { push, replace: jest.fn(() => Promise.resolve()) },
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return { wrapper, getComment, saveComment, push };
}

describe('src/module/sw-blog/page/sw-blog-comment-detail', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.restoreAllMocks();
    });

    it('reads the comment with the entry it belongs to', async () => {
        const { wrapper, getComment } = await createWrapper();
        await flushPromises();

        const criteria = callArgument<{ associations: unknown[] }>(getComment, 0, 2);

        expect(associationNames(criteria)).toEqual(['blog', 'blog.author']);
        expect(wrapper.get('.sw-blog-comment-detail__text-body').text()).toBe('When does the restock arrive?');
        expect(wrapper.find('.sw-blog-comment-detail__loading').exists()).toBe(false);
    });

    it('stands in the first words of the text when the comment has no headline', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(
            wrapper.get('.sw-blog-comment-detail__header').text().replace('sw-blog.comment.filterPending', '').trim(),
        ).toBe('When does the restock arrive?');
    });

    it('shows the headline of a comment that has one', async () => {
        const { wrapper } = await createWrapper({ entry: { ...comment, title: 'Question about restock' } });
        await flushPromises();

        expect(
            wrapper.get('.sw-blog-comment-detail__header').text().replace('sw-blog.comment.filterPending', '').trim(),
        ).toBe('Question about restock');
    });

    it('names a guest with the fields they typed', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('dd')[2].text()).toBe('Ada');
        expect(wrapper.findAll('dd')[3].text()).toBe('ada@example.com');
    });

    it('reads the account of a customer comment in a second request', async () => {
        const { wrapper } = await createWrapper({ entry: customerComment });
        await flushPromises();

        expect(wrapper.findAll('dd')[2].text()).toBe('Grace Lee');
        expect(wrapper.findAll('dd')[3].text()).toBe('grace@example.com');
    });

    it('shows the state as the word the list uses for it', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.get('.sw-blog-comment-detail__header-status').text()).toBe('sw-blog.comment.filterPending');
    });

    it('writes the state and reads the comment back', async () => {
        const { wrapper, getComment, saveComment } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-comment-detail__status').setValue(true);
        await wrapper.get('.sw-blog-comment-detail__action-save').trigger('click');
        await flushPromises();

        expect(saveComment).toHaveBeenCalledWith(expect.objectContaining({ id: 'comment-1', status: true }));
        // the second read is the reload after the save
        expect(getComment).toHaveBeenCalledTimes(2);
        expect(wrapper.get('.sw-blog-comment-detail__action-save').attributes('data-loading')).toBe('false');
    });

    it('keeps saving away from a role that may only read', async () => {
        const { wrapper } = await createWrapper({ can: (privilege) => privilege === 'blog.viewer' });

        await flushPromises();
        expect(wrapper.get('.sw-blog-comment-detail__action-save').attributes('disabled')).toBeDefined();
    });

    it('returns to the queue and jumps to the entry on demand', async () => {
        const { wrapper, push } = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-blog-comment-detail__blog-link').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.detail', params: { id: 'blog-1' } });

        await wrapper.get('.sw-blog-comment-detail__action-cancel').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'sw.blog.comment.list' });
    });
    it('locks moderation during a save and retains the change after a failed request', async () => {
        const { wrapper, saveComment, getComment } = await createWrapper();
        await flushPromises();
        let rejectSave: (error: Error) => void = () => {};
        saveComment.mockImplementationOnce(
            () =>
                new Promise((resolve, reject) => {
                    rejectSave = reject;
                }),
        );
        await wrapper.get('.sw-blog-comment-detail__status').setValue(true);
        await wrapper.get('.sw-blog-comment-detail__action-save').trigger('click');
        expect(wrapper.get('.sw-blog-comment-detail__status').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.sw-blog-comment-detail__language').attributes('disabled')).toBe('true');
        rejectSave(new Error('Save failed'));
        await flushPromises();
        expect(wrapper.get('.sw-blog-comment-detail__status').attributes('disabled')).toBeUndefined();
        expect((wrapper.get('.sw-blog-comment-detail__status').element as HTMLInputElement).checked).toBe(true);
        expect(getComment).toHaveBeenCalledTimes(1);
    });
});
