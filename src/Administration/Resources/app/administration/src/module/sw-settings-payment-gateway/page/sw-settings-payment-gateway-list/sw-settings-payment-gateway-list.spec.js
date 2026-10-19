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
        providers: [
            { providerTechnicalName: 'alipay', enabled: true },
            { providerTechnicalName: 'wechat_pay', enabled: false },
        ],
        channels: [
            { channelTechnicalName: 'alipay_h5', enabled: true },
            { channelTechnicalName: 'alipay_app', enabled: false },
        ],
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
    // the tenant of the platform is seeded by the migration and listed read only, its type is the
    // only difference to the rows an administrator creates
    {
        id: 'app-platform',
        technicalName: 'platform',
        name: '平台支付',
        type: 'platform',
        active: true,
        hasSecret: true,
        providers: [{ providerTechnicalName: 'alipay', enabled: true }],
        channels: [{ channelTechnicalName: 'alipay_h5', enabled: true }],
    },
];

/**
 * The grid is stubbed on purpose: it renders the slots of this page and nothing else, which is the
 * part under test. Everything it renders around them is covered by the specs of the grid itself.
 */
const dataGridStub = {
    name: 'sw-data-grid',
    props: ['dataSource', 'columns', 'isLoading'],
    template: `
        <div class="sw-data-grid">
            <div class="sw-data-grid__header">
                <span
                    v-for="column in columns"
                    :key="column.property"
                    class="sw-data-grid__header-cell"
                >
                    {{ column.property }}
                </span>
            </div>
            <div
                v-for="item in dataSource"
                :key="item.id"
                class="sw-data-grid__row"
            >
                <div class="sw-data-grid__cell">
                    <slot name="column-name" :item="item" />
                </div>
                <div class="sw-data-grid__cell">
                    <slot name="column-active" :item="item" />
                </div>
                <div class="sw-data-grid__cell">
                    <slot name="column-providers" :item="item" />
                </div>
                <div class="sw-data-grid__cell">
                    <slot name="column-channels" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--actions">
                    <slot name="actions" :item="item" />
                </div>
            </div>
        </div>`,
};

