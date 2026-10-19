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
        ],
    },
    {
        technicalName: 'stripe',
        label: 'Stripe',
        enabled: false,
        configured: false,
        credentialFields: [
            {
                name: 'secret_key',
                label: 'Secret Key',
                type: 'password',
                required: true,
                secret: true,
                options: {},
                default: null,
                value: null,
                configured: false,
            },
        ],
        settings: {},
        channels: [
            {
                channel: 'stripe_checkout',
                label: 'Stripe Checkout',
                flow: 'redirect',
                currencies: [],
                devices: [],
                runtimeParams: {},
                paramFields: [],
                enabled: false,
                configured: false,
                settings: {},
            },
        ],
    },
];

const existingApp = {
    id: 'app-1',
    technicalName: 'alipay_sandbox_demo',
    name: '支付宝沙箱联调应用',
    active: true,
    notifyUrl: null,
    returnUrl: null,
    allowedIps: ['127.0.0.1'],
    rateLimit: 60,
    hasSecret: true,
    providers: [
        {
            providerTechnicalName: 'alipay',
            enabled: true,
            mode: 'platform',
            hasCredentials: false,
            settings: {},
        },
    ],
    channels: [
        {
            channelTechnicalName: 'alipay_h5',
            enabled: true,
            hasParams: false,
            settings: {},
        },
    ],
};

async function createWrapper({ appId = 'app-1', app = existingApp, savedApp = null, appSecret = null } = {}) {
    const service = {
        listProviders: jest.fn(() => Promise.resolve(registryProviders)),
        listApps: jest.fn(() => Promise.resolve(app === null ? [] : [app])),
        saveApp: jest.fn(() =>
            Promise.resolve({
                app: savedApp ?? app,
                ...(appSecret === null ? {} : { appSecret }),
            }),
        ),
        deleteApp: jest.fn(() => Promise.resolve()),
    };

    const push = jest.fn();
    const replace = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-payment-gateway-detail', { sync: true }), {
        props: { appId },
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
                'sw-payment-config-field': {
                    props: ['field', 'modelValue'],
                    template: '<div class="sw-payment-config-field-stub">{{ field.name }}</div>',
                },
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-card': { template: '<div><slot></slot><slot name="headerRight"></slot></div>' },
                'mt-button': true,
                'mt-badge': true,
                'mt-icon': true,
                'mt-switch': true,
                'mt-select': true,
                'mt-text-field': true,
                'mt-password-field': true,
                'mt-textarea': true,
                'mt-url-field': true,
                'mt-number-field': true,
                'mt-banner': true,
                'mt-modal-root': { template: '<div><slot></slot></div>' },
                'mt-modal': { template: '<div><slot></slot><slot name="footer"></slot></div>' },
                'mt-modal-close': true,
                'mt-modal-action': true,
            },
            provide: {
                paymentGatewayService: service,
            },
            mocks: {
                $router: { push, replace },
                $te: () => false,
            },
        },
    });

    await flushPromises();

    return { wrapper, service, push, replace };
}

