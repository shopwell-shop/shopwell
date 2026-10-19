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
        credentialFields: [
            {
                name: 'app_id',
                label: '支付宝应用 APPID',
                type: 'text',
                required: true,
                secret: false,
                options: {},
                default: null,
                value: '2021000000000000',
                configured: true,
            },
            {
                name: 'private_key',
                label: '应用私钥',
                type: 'textarea',
                required: true,
                secret: true,
                options: {},
                default: null,
                value: null,
                configured: true,
            },
            {
                name: 'sign_type',
                label: '签名算法',
                type: 'select',
                required: false,
                secret: false,
                options: { RSA2: 'RSA2' },
                default: 'RSA2',
                value: null,
                configured: false,
            },
        ],
        settings: {},
        channels: [
            {
                channel: 'alipay_h5',
                label: '支付宝 手机网站支付',
                flow: 'redirect',
                currencies: ['CNY'],
                devices: ['wap'],
                runtimeParams: {},
                paramFields: [],
                enabled: true,
                configured: true,
                settings: {},
            },
            {
                channel: 'alipay_mini',
                label: '支付宝 小程序支付',
                flow: 'sdk',
                currencies: ['CNY'],
                devices: ['mini'],
                runtimeParams: { buyer_id: true },
                paramFields: [
                    {
                        name: 'app_id',
                        label: '小程序 APPID',
                        type: 'text',
                        required: true,
                        secret: false,
                        options: {},
                        default: null,
                        value: 'wx-mini-1',
                        configured: true,
                    },
                ],
                enabled: false,
                configured: true,
                settings: {},
            },
        ],
    },
];

async function createWrapper({ providers = registryProviders, providerId = 'alipay', saveFails = false } = {}) {
    const service = {
        listProviders: jest.fn(() => Promise.resolve(providers)),
        saveProvider: jest.fn(() => (saveFails ? Promise.reject(new Error('save failed')) : Promise.resolve())),
        saveProviderChannel: jest.fn(() => Promise.resolve()),
        resetProvider: jest.fn(() => Promise.resolve()),
        resetProviderChannel: jest.fn(() => Promise.resolve()),
    };

    const push = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-payment-provider-detail', { sync: true }), {
        props: { providerId },
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-extension-component-section': true,
                'sw-payment-config-field': {
                    props: ['field', 'modelValue'],
                    template: '<div class="sw-payment-config-field-stub">{{ field.name }}</div>',
                },
                'mt-card': { template: '<div><slot></slot><slot name="headerRight"></slot></div>' },
                'mt-button': true,
                'mt-badge': true,
                'mt-icon': true,
                'mt-switch': true,
                'mt-banner': true,
                'mt-modal-root': { template: '<div><slot></slot></div>' },
                'mt-modal': { template: '<div><slot></slot><slot name="footer"></slot></div>' },
                'mt-modal-close': true,
                'mt-modal-action': true,
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

describe('module/sw-settings-payment-provider/page/sw-settings-payment-provider-detail', () => {
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

    it('should load the provider of the route and prefill the fields that are not secret', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.provider.technicalName).toBe('alipay');
        expect(wrapper.vm.provider.credentialInput.app_id).toBe('2021000000000000');
        // a secret is never echoed back, the input starts empty
        expect(wrapper.vm.provider.credentialInput.private_key).toBe('');
    });

    it('should go back to the list when the interface of the route is unknown', async () => {
        const { wrapper, push } = await createWrapper({ providerId: 'unknown' });

        expect(wrapper.vm.provider).toBeNull();
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.provider.index' });
    });

    it('should mark an enabled interface with missing required fields as invalid', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.provider.credentialInput.app_id = '';

        expect(wrapper.vm.isProviderInvalid()).toBe(true);
    });

    it('should count a configured secret as filled', async () => {
        const { wrapper } = await createWrapper();

        // the private key is configured and its input stays empty, that is not a missing field
        expect(wrapper.vm.isProviderInvalid()).toBe(false);
    });

    it('should refuse to save an enabled interface with missing fields', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.provider.credentialInput.app_id = '';

        await wrapper.vm.onSave();

        expect(service.saveProvider).not.toHaveBeenCalled();
    });

    it('should only save the interface when something changed', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.provider.credentialInput.app_id = '2021000000000001';

        await wrapper.vm.onSave();

        expect(service.saveProvider).toHaveBeenCalledTimes(1);
        expect(service.saveProvider).toHaveBeenCalledWith('alipay', {
            enabled: true,
            credentials: expect.objectContaining({ app_id: '2021000000000001' }),
            settings: {},
        });
        expect(service.saveProviderChannel).not.toHaveBeenCalled();
    });

    it('should only save the channel that changed', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.provider.channels[1].paramInput.app_id = 'wx-mini-2';

        await wrapper.vm.onSave();

        expect(service.saveProvider).not.toHaveBeenCalled();
        expect(service.saveProviderChannel).toHaveBeenCalledTimes(1);
        expect(service.saveProviderChannel).toHaveBeenCalledWith('alipay', 'alipay_mini', {
            enabled: false,
            params: expect.objectContaining({ app_id: 'wx-mini-2' }),
            settings: {},
        });
    });

    it('should reset the credentials of the interface', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.onOpenResetProvider();
        expect(wrapper.vm.resetTarget.type).toBe('provider');

        await wrapper.vm.onConfirmReset();

        expect(service.resetProvider).toHaveBeenCalledWith('alipay');
        expect(wrapper.vm.resetTarget).toBeNull();
    });

    it('should reset the parameters of a channel', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.onOpenResetChannel(wrapper.vm.provider.channels[1]);
        expect(wrapper.vm.resetTarget.type).toBe('channel');

        await wrapper.vm.onConfirmReset();

        expect(service.resetProviderChannel).toHaveBeenCalledWith('alipay', 'alipay_mini');
        expect(wrapper.vm.resetTarget).toBeNull();
    });
});
