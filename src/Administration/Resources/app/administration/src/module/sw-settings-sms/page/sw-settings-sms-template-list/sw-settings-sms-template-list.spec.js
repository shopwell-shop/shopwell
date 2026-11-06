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
        delete: jest.fn(() => Promise.resolve()),
    };

    const wrapper = mount(await wrapTestComponent('sw-settings-sms-template-list', { sync: true }), {
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

                'sw-context-menu-item': { template: '<button><slot/></button>' },
            },
        },
    });

    await flushPromises();

    return { wrapper, repository };
}

describe('src/module/sw-settings-sms/page/sw-settings-sms-template-list', () => {
    let wrapper;
    let repository;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should load the templates by their technical name', async () => {
        ({ wrapper, repository } = await createWrapper({
            rows: [{ id: 'template-1', technicalName: 'customer.otp.login', scene: 'otp' }],
        }));
        expect(repository.search.mock.calls[0][0].sortings).toEqual([
            expect.objectContaining({ field: 'technicalName', order: 'ASC' }),
        ]);
        expect(wrapper.find('[data-id="template-1"]').exists()).toBe(true);
        expect(wrapper.getComponent({ name: 'sw-entity-listing' }).props('isLoading')).toBe(false);
    });

    it.each([
        [true, 'sw-settings-sms.template.scene.otp'],
        [false, 'otp'],
    ])('should label the scene (%s)', async (hasSnippet, label) => {
        ({ wrapper } = await createWrapper({ hasSnippet, rows: [{ id: 'template-1', scene: 'otp' }] }));
        expect(wrapper.get('[data-id="template-1"]').text()).toContain(label);
    });

    it('should search again from the first page', async () => {
        ({ wrapper, repository } = await createWrapper());
        await wrapper.get('.page-two').trigger('click');
        await wrapper.get('.search-input').setValue('otp');
        expect(repository.search.mock.calls[2][0].term).toBe('otp');
        expect(repository.search.mock.calls[2][0].page).toBe(1);
    });

    it('should delete the template after the confirmation', async () => {
        ({ wrapper, repository } = await createWrapper({
            rows: [{ id: 'template-1', technicalName: 'customer.otp.login', scene: 'otp' }],
        }));
        await wrapper.get('.sw-settings-sms-template-list__delete-action').trigger('click');
        await wrapper.get('.confirm-remove').trigger('click');
        await flushPromises();
        expect(repository.delete).toHaveBeenCalledWith('template-1');
        expect(wrapper.find('.confirm-remove').exists()).toBe(false);
        expect(repository.search).toHaveBeenCalledTimes(2);
    });
});
