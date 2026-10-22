/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

function collection(items) {
    return {
        total: items.length,
        length: items.length,
        getIds: () => items.map((item) => item.id),
        forEach: (callback) => items.forEach(callback),
        get: (id) => items.find((item) => item.id === id),
    };
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

describe('src/module/sw-settings-sms/page/sw-settings-sms-gateway-list', () => {
    let wrapper;
    let repository;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should load the gateways ordered by priority', async () => {
        const rows = [
            { id: 'gateway-1', technicalName: 'aliyun', label: 'main', enabled: true, priority: 1 },
        ];
        const search = jest.fn(() => Promise.resolve(collection(rows)));

        ({ wrapper } = await createWrapper({ search }));

        expect(search).toHaveBeenCalledTimes(1);

        const criteria = search.mock.calls[0][0];

        expect(criteria.limit).toBe(25);
        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'priority', order: 'ASC' }),
        ]);
        expect(wrapper.vm.gateways.getIds()).toEqual(['gateway-1']);
        expect(wrapper.vm.total).toBe(1);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should show the technical name of a provider it has no snippet for', async () => {
        ({ wrapper } = await createWrapper({ hasSnippet: false }));

        // a provider of the platform carries a snippet, one an extension added may ship none
        expect(wrapper.vm.providerLabel('some-extension-provider')).toBe('some-extension-provider');
    });

    it('should look up the label of a provider that ships one', async () => {
        ({ wrapper } = await createWrapper({ hasSnippet: true }));

        expect(wrapper.vm.providerLabel('aliyun')).toBe('sms.gateway.aliyun');
    });

    it('should name a gateway by the name an administrator gave it', async () => {
        ({ wrapper } = await createWrapper({ hasSnippet: true }));

        expect(wrapper.vm.gatewayName({ technicalName: 'aliyun', label: 'main' })).toBe('main');
    });

    it('should name a gateway by its provider when it carries no name', async () => {
        ({ wrapper } = await createWrapper({ hasSnippet: true }));

        // the seeded baseline gateway has no name of its own, the provider names it
        expect(wrapper.vm.gatewayName({ technicalName: 'null', label: null })).toBe('sms.gateway.null');
    });

    it('should search from the first page again', async () => {
        const search = jest.fn(() => Promise.resolve(collection([])));

        ({ wrapper } = await createWrapper({ search }));

        wrapper.vm.page = 3;
        wrapper.vm.onSearch('aliyun');

        expect(wrapper.vm.term).toBe('aliyun');
        expect(wrapper.vm.page).toBe(1);
        expect(search).toHaveBeenCalledTimes(2);

        const criteria = search.mock.calls[1][0];

        expect(criteria.term).toBe('aliyun');
        expect(criteria.page).toBe(1);
    });

    it('should load the page it was switched to', async () => {
        const search = jest.fn(() => Promise.resolve(collection([])));

        ({ wrapper } = await createWrapper({ search }));

        wrapper.vm.onPageChange({ page: 2, limit: 10 });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(10);

        const criteria = search.mock.calls[1][0];

        expect(criteria.page).toBe(2);
        expect(criteria.limit).toBe(10);
    });

    it('should load the list again after sorting by another column', async () => {
        const search = jest.fn(() => Promise.resolve(collection([])));

        ({ wrapper } = await createWrapper({ search }));

        wrapper.vm.onSortColumn({ sortBy: 'label', sortDirection: 'DESC' });

        expect(wrapper.vm.sortBy).toBe('label');
        expect(wrapper.vm.sortDirection).toBe('DESC');

        const criteria = search.mock.calls[1][0];

        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'label', order: 'DESC' }),
        ]);
    });

    it('should delete the gateway after the confirmation and load the list again', async () => {
        const rows = [
            { id: 'gateway-1', technicalName: 'aliyun', enabled: true, priority: 1 },
        ];

        ({ wrapper, repository } = await createWrapper({ rows }));

        wrapper.vm.onOpenRemoveModal(wrapper.vm.gateways.get('gateway-1'));
        expect(wrapper.vm.removeTarget.id).toBe('gateway-1');

        await wrapper.vm.onConfirmRemove();
        await flushPromises();

        expect(repository.delete).toHaveBeenCalledWith('gateway-1');
        expect(wrapper.vm.removeTarget).toBeNull();
        expect(repository.search).toHaveBeenCalledTimes(2);
    });

    it('should keep the gateway when the modal is closed instead of confirmed', async () => {
        const rows = [
            { id: 'gateway-1', technicalName: 'aliyun', enabled: true, priority: 1 },
        ];

        ({ wrapper, repository } = await createWrapper({ rows }));

        wrapper.vm.onOpenRemoveModal(wrapper.vm.gateways.get('gateway-1'));
        wrapper.vm.onRemoveModalChange(false);

        expect(wrapper.vm.removeTarget).toBeNull();
        expect(repository.delete).not.toHaveBeenCalled();
    });
});
