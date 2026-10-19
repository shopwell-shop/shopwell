/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

function collection(items) {
    return {
        total: items.length,
        length: items.length,
        getIds: () => items.map((item) => item.id),
        get: (id) => items.find((item) => item.id === id),
    };
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
                'sw-search-bar': true,
                'sw-entity-listing': true,
                'sw-settings-sms-section-tabs': true,
                'sw-context-menu-item': true,
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
        const rows = [
            { id: 'template-1', technicalName: 'customer.otp.login', scene: 'otp', maxLength: 140, enabled: true },
        ];

        ({ wrapper, repository } = await createWrapper({ rows }));

        expect(repository.search).toHaveBeenCalledTimes(1);

        const criteria = repository.search.mock.calls[0][0];

        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'technicalName', order: 'ASC' }),
        ]);
        expect(wrapper.vm.templates.getIds()).toEqual(['template-1']);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should label the scene of a template and fall back to the stored value', async () => {
        ({ wrapper } = await createWrapper({ hasSnippet: true }));
        expect(wrapper.vm.sceneLabel('otp')).toBe('sw-settings-sms.template.scene.otp');

        ({ wrapper } = await createWrapper({ hasSnippet: false }));
        expect(wrapper.vm.sceneLabel('otp')).toBe('otp');
    });

    it('should search again from the first page', async () => {
        ({ wrapper, repository } = await createWrapper());

        wrapper.vm.page = 2;
        wrapper.vm.onSearch('otp');

        expect(wrapper.vm.term).toBe('otp');
        expect(wrapper.vm.page).toBe(1);
        expect(repository.search.mock.calls[1][0].term).toBe('otp');
    });

    it('should delete the template after the confirmation', async () => {
        const rows = [
            { id: 'template-1', technicalName: 'customer.otp.login', scene: 'otp', enabled: true },
        ];

        ({ wrapper, repository } = await createWrapper({ rows }));

        wrapper.vm.onOpenRemoveModal(wrapper.vm.templates.get('template-1'));
        await wrapper.vm.onConfirmRemove();

        expect(repository.delete).toHaveBeenCalledWith('template-1');
        expect(wrapper.vm.removeTarget).toBeNull();
        expect(repository.search).toHaveBeenCalledTimes(2);
    });
});
