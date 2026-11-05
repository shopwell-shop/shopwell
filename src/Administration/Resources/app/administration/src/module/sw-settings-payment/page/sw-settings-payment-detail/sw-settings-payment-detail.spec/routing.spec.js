/**
 * @sw-package checkout
 */
import { cleanUpWrappers, createWrapper, createGatewayService, PLATFORM_APP, SECOND_APP } from './fixtures';

describe('payment method routing', () => {
    afterEach(cleanUpWrappers);

    it('should ask for a saved payment method before the collection channel is configured', async () => {
        const gatewayService = createGatewayService([PLATFORM_APP]);

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
        const gatewayService = createGatewayService([]);

        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-empty').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-empty a').attributes('href')).toBeDefined();
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-save-action').exists()).toBe(false);
    });

    it('should tell that the account offers no channel the platform has enabled', async () => {
        const appWithoutChannels = { ...PLATFORM_APP, methods: [] };
        const gatewayService = createGatewayService([appWithoutChannels]);

        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-no-channels').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-unbound').exists()).toBe(false);
    });

    it('should warn that an unbound payment method does not appear in the checkout', async () => {
        const wrapper = await createWrapper([], {}, createGatewayService([PLATFORM_APP]));

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-unbound').exists()).toBe(true);
        expect(wrapper.get('.sw-settings-payment-detail__gateway-channel-save-action').attributes('disabled')).toBeDefined();
    });

    /**
     * A shop with a single account has nothing to choose: the row states the fact instead of rendering
     * a select with exactly one option.
     */
    it('should show the only account as a fact instead of a select', async () => {
        const wrapper = await createWrapper([], {}, createGatewayService([PLATFORM_APP]));

        await flushPromises();

        const account = wrapper.find('.sw-settings-payment-detail__gateway-channel-account');

        expect(account.exists()).toBe(true);
        expect(account.text()).toContain('Shopfront');
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-app').exists()).toBe(false);
    });

    it('should offer the accounts as a choice when the shop collects on more than one', async () => {
        const wrapper = await createWrapper([], {}, createGatewayService([PLATFORM_APP, SECOND_APP]));

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-account').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-app').exists()).toBe(true);
        expect(wrapper.getComponent('.sw-settings-payment-detail__gateway-channel-app').props('options')).toEqual([
            { value: 'platform-app-id', label: 'Shopfront' },
            { value: 'second-app-id', label: 'Second app' },
        ]);
    });

    it('should prefill the account and the channel the payment method collects through', async () => {
        const gatewayService = createGatewayService([PLATFORM_APP], {
            binding: { paymentAppId: 'platform-app-id', methodTechnicalName: 'alipay', routingPolicy: 'priority' },
            collectThroughGateway: true,
        });

        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-account').text()).toContain('Shopfront');
        expect(wrapper.getComponent('.sw-settings-payment-detail__gateway-channel-channel').props('modelValue')).toBe(
            'alipay',
        );
        // only the channels the account offers and the platform has enabled are offered
        expect(
            wrapper
                .getComponent('.sw-settings-payment-detail__gateway-channel-channel')
                .props('options')
                .map((option) => option.value),
        ).toEqual(['alipay']);
        // the stored binding is not a change
        expect(wrapper.get('.sw-settings-payment-detail__gateway-channel-save-action').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-bound').exists()).toBe(true);
    });

    it('should offer the channels of the account the operator picked', async () => {
        const wrapper = await createWrapper([], {}, createGatewayService([PLATFORM_APP, SECOND_APP]));

        await flushPromises();

        expect(
            wrapper
                .getComponent('.sw-settings-payment-detail__gateway-channel-channel')
                .props('options')
                .map((option) => option.value),
        ).toEqual(['alipay']);

        await wrapper.getComponent('.sw-settings-payment-detail__gateway-channel-app').setValue('second-app-id');
        await flushPromises();

        // the second account collects the card channel as well, so it is offered next to the h5 one
        expect(
            wrapper
                .getComponent('.sw-settings-payment-detail__gateway-channel-channel')
                .props('options')
                .map((option) => option.value),
        ).toEqual(['alipay', 'card']);
    });

    /**
     * A channel that is opened by an sdk belongs to an app that renders the payment itself. The
     * storefront can never hand a payer over to it, so it is not offered at all - binding one would
     * produce a method that fails after the order was placed.
     */
    it('reads method choices from binding metadata without requesting merchant configuration', async () => {
        const gatewayService = createGatewayService([PLATFORM_APP]);
        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();
        expect(gatewayService.listProviderDefinitions).not.toHaveBeenCalled();
        expect(gatewayService.listApps).not.toHaveBeenCalled();

        const values = wrapper
            .getComponent('.sw-settings-payment-detail__gateway-channel-channel')
            .props('options')
            .map((option) => option.value);

        expect(values).not.toContain('alipay_app');
        expect(values).toContain('alipay');
    });

    it('should disable a channel another payment method already collects through', async () => {
        const gatewayService = createGatewayService([PLATFORM_APP], {
            taken: [
                {
                    paymentAppId: 'platform-app-id',
                    paymentAppName: 'Shopfront',
                    methodTechnicalName: 'alipay',
                    paymentMethodId: 'other-id',
                    paymentMethodName: 'Other method',
                },
            ],
        });

        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();

        await flushPromises();

        const option = wrapper
            .getComponent('.sw-settings-payment-detail__gateway-channel-channel')
            .props('options')
            .find((item) => item.value === 'alipay');

        expect(option.disabled).toBe(true);
        // the option renders the "taken by" variant instead of the plain channel label
        expect(option.label).toBe('sw-settings-payment.detail.gatewayChannel.optionTakenBy');
    });

    it('should save the collection channel of an unbound payment method without confirmation', async () => {
        const gatewayService = createGatewayService([PLATFORM_APP]);

        const wrapper = await createWrapper(['payment.editor'], {}, gatewayService);

        await flushPromises();

        await wrapper.getComponent('.sw-settings-payment-detail__gateway-channel-channel').setValue('alipay');
        await flushPromises();

        expect(
            wrapper.get('.sw-settings-payment-detail__gateway-channel-save-action').attributes('disabled'),
        ).toBeUndefined();

        await wrapper.get('.sw-settings-payment-detail__gateway-channel-save-action').trigger('click');
        await flushPromises();

        // the first binding has no account to take the money away from, so it is not confirmed
        expect(wrapper.find('.sw-confirm-modal-stub').exists()).toBe(false);
        expect(gatewayService.savePaymentMethodChannel).toHaveBeenCalledWith(
            '12312',
            'alipay',
            'platform-app-id',
            'priority',
        );
        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-bound').exists()).toBe(true);
    });

    it('should confirm before the payment method is moved to another channel', async () => {
        const gatewayService = createGatewayService([PLATFORM_APP], {
            binding: { paymentAppId: 'platform-app-id', methodTechnicalName: 'alipay', routingPolicy: 'priority' },
            collectThroughGateway: true,
        });

        const wrapper = await createWrapper(['payment.editor'], {}, gatewayService);

        await flushPromises();

        await wrapper.getComponent('.sw-settings-payment-detail__gateway-routing-policy').setValue('merchant_priority');
        await wrapper.get('.sw-settings-payment-detail__gateway-channel-save-action').trigger('click');
        await flushPromises();

        // it is already bound, so the click only opens the confirmation
        expect(wrapper.find('.sw-confirm-modal-stub').exists()).toBe(true);
        expect(gatewayService.savePaymentMethodChannel).not.toHaveBeenCalled();

        await wrapper.get('.confirm-routing').trigger('click');
        await flushPromises();

        expect(gatewayService.savePaymentMethodChannel).toHaveBeenCalledWith(
            '12312',
            'alipay',
            'platform-app-id',
            'merchant_priority',
        );
    });

    it('should tell that saving makes the payment method collect through the channel', async () => {
        const gatewayService = createGatewayService([PLATFORM_APP], {
            binding: { paymentAppId: 'platform-app-id', methodTechnicalName: 'alipay', routingPolicy: 'priority' },
            collectThroughGateway: false,
        });

        const wrapper = await createWrapper([], {}, gatewayService);

        await flushPromises();

        expect(wrapper.find('.sw-settings-payment-detail__gateway-channel-collect-hint').exists()).toBe(true);
    });
});
