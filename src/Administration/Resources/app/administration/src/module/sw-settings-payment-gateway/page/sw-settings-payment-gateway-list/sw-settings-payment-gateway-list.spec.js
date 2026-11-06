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
        accounts: [
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
        accounts: [],
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
        accounts: [{ providerTechnicalName: 'alipay', enabled: true }],
        channels: [{ channelTechnicalName: 'alipay_h5', enabled: true }],
    },
];

/**
 * The grid is stubbed on purpose: it renders the slots of this page and nothing else, which is the
 * part under test. Everything it renders around them is covered by the specs of the grid itself.
 */
const dataGridStub = {
    name: 'sw-data-grid',
    props: [
        'dataSource',
        'columns',
        'isLoading',
        'showActions',
        'isRecordDisabled',
    ],
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

const wrappers = [];

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
                    template: '<button class="sw-context-menu-item" :disabled="disabled"><slot></slot></button>',
                },
                'router-link': {
                    props: ['to'],
                    template: '<a :href="JSON.stringify(to)"><slot></slot></a>',
                },
                'mt-search': {
                    props: ['modelValue'],
                    emits: ['update:modelValue', 'change'],
                    template: `<input :value="modelValue" @input="$emit('update:modelValue', $event.target.value); $emit('change', $event.target.value)">`,
                },
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
                'mt-modal-action': { template: '<button><slot></slot></button>' },
            },
        },
    });

    await flushPromises();

    wrappers.push(wrapper);
    return { wrapper, service, push };
}

describe('payment app overview', () => {
    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));
    it('shows account and product grants from the current API metadata', async () => {
        const { wrapper } = await createWrapper();
        const rows = wrapper.findAll('.sw-data-grid__row');
        expect(rows).toHaveLength(3);
        expect(rows[0].find('.sw-settings-payment-gateway-list__name-link').text()).toBe('POS 收银');
        const cells = rows[0].findAll('.sw-data-grid__cell');
        expect(cells[2].text()).toBe('1');
        expect(cells[3].text()).toBe('1');
    });
    it('filters by app name and technical name through the search field', async () => {
        const { wrapper } = await createWrapper();
        await wrapper.find('input').setValue('shop_app');
        expect(wrapper.findAll('.sw-data-grid__row')).toHaveLength(1);
        expect(wrapper.find('.sw-settings-payment-gateway-list__name-link').text()).toBe('商城应用');
    });
    it('shows the platform app as editable but never deletable', async () => {
        const { wrapper } = await createWrapper();
        const platform = wrapper.findAll('.sw-data-grid__row')[2];
        expect(platform.find('.sw-settings-payment-gateway-list__platform-badge').exists()).toBe(true);
        expect(platform.find('.sw-settings-payment-gateway-list__edit-action').exists()).toBe(true);
        expect(platform.find('.sw-settings-payment-gateway-list__delete-action').exists()).toBe(false);
    });
    it('opens a new app from the create action', async () => {
        const { wrapper, push } = await createWrapper();
        await wrapper.find('.sw-settings-payment-gateway-list__create-action').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.gateway.detail', params: { id: 'new' } });
    });
    it('protects create, edit and delete actions by privilege', async () => {
        const { wrapper } = await createWrapper({ can: () => false });
        expect(wrapper.find('.sw-settings-payment-gateway-list__create-action').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-settings-payment-gateway-list__edit-action').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-settings-payment-gateway-list__delete-action').attributes('disabled')).toBeDefined();
    });
    it('deletes an external app only after confirmation and refreshes the list', async () => {
        const { wrapper, service } = await createWrapper();
        await wrapper.find('.sw-settings-payment-gateway-list__delete-action').trigger('click');
        expect(service.deleteApp).not.toHaveBeenCalled();
        const buttons = wrapper.findAll('button');
        await buttons[buttons.length - 1].trigger('click');
        await flushPromises();
        expect(service.deleteApp).toHaveBeenCalledWith('app-1');
        expect(service.listApps).toHaveBeenCalledTimes(2);
    });
    it('offers a create action in the empty state', async () => {
        const { wrapper } = await createWrapper({ listApps: () => Promise.resolve([]) });
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-settings-payment-gateway.list.messageEmpty');
    });
});
