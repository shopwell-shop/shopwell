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

async function createWrapper({ rows = [], search, hasSnippet = false } = {}) {
    const repository = {
        search: search ?? jest.fn(() => Promise.resolve(collection(rows))),
        delete: jest.fn(() => Promise.resolve()),
    };

    const wrapper = mount(await wrapTestComponent('sw-settings-sms-gateway-list', { sync: true }), {
        global: {
            mocks: {
                // The translation itself is not under test, only which key is looked up: `$te` says
                // whether the shop knows a label for the key at all.
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

                'sw-context-menu-item': { template: '<button><slot/></button>' },
            },
        },
    });

    await flushPromises();

    return { wrapper, repository };
}

describe('src/module/sw-settings-sms/page/sw-settings-sms-gateway-list', () => {
    let wrapper;
    let repository;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should load the gateways ordered by priority', async () => {
        const rows = [{ id: 'gateway-1', technicalName: 'aliyun', label: 'main', enabled: true, priority: 1 }];
        ({ wrapper, repository } = await createWrapper({ rows }));
        const criteria = repository.search.mock.calls[0][0];

        expect(criteria.limit).toBe(25);
        expect(criteria.sortings).toEqual([expect.objectContaining({ field: 'priority', order: 'ASC' })]);
        expect(wrapper.find('[data-id="gateway-1"]').exists()).toBe(true);
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(1)');
        expect(wrapper.getComponent({ name: 'sw-entity-listing' }).props('isLoading')).toBe(false);
    });

    it.each([
        [
            false,
            'some-extension-provider',
            'named',
            'some-extension-provider',
            'named',
        ],
        [
            true,
            'aliyun',
            'main',
            'sms.gateway.aliyun',
            'main',
        ],
        [
            true,
            'null',
            null,
            'sms.gateway.null',
            'sms.gateway.null',
        ],
    ])('should label the provider and its account (%s, %s)', async (hasSnippet, technicalName, label, provider, account) => {
        ({ wrapper } = await createWrapper({ hasSnippet, rows: [{ id: 'gateway-1', technicalName, label }] }));
        const row = wrapper.get('[data-id="gateway-1"]');

        expect(row.text()).toContain(provider);
        expect(row.text()).toContain(account);
    });

    it('should search from the first page again', async () => {
        ({ wrapper, repository } = await createWrapper());
        await wrapper.get('.page-two').trigger('click');
        await wrapper.get('.search-input').setValue('aliyun');

        expect(repository.search.mock.calls[2][0].term).toBe('aliyun');
        expect(repository.search.mock.calls[2][0].page).toBe(1);
    });

    it('should load the page it was switched to', async () => {
        ({ wrapper, repository } = await createWrapper());
        await wrapper.get('.page-two').trigger('click');

        expect(repository.search.mock.calls[1][0].page).toBe(2);
        expect(repository.search.mock.calls[1][0].limit).toBe(10);
    });

    it('should load the list again after sorting by another column', async () => {
        ({ wrapper, repository } = await createWrapper());
        await wrapper.get('.sort-label').trigger('click');

        expect(repository.search.mock.calls[1][0].sortings).toEqual([
            expect.objectContaining({ field: 'label', order: 'DESC' }),
        ]);
    });

    it('should delete the gateway after the confirmation and load the list again', async () => {
        ({ wrapper, repository } = await createWrapper({ rows: [{ id: 'gateway-1', technicalName: 'aliyun' }] }));
        await wrapper.get('.sw-settings-sms-gateway-list__delete-action').trigger('click');
        expect(wrapper.find('.confirm-remove').exists()).toBe(true);
        await wrapper.get('.confirm-remove').trigger('click');
        await flushPromises();

        expect(repository.delete).toHaveBeenCalledWith('gateway-1');
        expect(wrapper.find('.confirm-remove').exists()).toBe(false);
        expect(repository.search).toHaveBeenCalledTimes(2);
    });

    it('should keep the gateway when the modal is closed instead of confirmed', async () => {
        ({ wrapper, repository } = await createWrapper({ rows: [{ id: 'gateway-1', technicalName: 'aliyun' }] }));
        await wrapper.get('.sw-settings-sms-gateway-list__delete-action').trigger('click');
        await wrapper.get('.dismiss-modal').trigger('click');

        expect(wrapper.find('.confirm-remove').exists()).toBe(false);
        expect(repository.delete).not.toHaveBeenCalled();
    });
});
