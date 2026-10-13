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
                'sw-search-bar': true,
                'sw-entity-listing': true,
                'sw-settings-sms-section-tabs': true,
                'sw-single-select': true,
                'mt-badge': true,
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
        const rows = [
            {
                id: 'delivery-1',
                templateRef: 'customer.otp.login',
                technicalName: 'aliyun',
                phoneMasked: '+86138****0000',
                status: 'sent',
                errorCode: null,
            },
        ];

        ({ wrapper, repository } = await createWrapper({ rows }));

        const criteria = repository.search.mock.calls[0][0];

        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'createdAt', order: 'DESC' }),
        ]);
        expect(wrapper.vm.deliveries.getIds()).toEqual(['delivery-1']);
        expect(wrapper.vm.total).toBe(1);
    });

    it('should filter by the state it was set to and search from the first page again', async () => {
        ({ wrapper, repository } = await createWrapper());

        wrapper.vm.page = 3;
        wrapper.vm.onFilterStatus('failed');

        expect(wrapper.vm.statusFilter).toBe('failed');
        expect(wrapper.vm.page).toBe(1);

        const criteria = repository.search.mock.calls[1][0];

        expect(criteria.filters).toEqual([
            expect.objectContaining({ field: 'status', value: 'failed' }),
        ]);
    });

    it('should drop the filter again', async () => {
        ({ wrapper, repository } = await createWrapper());

        wrapper.vm.onFilterStatus('sent');
        wrapper.vm.onFilterStatus(null);

        expect(wrapper.vm.statusFilter).toBeNull();
        expect(repository.search.mock.calls[1][0].filters).toHaveLength(1);
        expect(repository.search.mock.calls[2][0].filters).toHaveLength(0);
    });

    it('should answer the state of a row as a badge', async () => {
        ({ wrapper } = await createWrapper({ hasSnippet: false }));

        expect(wrapper.vm.statusLabel('sent')).toBe('sent');
        expect(wrapper.vm.statusVariant('sent')).toBe('positive');
        expect(wrapper.vm.statusVariant('failed')).toBe('critical');
        expect(wrapper.vm.statusVariant('queued')).toBe('attention');
    });
});
