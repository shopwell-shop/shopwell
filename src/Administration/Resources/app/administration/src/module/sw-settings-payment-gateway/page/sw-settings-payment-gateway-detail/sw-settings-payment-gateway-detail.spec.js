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

        const alipayRow = () => wrapper.findAll('.sw-settings-payment-gateway-detail__account').at(0);

        // the interface collects on an account of its own, so it renders the fields of its credentials
        expect(alipayRow().findAll('.sw-payment-config-field-stub')).toHaveLength(1);

        wrapper.vm.providers[0].sourceAppId = 'app-2';

        await wrapper.vm.$nextTick();

        // the switch is immediate, and the interface that inherits loses its fields
        expect(alipayRow().findAll('.sw-payment-config-field-stub')).toHaveLength(0);
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
                    // the values the api handed over are part of the form and go back as they are
                    credentials: { app_id: '2021000000000000' },
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
                    params: { sign_type: 'RSA2' },
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

    it('should start the form with the credentials and the parameters the api hands over', async () => {
        const { wrapper } = await createWrapper();

        // the merchant recognises what is configured instead of retyping a key to find out
        expect(wrapper.vm.providers[0].credentialInput.app_id).toBe('2021000000000000');
        expect(wrapper.vm.providers[0].channels[0].paramInput.sign_type).toBe('RSA2');

        // an interface without a stored value still starts empty
        expect(wrapper.vm.providers[1].credentialInput.secret_key).toBe('');
    });

    it('should keep the platform app editable apart from its identifier and the delete action', async () => {
        const { wrapper } = await createWrapper({ appId: 'app-platform', app: platformApp });

        expect(wrapper.vm.isPlatformTenant).toBe(true);
        // the platform app is edited on this page like every other one - only the permission decides
        expect(wrapper.vm.isReadOnly).toBe(false);

        // saving is offered, deleting is offered but refused: the platform collects through this app
        // and the installation has no second one
        const save = wrapper.find('.sw-settings-payment-gateway-detail__save-action');
        const del = wrapper.find('.sw-settings-payment-gateway-detail__delete-action');
        expect(save.exists()).toBe(true);
        expect(save.attributes()).not.toHaveProperty('disabled');
        expect(del.exists()).toBe(true);
        expect(del.attributes()).toHaveProperty('disabled');

        // the notice explains what is special instead of declaring the page read only
        const notice = wrapper.find('.sw-settings-payment-gateway-detail__platform-notice');
        expect(notice.exists()).toBe(true);
        expect(notice.text()).toBe('sw-settings-payment-gateway.detail.platformAccountNotice');
        expect(wrapper.vm.providers).toHaveLength(2);

        // the technical name is the identifier the installation gave this row - it is not shown, while
        // the name of the app is editable because the platform collects under the name of this shop
        expect(wrapper.find('[name="sw-field--payment-app-technical-name"]').exists()).toBe(false);
        expect(wrapper.find('[name="sw-field--payment-app-name"]').exists()).toBe(true);
        expect(wrapper.find('[name="sw-field--payment-app-name"]').attributes('disabled')).toBeUndefined();

        // the merchant account of the platform is maintained on the page of the interface: the values
        // are shown here and disabled, and the hint links to the page that owns them
        const accountRow = wrapper.findAll('.sw-settings-payment-gateway-detail__account').at(0);
        const fields = accountRow.findAll('.sw-payment-config-field-stub');
        expect(fields.length).toBeGreaterThan(0);
        fields.forEach((field) => expect(field.attributes('disabled')).toBe('disabled'));

        const hint = accountRow.find('.sw-settings-payment-gateway-detail__platform-credentials-hint');
        expect(hint.exists()).toBe(true);
        expect(hint.text()).toContain('sw-settings-payment-gateway.detail.platformCredentialHint');

        // the parameters of a channel are stored on the platform level as well: a write into this row
        // would never be read by the resolver, so the inputs only display the values
        const channelRow = wrapper.findAll('.sw-settings-payment-gateway-detail__channel').at(0);
        const channelParams = channelRow.findAll('.sw-settings-payment-gateway-detail__channel-params')
            .at(0)
            .findAll('.sw-payment-config-field-stub');
        expect(channelParams.length).toBeGreaterThan(0);
        channelParams.forEach((field) => expect(field.attributes('disabled')).toBe('disabled'));

        // and the same hint sits above them, so the merchant knows where to change them
        const channelHint = channelRow.find('.sw-settings-payment-gateway-detail__platform-credentials-hint');
        expect(channelHint.exists()).toBe(true);
        expect(channelHint.text()).toContain('sw-settings-payment-gateway.detail.platformCredentialHint');
    });

    it('should keep the save and the delete action for an app of a merchant', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.isPlatformTenant).toBe(false);
        expect(wrapper.find('.sw-settings-payment-gateway-detail__save-action').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-payment-gateway-detail__delete-action').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-payment-gateway-detail__platform-notice').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-payment-gateway-detail__platform-credentials-hint').exists()).toBe(false);
        expect(wrapper.find('[name="sw-field--payment-app-technical-name"]').exists()).toBe(true);

        // and the credentials of an interface the merchant is authorized for are the ones it fills in
        const fields = wrapper.findAll('.sw-settings-payment-gateway-detail__account').at(0)
            .findAll('.sw-payment-config-field-stub');
        expect(fields.length).toBeGreaterThan(0);
        expect(fields.at(0).text()).toBe('app_id');
        expect(fields.at(0).attributes('disabled')).toBeUndefined();

        // the interface the app is not authorized for keeps its fields disabled - enabling the
        // interface is the decision that turns them on, not typing into them
        const unauthorized = wrapper.findAll('.sw-settings-payment-gateway-detail__account').at(1)
            .findAll('.sw-payment-config-field-stub');
        expect(unauthorized.at(0).text()).toBe('secret_key');
        expect(unauthorized.at(0).attributes('disabled')).toBe('disabled');
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
