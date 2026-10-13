/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const apps = [
    {
        id: 'app-1',
        technicalName: 'alipay_sandbox_demo',
        name: '支付宝沙箱联调应用',
        active: true,
        hasSecret: true,
        providers: [{ providerTechnicalName: 'alipay', enabled: true, mode: 'platform' }],
        channels: [{ channelTechnicalName: 'alipay_h5', enabled: true }],
    },
    {
        id: 'app-2',
        technicalName: 'shop_app',
        name: '商城应用',
        active: false,
        hasSecret: false,
        providers: [],
        channels: [],
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
                    template: '<div class="sw-data-grid-stub"><slot name="actions" :item="dataSource[0]" /></div>',
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
        expect(wrapper.vm.apps).toHaveLength(2);
        expect(wrapper.vm.filteredApps).toHaveLength(2);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should filter the apps by their technical name and their name', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.onSearch('shop');
        expect(wrapper.vm.filteredApps.map((app) => app.id)).toEqual(['app-2']);

        wrapper.vm.onSearch('沙箱');
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