describe('module/sw-settings-payment-gateway/page/sw-settings-payment-gateway-detail', () => {
    beforeEach(() => {
        global.activeAclRoles = [
            'payment_app.viewer',
            'payment_app.creator',
            'payment_app.editor',
            'payment_app.deleter',
        ];
    });

    it('should merge the interfaces of the server with the ones of the app', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.isNew).toBe(false);
        expect(wrapper.vm.app.technicalName).toBe('alipay_sandbox_demo');
        expect(wrapper.vm.allowedIpsInput).toBe('127.0.0.1');

        expect(wrapper.vm.providers).toHaveLength(2);
        expect(wrapper.vm.providers[0]).toEqual(
            expect.objectContaining({ technicalName: 'alipay', enabled: true, mode: 'platform' }),
        );
        expect(wrapper.vm.providers[1]).toEqual(
            expect.objectContaining({ technicalName: 'stripe', enabled: false, mode: 'platform' }),
        );
    });

    it('should nest the channels under their interface and merge the assignment of the app', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.providers[0].channels[0]).toEqual(
            expect.objectContaining({ technicalName: 'alipay_h5', enabled: true }),
        );
        expect(wrapper.vm.providers[1].channels[0]).toEqual(
            expect.objectContaining({ technicalName: 'stripe_checkout', enabled: false }),
        );
    });

    it('should start with an empty app when a new one is created', async () => {
        const { wrapper, service } = await createWrapper({ appId: 'new', app: null });

        expect(wrapper.vm.isNew).toBe(true);
        expect(wrapper.vm.app).toEqual({
            id: null,
            technicalName: '',
            name: '',
            active: false,
            notifyUrl: null,
            returnUrl: null,
            allowedIps: [],
            rateLimit: null,
            hasSecret: false,
            providers: [],
            channels: [],
        });

        // a new app has no authorization yet, but the interfaces it could use are already offered
        expect(wrapper.vm.providers).toHaveLength(2);
        expect(service.listApps).toHaveBeenCalled();
    });

    it('should warn when the app cannot be found', async () => {
        const { wrapper, push } = await createWrapper({ appId: 'missing', app: null });
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError');

        // the app is loaded once on creation, the second call is the one that cannot find it
        await wrapper.vm.createdComponent();

        expect(notification).toHaveBeenCalled();
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.gateway.list' });
    });

    it('should send the interfaces and the channels of the app', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.app.rateLimit = 120;
        wrapper.vm.allowedIpsInput = '127.0.0.1\n10.0.0.1\n\n';
        wrapper.vm.appSecretInput = 'the-new-secret';

        const stripe = wrapper.vm.providers[1];
        stripe.enabled = true;
        stripe.mode = 'own';
        stripe.credentialInput.secret_key = 'sk_test';
        stripe.channels[0].enabled = true;

        await wrapper.vm.onSave();

        expect(service.saveApp).toHaveBeenCalledWith({
            id: 'app-1',
            technicalName: 'alipay_sandbox_demo',
            name: '支付宝沙箱联调应用',
            active: true,
            notifyUrl: null,
            returnUrl: null,
            allowedIps: ['127.0.0.1', '10.0.0.1'],
            rateLimit: 120,
            appSecret: 'the-new-secret',
            providers: [
                {
                    providerTechnicalName: 'alipay',
                    enabled: true,
                    mode: 'platform',
                    credentials: { app_id: '' },
                    settings: {},
                },
                {
                    providerTechnicalName: 'stripe',
                    enabled: true,
                    mode: 'own',
                    credentials: { secret_key: 'sk_test' },
                    settings: {},
                },
            ],
            channels: [
                {
                    channelTechnicalName: 'alipay_h5',
                    enabled: true,
                    params: {},
                    settings: {},
                },
                {
                    channelTechnicalName: 'stripe_checkout',
                    enabled: true,
                    params: {},
                    settings: {},
                },
            ],
        });
    });

    it('should hand out a generated secret once and clear the input', async () => {
        const { wrapper } = await createWrapper({ appSecret: 'generated-secret' });

        await wrapper.vm.onSave();

        expect(wrapper.vm.generatedSecret).toBe('generated-secret');
        expect(wrapper.vm.isSecretModalOpen).toBe(true);
        expect(wrapper.vm.appSecretInput).toBe('');

        wrapper.vm.onCloseSecretModal();
        expect(wrapper.vm.generatedSecret).toBe('');
        expect(wrapper.vm.isSecretModalOpen).toBe(false);
    });

    it('should offer a random secret that the app can be created with', async () => {
        const { wrapper } = await createWrapper({ appId: 'new', app: null });

        wrapper.vm.onGenerateSecret();

        expect(wrapper.vm.appSecretInput).toMatch(/^[0-9a-f]{64}$/);
    });

    it('should delete the app and leave the page', async () => {
        const { wrapper, service, push } = await createWrapper();

        wrapper.vm.onOpenDeleteModal();
        expect(wrapper.vm.isDeleteModalOpen).toBe(true);

        await wrapper.vm.onConfirmDelete();

        expect(service.deleteApp).toHaveBeenCalledWith('app-1');
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.gateway.list' });
    });
});
