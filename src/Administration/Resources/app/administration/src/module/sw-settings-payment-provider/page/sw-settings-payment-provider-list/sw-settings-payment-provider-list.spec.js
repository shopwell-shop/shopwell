/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const registryProviders = [
    {
        technicalName: 'alipay',
        label: '支付宝',
        enabled: true,
        configured: true,
        credentialFields: [],
        settings: {},
        channels: [
            { channel: 'alipay_h5', label: '支付宝 手机网站支付', enabled: true },
            { channel: 'alipay_web', label: '支付宝 电脑网站支付', enabled: false },
        ],
    },
    {
        technicalName: 'stripe',
        label: 'Stripe',
        enabled: false,
        configured: false,
        credentialFields: [],
        settings: {},
        channels: [
            { channel: 'stripe_checkout', label: 'Stripe Checkout', enabled: false },
        ],
    },
];

async function createWrapper({ providers = registryProviders, saveFails = false } = {}) {
    const service = {
        listProviders: jest.fn(() => Promise.resolve(providers)),
        saveProvider: jest.fn(() => (saveFails ? Promise.reject(new Error('save failed')) : Promise.resolve())),
    };

    const push = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-payment-provider-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot></div>' },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-extension-component-section': true,
                'sw-internal-link': {
                    props: ['routerLink'],
                    template: '<a class="internal-link-stub"><slot></slot></a>',
                },
                'mt-card': { template: '<div><slot></slot><slot name="headerRight"></slot></div>' },
                'mt-empty-state': true,
                'mt-badge': true,
                'mt-switch': true,
            },
            provide: {
                paymentGatewayService: service,
                acl: { can: () => true },
            },
            mocks: {
                $router: { push },
                $te: () => false,
            },
        },
    });

    await flushPromises();

    return { wrapper, service, push };
}

describe('module/sw-settings-payment-provider/page/sw-settings-payment-provider-list', () => {
    beforeEach(() => {
        global.activeAclRoles = [
            'payment_provider_config.viewer',
            'payment_provider_config.editor',
            'payment_provider_config.deleter',
            'payment_channel_config.viewer',
            'payment_channel_config.editor',
            'payment_channel_config.deleter',
        ];
    });

    it('should load the providers from the server', async () => {
        const { wrapper, service } = await createWrapper();

        expect(service.listProviders).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.providers).toHaveLength(2);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should count the enabled channels of a provider', async () => {
        const { wrapper } = await createWrapper();

        const alipay = wrapper.vm.providers[0];

        expect(wrapper.vm.channelCount(alipay)).toBe(2);
        expect(wrapper.vm.enabledChannelCount(alipay)).toBe(1);
    });

    it('should save the switch of a provider immediately', async () => {
        const { wrapper, service } = await createWrapper();

        const alipay = wrapper.vm.providers[0];
        alipay.enabled = false;

        await wrapper.vm.onToggleProvider(alipay);

        expect(service.saveProvider).toHaveBeenCalledWith('alipay', { enabled: false });
        expect(wrapper.vm.createNotificationSuccess).toBeTruthy();
    });

    it('should revert the switch when saving fails', async () => {
        const { wrapper } = await createWrapper({ saveFails: true });

        const alipay = wrapper.vm.providers[0];
        alipay.enabled = false;

        await wrapper.vm.onToggleProvider(alipay);

        expect(alipay.enabled).toBe(true);
    });
});
