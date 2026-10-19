/**
 * @sw-package framework
 */
import { createWrapper } from './sw-settings-payment-gateway-detail.spec/create-wrapper';
import { existingApp, otherApp, platformApp } from './sw-settings-payment-gateway-detail.spec/fixtures';

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
        expect(wrapper.vm.app.technicalName).toBe('pos');
        expect(wrapper.vm.allowedIpsInput).toBe('127.0.0.1');

        expect(wrapper.vm.providers).toHaveLength(2);
        expect(wrapper.vm.providers[0]).toEqual(
            expect.objectContaining({ technicalName: 'alipay', enabled: true, sourceAppId: '' }),
        );
        expect(wrapper.vm.providers[1]).toEqual(
            expect.objectContaining({ technicalName: 'stripe', enabled: false, sourceAppId: '' }),
        );

        // the account source is an app, the field the page used to offer does not exist anymore
        expect(wrapper.vm.providers[0]).not.toHaveProperty('mode');
    });

    it('should keep the account source of an authorization that inherits from another app', async () => {
        const inheriting = {
            ...existingApp,
            providers: [
                {
                    providerTechnicalName: 'alipay',
                    enabled: true,
                    sourceAppId: 'app-2',
                    sourceAppName: 'POS 二号',
                    hasCredentials: false,
                    settings: {},
                },
            ],
        };

        const { wrapper } = await createWrapper({ app: inheriting });

        expect(wrapper.vm.providers[0].sourceAppId).toBe('app-2');
        expect(wrapper.vm.isAccountInherited(wrapper.vm.providers[0])).toBe(true);
        expect(wrapper.vm.sourceAppName(wrapper.vm.providers[0])).toBe('POS 二号');
        expect(wrapper.vm.sourceAppMissesInterface(wrapper.vm.providers[0])).toBe(false);
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
            type: 'external',
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

    it('should offer the own account and every other app as the account source, the platform tenant first', async () => {
        const { wrapper } = await createWrapper();

        const options = wrapper.vm.accountSourceOptions();

        expect(options).toEqual([
            { value: '', label: 'sw-settings-payment-gateway.detail.accountSourceOwn' },
            { value: 'app-platform', label: 'Platform Payment App' },
            { value: 'app-2', label: 'POS 二号' },
        ]);

        // the app of the page is never an account source of its own - that would be a circle
        expect(options.map((option) => option.value)).not.toContain('app-1');
    });

    it('should show the credentials of the app itself and hide them behind a note when it inherits', async () => {
        const { wrapper } = await createWrapper();

        // both interfaces collect on an account of their own, so both render their credential fields
        expect(wrapper.findAll('.sw-payment-config-field-stub')).toHaveLength(2);

        wrapper.vm.providers[0].sourceAppId = 'app-2';

        await wrapper.vm.$nextTick();

        // the switch is immediate, only the interface that inherits lost its fields
        expect(wrapper.findAll('.sw-payment-config-field-stub')).toHaveLength(1);
        expect(wrapper.vm.inheritanceText(wrapper.vm.providers[0])).toContain('POS 二号');
        expect(wrapper.vm.inheritanceText(wrapper.vm.providers[0])).toContain('支付宝');

        // the parameters of a channel belong to the account that is charged, so they are not editable
        expect(wrapper.vm.channelParamsInheritedText(wrapper.vm.providers[0])).toContain('POS 二号');
    });

    it('should warn when the app it inherits from is not authorized for the interface', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.apps = [
            platformApp,
            existingApp,
            {
                ...otherApp,
                providers: [
                    {
                        providerTechnicalName: 'alipay',
                        enabled: false,
                        sourceAppId: null,
                        sourceAppName: '',
                        hasCredentials: false,
                        settings: {},
                    },
                ],
            },
        ];

        wrapper.vm.providers[0].sourceAppId = 'app-2';

        expect(wrapper.vm.sourceAppMissesInterface(wrapper.vm.providers[0])).toBe(true);
        expect(wrapper.vm.sourceAppWarning(wrapper.vm.providers[0])).toContain('POS 二号');
    });

    it('should send the interfaces and the channels of the app', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.app.rateLimit = 120;
        wrapper.vm.allowedIpsInput = '127.0.0.1\n10.0.0.1\n\n';
        wrapper.vm.appSecretInput = 'the-new-secret';

        const stripe = wrapper.vm.providers[1];
        stripe.enabled = true;
        stripe.credentialInput.secret_key = 'sk_test';
        stripe.channels[0].enabled = true;

        await wrapper.vm.onSave();

        expect(service.saveApp).toHaveBeenCalledWith({
            id: 'app-1',
            technicalName: 'pos',
            name: 'POS 收银',
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
                    sourceAppId: null,
                    credentials: { app_id: '' },
                    settings: {},
                },
                {
                    providerTechnicalName: 'stripe',
                    enabled: true,
                    sourceAppId: null,
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

    it('should send the account source and keep no credentials of its own when it inherits', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.providers[0].sourceAppId = 'app-2';
        wrapper.vm.providers[0].credentialInput.app_id = 'typed-but-not-sent';

        await wrapper.vm.onSave();

        const payload = service.saveApp.mock.calls[0][0];

        expect(payload.providers[0]).toEqual({
            providerTechnicalName: 'alipay',
            enabled: true,
            sourceAppId: 'app-2',
            credentials: {},
            settings: {},
        });
    });

    it('should rebuild the interfaces from the declaration after a save', async () => {
        const { wrapper, service } = await createWrapper();

        await wrapper.vm.onSave();

        expect(service.saveApp).toHaveBeenCalled();
        // a state of the page is not a declaration: the channels keep their technical name
        expect(wrapper.vm.providers[0].channels[0].technicalName).toBe('alipay_h5');
        expect(wrapper.vm.providers[1].channels[0].technicalName).toBe('stripe_checkout');
    });

    it('should render the tenant of the platform read only', async () => {
        const { wrapper } = await createWrapper({ appId: 'app-platform', app: platformApp });

        expect(wrapper.vm.isPlatformTenant).toBe(true);
        expect(wrapper.vm.isReadOnly).toBe(true);
    });

    it('should render the page read only without the editor role', async () => {
        global.activeAclRoles = ['payment_app.viewer'];

        const { wrapper } = await createWrapper();

        expect(wrapper.vm.isPlatformTenant).toBe(false);
        expect(wrapper.vm.isReadOnly).toBe(true);
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