async function createWrapper({ listApps = () => Promise.resolve(apps), can = () => true } = {}) {
    const service = {
        listApps: jest.fn(listApps),
        deleteApp: jest.fn(() => Promise.resolve()),
    };

    const push = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-payment-gateway-list', { sync: true }), {
        global: {
            mocks: {
                // the text itself is not under test, only which key is looked up
                $t: (key) => key,
                $route: { meta: { $module: { icon: 'regular-plug' } } },
                $router: { push },
            },
            provide: {
                paymentGatewayService: service,
                acl: { can },
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
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': { template: '<div><slot name="grid"></slot></div>' },
                'sw-data-grid': dataGridStub,
                'sw-context-menu-item': {
                    name: 'sw-context-menu-item',
                    props: ['routerLink', 'disabled', 'variant'],
                    template: '<div class="sw-context-menu-item"><slot></slot></div>',
                },
                'router-link': {
                    props: ['to'],
                    template: '<a :href="JSON.stringify(to)"><slot></slot></a>',
                },
                'mt-search': true,
                'mt-button': { template: '<button><slot></slot></button>' },
                'mt-badge': { template: '<span class="mt-badge"><slot></slot></span>' },
                'mt-icon': true,
                'mt-empty-state': {
                    props: ['headline', 'description', 'icon'],
                    template: `
                        <div class="mt-empty-state">
                            <span class="mt-empty-state__headline">{{ headline }}</span>
                            <slot name="button"></slot>
                        </div>`,
                },
                'mt-modal-root': { template: '<div><slot></slot></div>' },
                'mt-modal': { template: '<div><slot></slot><slot name="footer"></slot></div>' },
                'mt-modal-close': true,
                'mt-modal-action': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, service, push };
}

describe('module/sw-settings-payment-gateway/page/sw-settings-payment-gateway-list', () => {
    it('should load the apps of the gateway', async () => {
        const { wrapper, service } = await createWrapper();

        expect(service.listApps).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.apps).toHaveLength(3);
        expect(wrapper.vm.filteredApps).toHaveLength(3);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should describe the columns of the app list without the technical name', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.appColumns.map((column) => column.property)).toEqual([
            'name',
            'active',
            'providers',
            'channels',
        ]);
        expect(wrapper.vm.appColumns[0].primary).toBe(true);

        // the technical name is how an app authenticates, not what it is called - it is not displayed
        expect(wrapper.vm.appColumns.some((column) => column.property === 'technicalName')).toBe(false);
        expect(wrapper.find('.sw-data-grid__header').text()).not.toContain('technicalName');
        expect(wrapper.text()).not.toContain('pos');
        expect(wrapper.text()).not.toContain('shop_app');
    });

    it('should render the name of an app as the primary column and nothing else', async () => {
        const { wrapper } = await createWrapper();

        const firstRow = wrapper.findAll('.sw-data-grid__row').at(0);

        expect(firstRow.find('.sw-settings-payment-gateway-list__name-link').text()).toBe('POS 收银');
        expect(firstRow.text()).not.toContain('pos');
    });

    it('should count only the enabled interfaces and channels', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.enabledProviderCount(apps[0])).toBe(1);
        expect(wrapper.vm.enabledProviderCount(apps[1])).toBe(0);
        expect(wrapper.vm.enabledProviderCount({})).toBe(0);

        expect(wrapper.vm.enabledChannelCount(apps[0])).toBe(1);
        expect(wrapper.vm.enabledChannelCount(apps[1])).toBe(0);
        expect(wrapper.vm.enabledChannelCount({})).toBe(0);
    });

    it('should render the status of an app as a badge', async () => {
        const { wrapper } = await createWrapper();

        const rows = wrapper.findAll('.sw-data-grid__row');

        expect(rows.at(0).find('.sw-settings-payment-gateway-list__status').text()).toBe(
            'sw-settings-payment-gateway.list.labelActive',
        );
        expect(rows.at(1).find('.sw-settings-payment-gateway-list__status').text()).toBe(
            'sw-settings-payment-gateway.list.labelInactive',
        );
    });

    it('should give the row of the platform a badge and nothing to click', async () => {
        const { wrapper } = await createWrapper();

        const platformRow = wrapper.findAll('.sw-data-grid__row').at(2);

        expect(platformRow.find('.sw-settings-payment-gateway-list__name-link').exists()).toBe(false);
        expect(platformRow.find('.sw-settings-payment-gateway-list__name').text()).toBe('平台支付');

        // one badge says why the row carries no actions, it is the only explanation on the page
        expect(platformRow.find('.sw-settings-payment-gateway-list__platform-badge').text()).toBe(
            'sw-settings-payment-gateway.list.badgePlatform',
        );

        expect(platformRow.find('.sw-settings-payment-gateway-list__edit-action').exists()).toBe(false);
        expect(platformRow.find('.sw-settings-payment-gateway-list__delete-action').exists()).toBe(false);
    });

    it('should keep the external rows editable and deletable', async () => {
        const { wrapper } = await createWrapper();

        const externalRow = wrapper.findAll('.sw-data-grid__row').at(0);

        expect(externalRow.find('.sw-settings-payment-gateway-list__name-link').exists()).toBe(true);
        expect(externalRow.find('.sw-settings-payment-gateway-list__edit-action').exists()).toBe(true);
        expect(externalRow.find('.sw-settings-payment-gateway-list__delete-action').exists()).toBe(true);
        expect(externalRow.find('.sw-settings-payment-gateway-list__platform-badge').exists()).toBe(false);
    });

    it('should route the edit action of an external app to its detail page', async () => {
        const { wrapper } = await createWrapper();

        const editAction = wrapper
            .findAllComponents({ name: 'sw-context-menu-item' })
            .find((item) => item.classes('sw-settings-payment-gateway-list__edit-action'));

        // the country list opens the detail page the same way: the item carries the route, no handler
        expect(editAction.props('routerLink')).toEqual({
            name: 'sw.settings.payment.gateway.detail',
            params: { id: 'app-1' },
        });

        // and the name in the primary column is the entry point for viewers without the editor role
        const nameLink = wrapper.findAll('.sw-data-grid__row').at(0)
            .find('.sw-settings-payment-gateway-list__name-link');

        expect(nameLink.attributes('href')).toBe(JSON.stringify({
            name: 'sw.settings.payment.gateway.detail',
            params: { id: 'app-1' },
        }));
    });

    it('should open the detail page of a new app from the create action', async () => {
        const { wrapper, push } = await createWrapper();

        wrapper.vm.onCreate();

        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.gateway.detail', params: { id: 'new' } });
    });

    it('should filter the apps by their name and their technical name', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.onSearch('商城');
        expect(wrapper.vm.filteredApps.map((app) => app.id)).toEqual(['app-2']);

        // the technical name is not displayed but is still what an external app identifies itself
        // with, so an operator who has it in hand finds the row
        wrapper.vm.onSearch('pos');
        expect(wrapper.vm.filteredApps.map((app) => app.id)).toEqual(['app-1']);

        wrapper.vm.onSearch('nothing');
        await flushPromises();

        expect(wrapper.vm.filteredApps).toHaveLength(0);
        expect(wrapper.findAll('.sw-data-grid__row')).toHaveLength(0);
    });

    it('should show the empty state when there is no app yet', async () => {
        const { wrapper } = await createWrapper({ listApps: () => Promise.resolve([]) });

        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-settings-payment-gateway.list.messageEmpty');

        // an installation without an app offers the way to create the first one
        expect(wrapper.find('.sw-settings-payment-gateway-list__empty-state button').exists()).toBe(true);
    });

    it('should show the no result state of the search instead of the empty state', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.mt-empty-state').exists()).toBe(false);

        wrapper.vm.onSearch('nothing');
        await flushPromises();

        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');

        // a search that found nothing is not an installation without apps, so it does not offer
        // to create one
        expect(wrapper.find('.sw-settings-payment-gateway-list__empty-state button').exists()).toBe(false);
    });

    it('should not offer to create an app without the permission to create one', async () => {
        const { wrapper } = await createWrapper({
            listApps: () => Promise.resolve([]),
            can: (privilege) => privilege !== 'payment_app.creator',
        });

        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-payment-gateway-list__empty-state button').exists()).toBe(false);
    });

    it('should refuse to delete the tenant of the platform', async () => {
        const { wrapper, service } = await createWrapper();

        const platformApp = wrapper.vm.apps.find((app) => app.type === 'platform');

        wrapper.vm.onOpenDeleteModal(platformApp);

        expect(wrapper.vm.deleteTarget).toBeNull();
        expect(service.deleteApp).not.toHaveBeenCalled();
    });

    it('should delete an app after the confirmation and load the list again', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.onOpenDeleteModal(apps[0]);
        expect(wrapper.vm.deleteTarget).toEqual(apps[0]);

        await wrapper.vm.onConfirmDelete();
        await flushPromises();

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
});
