import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

/**
 * The service the card for the collection channel talks to. Every method is a jest mock, so a test can
 * say what the server answered and which call the page made. The store behind the mocks is written by
 * a save, so a reload after the save answers the state the save created.
 */
function createGatewayService(providers = [], apps = [], channel = {}) {
    const stored = {
        binding: null,
        paymentApp: apps[0] || null,
        collectThroughGateway: false,
        taken: [],
        ...channel,
    };

    return {
        listProviders: jest.fn(() => Promise.resolve(providers)),
        listApps: jest.fn(() => Promise.resolve(apps)),
        getPaymentMethodChannel: jest.fn(() => Promise.resolve(stored)),
        savePaymentMethodChannel: jest.fn((id, channelTechnicalName, paymentAppId = null) => {
            const appId = paymentAppId || (stored.paymentApp ? stored.paymentApp.id : null);

            stored.binding = { paymentAppId: appId, channelTechnicalName };
            stored.paymentApp = apps.find((app) => app.id === appId) || stored.paymentApp;
            stored.collectThroughGateway = true;

            return Promise.resolve(stored);
        }),
    };
}

async function createWrapper(privileges = [], paymentMethod = {}, gatewayService = createGatewayService()) {
    return mount(
        await wrapTestComponent('sw-settings-payment-detail', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                        params: {
                            id: '12312',
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            create: () => {
                                return {
                                    id: '1a2b3c',
                                    name: 'Test settings-payment',
                                    entity: 'settings-payment',
                                    pluginId: '12321-a',
                                    ...paymentMethod,
                                };
                            },
                            get: () =>
                                Promise.resolve({
                                    id: '1a2b3c',
                                    name: 'Test settings-payment',
                                    entity: 'settings-payment',
                                    pluginId: '12321-a',
                                    ...paymentMethod,
                                }),
                            search: () =>
                                Promise.resolve({
                                    first: () =>
                                        Promise.resolve({
                                            id: '1a2b3c',
                                            name: 'Test settings-payment',
                                            entity: 'settings-payment',
                                            pluginId: '12321-a',
                                            getEntityName: () => 'payment-method',
                                            ...paymentMethod,
                                        }),
                                }),
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                    feature: {
                        isActive: () => true,
                    },
                    paymentGatewayService: gatewayService,
                },
                stubs: {
                    'sw-page': {
                        template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>
                    `,
                    },
                    'sw-button-process': true,
                    'sw-language-switch': true,
                    'sw-card-view': true,
                    'sw-container': true,
                    'mt-switch': true,
                    'mt-number-field': true,
                    'mt-select': true,
                    'mt-banner': {
                        template: '<div class="mt-banner"><slot></slot></div>',
                    },
                    'sw-text-field': true,
                    'sw-language-info': true,
                    'sw-upload-listener': true,
                    'sw-media-upload-v2': true,
                    'sw-plugin-box': true,
                    'mt-textarea': true,
                    'sw-select-rule-create': true,
                    'sw-sidebar': true,
                    'sw-sidebar-media-item': true,
                    'sw-skeleton': true,
                    'sw-context-menu-item': true,
                    'sw-custom-field-set-renderer': true,
                    'sw-confirm-modal': {
                        template: '<div class="sw-confirm-modal-stub"></div>',
                    },
                    'router-link': {
                        template: '<a class="router-link-stub" href="#"><slot></slot></a>',
                    },
                },
            },
        },
    );
}

const PROVIDERS = [
    {
        technicalName: 'alipay',
        label: 'alipay.label',
        enabled: true,
        configured: true,
        channels: [
            { channel: 'alipay_h5', label: 'alipay.channel.h5', enabled: true, flow: 'redirect' },
            { channel: 'alipay_qr', label: 'alipay.channel.qr', enabled: true, flow: 'qrcode' },
            // an sdk channel is declared and may even be enabled, but it belongs to an app
            { channel: 'alipay_app', label: 'alipay.channel.app', enabled: true, flow: 'sdk' },
            { channel: 'alipay_jsapi', label: 'alipay.channel.jsapi', enabled: false, flow: 'redirect' },
        ],
    },
    {
        technicalName: 'stripe',
        label: 'stripe.label',
        enabled: false,
        configured: true,
        channels: [{ channel: 'stripe_card', label: 'stripe.channel.card', enabled: true }],
    },
];

/**
 * The tenant of the platform: the account the shop's own checkout collects on.
 */
const PLATFORM_APP = {
    id: 'platform-app-id',
    technicalName: 'platform',
    name: 'Shopfront',
    type: 'platform',
    active: true,
    channels: [
        { channelTechnicalName: 'alipay_h5', enabled: true },
        { channelTechnicalName: 'alipay_qr', enabled: true },
        { channelTechnicalName: 'alipay_app', enabled: true },
        { channelTechnicalName: 'alipay_jsapi', enabled: true },
    ],
};

/**
 * An app the merchant onboarded - it collects on its own merchant account.
 */
const SECOND_APP = {
    id: 'second-app-id',
    technicalName: 'second-app',
    name: 'Second app',
    type: 'external',
    active: true,
    channels: [
        { channelTechnicalName: 'alipay_h5', enabled: true },
        { channelTechnicalName: 'stripe_card', enabled: true },
    ],
};

describe('module/sw-settings-payment/page/sw-settings-payment-detail', () => {
    it('should be able to save the settings-payment', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const saveButton = wrapper.find('.sw-payment-detail__save-action');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should be able to edit payment fields', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const nameField = wrapper.find('.sw-settings-payment-detail__field-name');
        const positionField = wrapper.find('.sw-settings-payment-detail__field-position');
        const commentField = wrapper.find('.sw-settings-payment-detail__description');
        const uploadField = wrapper.find('.sw-settings-payment-detail__logo-image-upload');
        const description = wrapper.find('.sw-settings-payment-detail__description');
        const activeField = wrapper.find('.sw-settings-payment-detail__field-active');
        const afterOrderField = wrapper.find('.sw-settings-payment-detail__field-after-order');
        const ruleField = wrapper.find('.sw-settings-payment-detail__field-availability-rule');

        expect(nameField.attributes('disabled')).toBeUndefined();
        expect(positionField.attributes('disabled')).toBeUndefined();
        expect(commentField.attributes('disabled')).toBeUndefined();
        expect(uploadField.attributes('disabled')).toBeUndefined();
        expect(description.attributes('disabled')).toBeUndefined();
        expect(activeField.attributes('disabled')).toBeUndefined();
        expect(afterOrderField.attributes('disabled')).toBeUndefined();
        expect(ruleField.attributes('disabled')).toBeUndefined();
    });

    it('should only allow whole numbers for the position', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const positionField = wrapper.find('.sw-settings-payment-detail__field-position');

        expect(positionField.attributes('step')).toBe('1');
        expect(positionField.attributes('number-type')).toBe('int');
    });

    it('should add conditions association', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const criteria = wrapper.vm.ruleFilter;

        expect(criteria.associations[0].association).toBe('conditions');
    });

    it('should disabled technical name input pluginId', async () => {
        const wrapper = await createWrapper([], {
            pluginId: '001',
        });

        await flushPromises();

        const technicalInput = wrapper.findComponent('.sw-settings-payment-detail__field-technical-name');

        expect(technicalInput.props().disabled).toBe(true);
    });

    it('should disabled technical name input appId', async () => {
        const wrapper = await createWrapper([], {
            appPaymentMethod: {
                id: '001',
            },
        });

        await flushPromises();

        const technicalInput = wrapper.findComponent('.sw-settings-payment-detail__field-technical-name');

        expect(technicalInput.props().disabled).toBe(true);
    });

    it('should not disabled technical name input', async () => {
        const wrapper = await createWrapper([], {
            pluginId: undefined,
        });

        await flushPromises();

        const technicalInput = wrapper.find('.sw-settings-payment-detail__field-technical-name');

        expect(technicalInput.attributes('disabled')).toBeUndefined();
    });

    it('should ask for a saved payment method before the collection channel is configured', async () => {
        const gatewayService = createGatewayService(PROVIDERS, [PLATFORM_APP]);

        const wrapper = await createWrapper([], { _isNew: true }, gatewayService);

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-new').exists()).toBe(true);
        // there is no id the endpoints could answer for, so no request is made at all
        expect(gatewayService.getPaymentMethodChannel).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-channel').exists()).toBe(false);
    });

    /**
     * A payment is collected on the merchant account of an app, so without a single account there is
     * nothing to collect on - and the card sends the operator to where the accounts come from.
     */
    it('should point at the payment interfaces when no account can collect', async () => {
        const gatewayService = createGatewayService(PROVIDERS, []);

        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-empty').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-empty a').attributes('href')).toBeDefined();
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-save-action').exists()).toBe(false);
    });

    it('should tell that the account offers no channel the platform has enabled', async () => {
        const appWithoutChannels = { ...PLATFORM_APP, channels: [] };
        const gatewayService = createGatewayService(PROVIDERS, [appWithoutChannels]);

        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-no-channels').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-unbound').exists()).toBe(false);
    });

    it('should warn that an unbound payment method does not appear in the checkout', async () => {
        const wrapper = await createWrapper([], {}, createGatewayService(PROVIDERS, [PLATFORM_APP]));

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-unbound').exists()).toBe(true);
        expect(wrapper.vm.gatewaySaveDisabled).toBe(true);
    });

    /**
     * A shop with a single account has nothing to choose: the row states the fact instead of rendering
     * a select with exactly one option.
     */
    it('should show the only account as a fact instead of a select', async () => {
        const wrapper = await createWrapper([], {}, createGatewayService(PROVIDERS, [PLATFORM_APP]));

        await flushPromises();

        const account = wrapper.find('.sw-settings-payment-detail__gateway-channel-account');

        expect(account.exists()).toBe(true);
        expect(account.text()).toContain('Shopfront');
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-app').exists()).toBe(false);
    });

    it('should offer the accounts as a choice when the shop collects on more than one', async () => {
        const wrapper = await createWrapper([], {}, createGatewayService(PROVIDERS, [PLATFORM_APP, SECOND_APP]));

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-account').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-app').exists()).toBe(true);
        expect(wrapper.vm.gatewayAppOptions).toEqual([
            { value: 'platform-app-id', label: 'Shopfront' },
            { value: 'second-app-id', label: 'Second app' },
        ]);
    });

    it('should prefill the account and the channel the payment method collects through', async () => {
        const gatewayService = createGatewayService(PROVIDERS, [PLATFORM_APP], {
            binding: { paymentAppId: 'platform-app-id', channelTechnicalName: 'alipay_h5' },
            collectThroughGateway: true,
        });

        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();

        expect(wrapper.vm.gatewayApp).toBe('platform-app-id');
        expect(wrapper.vm.gatewayChannel).toBe('alipay_h5');
        // only the channels the account offers and the platform has enabled are offered
        expect(wrapper.vm.gatewayChannelOptions.map((option) => option.value)).toEqual(['alipay_h5', 'alipay_qr']);
        // the stored binding is not a change
        expect(wrapper.vm.gatewaySaveDisabled).toBe(true);
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-bound').exists()).toBe(true);
    });

    it('should offer the channels of the account the operator picked', async () => {
        const wrapper = await createWrapper([], {}, createGatewayService(PROVIDERS, [PLATFORM_APP, SECOND_APP]));

        await flushPromises();

        expect(wrapper.vm.gatewayChannelOptions.map((option) => option.value)).toEqual(['alipay_h5', 'alipay_qr']);

        wrapper.vm.gatewayApp = 'second-app-id';
        await flushPromises();

        // the second account collects the card channel as well, so it is offered next to the h5 one
        expect(wrapper.vm.gatewayChannelOptions.map((option) => option.value)).toEqual(['alipay_h5', 'stripe_card']);
    });

    /**
     * A channel that is opened by an sdk belongs to an app that renders the payment itself. The
     * storefront can never hand a payer over to it, so it is not offered at all - binding one would
     * produce a method that fails after the order was placed.
     */
    it('should not offer a channel that is opened by an sdk', async () => {
        const wrapper = await createWrapper([], {}, createGatewayService(PROVIDERS, [PLATFORM_APP]));

        await flushPromises();

        wrapper.vm.gatewayApp = 'platform-app-id';
        await flushPromises();

        const values = wrapper.vm.gatewayChannelOptions.map((option) => option.value);

        expect(values).not.toContain('alipay_app');
        expect(values).toContain('alipay_h5');
    });

    it('should disable a channel another payment method already collects through', async () => {
        const gatewayService = createGatewayService(PROVIDERS, [PLATFORM_APP], {
            taken: [
                {
                    paymentAppId: 'platform-app-id',
                    paymentAppName: 'Shopfront',
                    channelTechnicalName: 'alipay_h5',
                    paymentMethodId: 'other-id',
                    paymentMethodName: 'Other method',
                },
            ],
        });

        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();

        wrapper.vm.gatewayApp = 'platform-app-id';
        await flushPromises();

        const option = wrapper.vm.gatewayChannelOptions.find((item) => item.value === 'alipay_h5');

        expect(option.disabled).toBe(true);
        // the option renders the "taken by" variant instead of the plain channel label
        expect(option.label).toBe('sw-settings-payment.detail.gatewayChannel.optionTakenBy');
    });

    it('should save the collection channel of an unbound payment method without confirmation', async () => {
        const gatewayService = createGatewayService(PROVIDERS, [PLATFORM_APP]);

        const wrapper = await createWrapper(['payment.editor'], {}, gatewayService);

        await flushPromises();

        wrapper.vm.gatewayChannel = 'alipay_h5';
        await flushPromises();

        expect(wrapper.vm.gatewaySaveDisabled).toBe(false);

        await wrapper.vm.onSaveGatewayChannel();
        await flushPromises();

        // the first binding has no account to take the money away from, so it is not confirmed
        expect(wrapper.find('.sw-confirm-modal-stub').exists()).toBe(false);
        expect(gatewayService.savePaymentMethodChannel).toHaveBeenCalledWith('12312', 'alipay_h5', 'platform-app-id');
        expect(wrapper.vm.gatewayBinding).toEqual({
            paymentAppId: 'platform-app-id',
            channelTechnicalName: 'alipay_h5',
        });
    });

    it('should confirm before the payment method is moved to another channel', async () => {
        const gatewayService = createGatewayService(PROVIDERS, [PLATFORM_APP], {
            binding: { paymentAppId: 'platform-app-id', channelTechnicalName: 'alipay_h5' },
            collectThroughGateway: true,
        });

        const wrapper = await createWrapper(['payment.editor'], {}, gatewayService);

        await flushPromises();

        await wrapper.vm.onSaveGatewayChannel();
        await flushPromises();

        // it is already bound, so the click only opens the confirmation
        expect(wrapper.vm.showGatewayRebindModal).toBe(true);
        expect(gatewayService.savePaymentMethodChannel).not.toHaveBeenCalled();

        await wrapper.vm.saveGatewayChannel();
        await flushPromises();

        expect(gatewayService.savePaymentMethodChannel).toHaveBeenCalledWith('12312', 'alipay_h5', 'platform-app-id');
    });

    it('should tell that saving makes the payment method collect through the channel', async () => {
        const gatewayService = createGatewayService(PROVIDERS, [PLATFORM_APP], {
            binding: { paymentAppId: 'platform-app-id', channelTechnicalName: 'alipay_h5' },
            collectThroughGateway: false,
        });

        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-collect-hint').exists()).toBe(true);
    });
});
