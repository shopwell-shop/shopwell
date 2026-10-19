/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const apps = [
    {
        id: 'app-1',
        technicalName: 'pos',
        name: 'POS 收银',
        type: 'external',
        active: true,
        hasSecret: true,
        providers: [{ providerTechnicalName: 'alipay', enabled: true, mode: 'platform' }],
        channels: [{ channelTechnicalName: 'alipay_h5', enabled: true }],
    },
    {
        id: 'app-2',
        technicalName: 'shop_app',
        name: '商城应用',
        type: 'external',
        active: false,
        hasSecret: false,
        providers: [],
        channels: [],
    },
    // the tenant of the platform is seeded by the migration and listed read only - it is named last
    // here on purpose, the order of the list is the answer of the server and not of the page
    {
        id: 'app-platform',
        technicalName: 'platform',
        name: '平台支付',
        type: 'platform',
        active: true,
        hasSecret: true,
        // the migration authorizes the interface of the channel it switches on, so a fresh
        // installation shows which account can really collect
        providers: [{ providerTechnicalName: 'alipay', enabled: true, mode: 'platform' }],
        channels: [{ channelTechnicalName: 'alipay_h5', enabled: true }],
    },
];

async function createWrapper() {
    const service = {
        listApps: jest.fn(() => Promise.resolve(apps)),
        deleteApp: jest.fn(() => Promise.resolve()),
    };

    const push = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-payment-gateway-list', { sync: true }), {
        global: {
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
                'sw-data-grid': {
                    props: ['dataSource', 'columns'],
                    template: `
                        <div class="sw-data-grid-stub">
                            <div
                                v-for="item in dataSource"
                                :key="item.id"
                                class="sw-data-grid-stub__row"
                            >
                                <slot name="column-technicalName" :item="item" />
                                <slot name="column-name" :item="item" />
                                <slot name="actions" :item="item" />
                            </div>
                        </div>`,
                },
                'sw-context-menu-item': { template: '<div><slot></slot></div>' },
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-search': true,
                'mt-button': true,
                'mt-badge': true,
                'mt-icon': true,
                'mt-empty-state': { template: '<div><slot name="button"></slot></div>' },
                'mt-modal-root': { template: '<div><slot></slot></div>' },
                'mt-modal': { template: '<div><slot></slot><slot name="footer"></slot></div>' },
                'mt-modal-close': true,
                'mt-modal-action': true,
            },
            provide: {
                paymentGatewayService: service,
            },
            mocks: {
                $router: { push },
            },
        },
    });

    await flushPromises();

    return { wrapper, service, push };
}

describe('module/sw-settings-payment-gateway/page/sw-settings-payment-gateway-list', () => {
    beforeEach(() => {
        global.activeAclRoles = [
            'payment_app.viewer',
            'payment_app.creator',
            'payment_app.editor',
            'payment_app.deleter',
        ];
    });

    it('should load the apps of the gateway', async () => {
        const { wrapper, service } = await createWrapper();

        expect(service.listApps).toHaveBeenCalled();
        expect(wrapper.vm.apps).toHaveLength(3);
        expect(wrapper.vm.filteredApps).toHaveLength(3);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should filter the apps by their technical name and their name', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.onSearch('shop');
        expect(wrapper.vm.filteredApps.map((app) => app.id)).toEqual(['app-2']);

        // the demo app is still findable, but through the new name of the seeded row
        wrapper.vm.onSearch('POS');
        expect(wrapper.vm.filteredApps.map((app) => app.id)).toEqual(['app-1']);

        wrapper.vm.onSearch('nothing');
        expect(wrapper.vm.filteredApps).toHaveLength(0);
    });

    it('should open the detail page of an app and of a new one', async () => {
        const { wrapper, push } = await createWrapper();

        wrapper.vm.onEdit(apps[0]);
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.gateway.detail', params: { id: 'app-1' } });

        wrapper.vm.onCreate();
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.gateway.detail', params: { id: 'new' } });
    });

    it('should render the tenant of the platform as a read only row', async () => {
        const { wrapper } = await createWrapper();

        const rows = wrapper.findAll('.sw-data-grid-stub__row');
        const platformRow = rows.at(rows.length - 1);

        // the row tells the merchant which account the platform collects through
        expect(platformRow.find('.sw-settings-payment-gateway-list__platform-badge').exists()).toBe(true);
        expect(platformRow.find('.sw-settings-payment-gateway-list__platform-providers').text()).toContain('alipay');
        expect(platformRow.find('.sw-settings-payment-gateway-list__platform-hint').text()).toBe(
            'sw-settings-payment-gateway.list.platformReadOnlyHint',
        );

        // and it cannot be opened or deleted, because the server refuses both
        expect(platformRow.find('a').exists()).toBe(false);
        expect(platformRow.find('.sw-settings-payment-gateway-list__edit-action').exists()).toBe(false);
        expect(platformRow.find('.sw-settings-payment-gateway-list__delete-action').exists()).toBe(false);
    });

    it('should keep the external rows editable and deletable', async () => {
        const { wrapper } = await createWrapper();

        const externalRow = wrapper.findAll('.sw-data-grid-stub__row').at(0);

        expect(externalRow.find('a').exists()).toBe(true);
        expect(externalRow.find('.sw-settings-payment-gateway-list__edit-action').exists()).toBe(true);
        expect(externalRow.find('.sw-settings-payment-gateway-list__delete-action').exists()).toBe(true);
    });

    it('should name the interfaces the platform activated', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.enabledProviderLabels(apps[2])).toEqual(['alipay']);

        // an interface that is not switched on is not activated, whatever else the row carries
        expect(wrapper.vm.enabledProviderLabels({
            providers: [
                { providerTechnicalName: 'alipay', enabled: false },
                { providerTechnicalName: 'wechat_pay', enabled: true },
            ],
        })).toEqual(['wechat_pay']);

        expect(wrapper.vm.enabledProviderLabels({ providers: [] })).toEqual([]);
    });

    it('should refuse to edit or delete the tenant of the platform', async () => {
        const { wrapper, push, service } = await createWrapper();

        const platformApp = wrapper.vm.apps.find((app) => app.type === 'platform');

        wrapper.vm.onEdit(platformApp);
        expect(push).not.toHaveBeenCalled();

        wrapper.vm.onOpenDeleteModal(platformApp);
        expect(wrapper.vm.deleteTarget).toBeNull();
        expect(service.deleteApp).not.toHaveBeenCalled();
    });

    it('should delete an app after the confirmation', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.onOpenDeleteModal(apps[0]);
        expect(wrapper.vm.deleteTarget).toEqual(apps[0]);

        await wrapper.vm.onConfirmDelete();

        expect(service.deleteApp).toHaveBeenCalledWith('app-1');
        expect(wrapper.vm.deleteTarget).toBeNull();
        expect(service.listApps).toHaveBeenCalledTimes(2);
    });

    it('should keep the app when the delete modal is closed instead of confirmed', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.onOpenDeleteModal(apps[0]);
        wrapper.vm.onDeleteModalChange(false);

        expect(wrapper.vm.deleteTarget).toBeNull();
        expect(service.deleteApp).not.toHaveBeenCalled();
    });

    it('should describe the columns of the app list', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.appColumns.map((column) => column.property)).toEqual([
            'technicalName',
            'name',
            'providers',
            'channels',
            'active',
        ]);
        expect(wrapper.vm.appColumns[0].primary).toBe(true);
    });
});
