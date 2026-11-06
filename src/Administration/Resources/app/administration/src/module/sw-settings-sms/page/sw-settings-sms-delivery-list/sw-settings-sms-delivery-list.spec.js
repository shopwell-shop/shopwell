/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

function collection(items) {
    return Object.assign(items, {
        total: items.length,
        getIds: () => items.map((item) => item.id),
        get: (id) => items.find((item) => item.id === id),
    });
}

async function createWrapper({ rows = [], hasSnippet = false } = {}) {
    const repository = {
        search: jest.fn(() => Promise.resolve(collection(rows))),
    };

    const wrapper = mount(await wrapTestComponent('sw-settings-sms-delivery-list', { sync: true }), {
        global: {
            mocks: {
                $t: (key) => key,
                $te: () => hasSnippet,
                // the icon of the empty state is the one of the module the route belongs to
                $route: { meta: { $module: { icon: 'regular-envelope' } } },
            },
            provide: {
                repositoryFactory: {
                    create: () => repository,
                },
                acl: {
                    can: () => true,
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
                'sw-search-bar': {
                    template: `<input class="search-input" @input="$emit('search', $event.target.value)"/>`,
                },
                'sw-entity-listing': {
                    name: 'sw-entity-listing',
                    props: [
                        'dataSource',
                        'isLoading',
                        'sortBy',
                        'sortDirection',
                    ],
                    template: `<section>
                        <button class="page-two" @click="$emit('page-change', { page: 2, limit: 10 })">Next</button>
                        <button class="sort-label" @click="$emit('column-sort', { sortBy: 'label', sortDirection: 'DESC' })">Sort</button>
                        <article v-for="item in dataSource" :key="item.id" :data-id="item.id">
                            <slot v-for="(_, name) in $slots" :name="name" :item="item"/>
                        </article>
                    </section>`,
                },
                'sw-settings-sms-section-tabs': true,
                'mt-modal-root': {
                    template: `<div><button class="dismiss-modal" @click="$emit('change', false)">Dismiss</button><slot/></div>`,
                },
                'mt-modal': { template: '<div><slot/><slot name="footer"/></div>' },
                'mt-modal-action': {
                    emits: ['click'],
                    template: '<button class="confirm-remove" @click="$emit(\'click\')"><slot/></button>',
                },
                'mt-modal-close': true,
                'mt-card': { template: '<div><slot/><slot name="toolbar"/><slot name="grid"/></div>' },
                'mt-badge': { props: ['variant'], template: '<span :data-variant="variant"><slot/></span>' },

                'sw-single-select': true,
                'mt-select': {
                    name: 'mt-select',
                    props: ['modelValue', 'options'],
                    template: `<select :value="modelValue" @change="$emit('update:modelValue', $event.target.value || null)">
                        <option value="">All</option><option v-for="option in options" :value="option.value">{{ option.label }}</option>
                    </select>`,
                },
            },
        },
    });

    await flushPromises();

    return { wrapper, repository };
}

describe('src/module/sw-settings-sms/page/sw-settings-sms-delivery-list', () => {
    let wrapper;
    let repository;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should load the log newest first', async () => {
        ({ wrapper, repository } = await createWrapper({ rows: [{ id: 'delivery-1', status: 'sent' }] }));
        expect(repository.search.mock.calls[0][0].sortings).toEqual([
            expect.objectContaining({ field: 'createdAt', order: 'DESC' }),
        ]);
        expect(wrapper.find('[data-id="delivery-1"]').exists()).toBe(true);
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(1)');
    });

    it('should filter by state and search from the first page again', async () => {
        ({ wrapper, repository } = await createWrapper());
        await wrapper.get('.page-two').trigger('click');
        await wrapper.get('select').setValue('failed');
        expect(repository.search.mock.calls[2][0].page).toBe(1);
        expect(repository.search.mock.calls[2][0].filters).toEqual([
            expect.objectContaining({ field: 'status', value: 'failed' }),
        ]);
    });

    it('should drop the filter again', async () => {
        ({ wrapper, repository } = await createWrapper());
        await wrapper.get('select').setValue('sent');
        await wrapper.get('select').setValue('');
        expect(repository.search.mock.calls[1][0].filters).toHaveLength(1);
        expect(repository.search.mock.calls[2][0].filters).toHaveLength(0);
    });

    it.each([
        ['sent', 'positive'],
        ['failed', 'critical'],
        ['queued', 'attention'],
    ])('should render %s as a %s badge', async (status, variant) => {
        ({ wrapper } = await createWrapper({ hasSnippet: false, rows: [{ id: 'delivery-1', status }] }));
        expect(wrapper.get('[data-variant]').attributes('data-variant')).toBe(variant);
        expect(wrapper.get('[data-variant]').text()).toBe(status);
    });
});
