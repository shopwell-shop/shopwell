import { expect, getCountryAddressData, getCountryCodeFromLocale, getLanguageCode, test } from '@fixtures/AcceptanceTest';

test(
    'Customers can use a non-shippable billing country with a shippable delivery address at checkout.',
    {
        tag: [
            '@Account',
            '@Address',
            '@Storefront',
        ],
    },
    async ({
        StorefrontAccountLogin,
        StorefrontProductDetail,
        StorefrontCheckoutRegister,
        StorefrontCheckoutConfirm,
        IdProvider,
        ShopCustomer,
        TestDataService,
        DefaultSalesChannel,
        AddProductToCart,
        Register,
    }) => {
        const nonShippableCountry = await TestDataService.createCountry({ shippingAvailable: false });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, nonShippableCountry.id);
        const shippableCountry = await TestDataService.getCountry(getCountryCodeFromLocale());
        const product = await TestDataService.createBasicProduct();
        const address = getCountryAddressData();
        const customer = {
            email: `${IdProvider.getIdPair().uuid}@test.com`,
            isGuest: true,
            name: 'Jeff Goldblum',
            ...address,
        };

        await test.step('Checkout allows the billing country but disables it for shipping', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product));
            await ShopCustomer.presses(StorefrontProductDetail.offCanvasCartGoToCheckoutButton);
            await StorefrontCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });

            const billingCountry = StorefrontAccountLogin.countryInput.locator(`option[value="${nonShippableCountry.id}"]`);
            const shippingCountry = StorefrontAccountLogin.shippingAddressCountryInput.locator(
                `option[value="${nonShippableCountry.id}"]`,
            );
            await ShopCustomer.expects(billingCountry).toBeEnabled();
            await StorefrontAccountLogin.countryInput.selectOption(nonShippableCountry.id);
            await StorefrontAccountLogin.differentShippingAddressCheckbox.check();
            await ShopCustomer.expects(shippingCountry).toBeDisabled();
            await ShopCustomer.expects(
                StorefrontAccountLogin.shippingAddressCountryInput.locator(`option[value="${shippableCountry.id}"]`),
            ).toBeEnabled();
        });

        await test.step('Checkout stores the non-shippable billing address and the valid shipping address', async () => {
            await ShopCustomer.fillsIn(StorefrontAccountLogin.shippingAddressNameInput, customer.name);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.shippingAddressStreetAddressInput, address.street);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.shippingAddressPostalCodeInput, address.postalCode);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.shippingAddressCityInput, address.city);
            await StorefrontAccountLogin.shippingAddressCountryInput.selectOption(shippableCountry.id);

            const country = await StorefrontAccountLogin.countryInput
                .locator(`option[value="${nonShippableCountry.id}"]`)
                .textContent();
            await ShopCustomer.attemptsTo(Register({ ...customer, country: country.replace(/\s+/g, ' ').trim() }));
            await ShopCustomer.expects(StorefrontCheckoutConfirm.headline).toBeVisible();

            const registeredCustomer = await TestDataService.getCustomerByEmail(customer.email);
            const billingAddress = await TestDataService.getCustomerAddress(registeredCustomer.defaultBillingAddressId);
            const shippingAddress = await TestDataService.getCustomerAddress(registeredCustomer.defaultShippingAddressId);
            expect(billingAddress.countryId).toBe(nonShippableCountry.id);
            expect(shippingAddress.countryId).toBe(shippableCountry.id);
        });
    },
);

test(
    'Customers can set a non-shippable address as billing but cannot set it as shipping after registration.',
    {
        tag: [
            '@Account',
            '@Address',
            '@Storefront',
        ],
    },
    async ({
        IdProvider,
        ShopCustomer,
        TestDataService,
        DefaultSalesChannel,
        StorefrontAccountLogin,
        AddNewAddress,
        StorefrontAccount,
        StorefrontAccountAddresses,
        Register,
    }) => {
        const nonShippableCountry = await TestDataService.createCountry({ shippingAvailable: false });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, nonShippableCountry.id);
        const countryDefaults = getCountryAddressData();
        const address = {
            name: 'Jeff Goldblum',
            company: 'shopwell',
            department: 'Operations',
            street: countryDefaults.street,
            zipcode: countryDefaults.postalCode,
            city: countryDefaults.city,
            country: countryDefaults.country,
        };
        const nonShippableAddress = { ...address, name: 'Non-shippable Billing Address', country: nonShippableCountry.name };
        const customer = { email: `${IdProvider.getIdPair().uuid}@test.com`, password: 'shopwell' };

        await test.step('Customer registers and adds a valid default shipping address', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await ShopCustomer.attemptsTo(Register(customer));
            await ShopCustomer.expects(StorefrontAccount.headline).toBeVisible();
            const registeredCustomer = await TestDataService.getCustomerByEmail(customer.email);
            expect(registeredCustomer.defaultBillingAddressId).toBeNull();
            expect(registeredCustomer.defaultShippingAddressId).toBeNull();

            await ShopCustomer.goesTo(StorefrontAccountAddresses.url());
            await ShopCustomer.attemptsTo(AddNewAddress(address));
            const shippingAddress = await StorefrontAccountAddresses.getAvailableAddress({ name: address.name });
            await ShopCustomer.presses(shippingAddress.addressActions);
            await ShopCustomer.expects(shippingAddress.useAsDefaultShippingButton).toBeEnabled();
            await ShopCustomer.presses(shippingAddress.useAsDefaultShippingButton);
            await ShopCustomer.expects(
                await StorefrontAccountAddresses.getDefaultShippingAddress({ name: address.name }),
            ).toBeVisible();
        });

        await test.step('Customer saves the non-shippable address and can only use it for billing', async () => {
            await ShopCustomer.attemptsTo(AddNewAddress(nonShippableAddress));
            const savedAddress = await StorefrontAccountAddresses.getAvailableAddress({ name: nonShippableAddress.name });
            await ShopCustomer.expects(savedAddress.address).toContainText(nonShippableAddress.name);
            await ShopCustomer.expects(savedAddress.address).toContainText(nonShippableAddress.street);
            await ShopCustomer.expects(savedAddress.address).toContainText(nonShippableAddress.city);
            await ShopCustomer.expects(savedAddress.address).toContainText(nonShippableAddress.zipcode);
            await ShopCustomer.expects(savedAddress.address).toContainText(nonShippableAddress.country);
            await ShopCustomer.expects(savedAddress.address).toContainText(nonShippableAddress.company);
            await ShopCustomer.expects(savedAddress.address).toContainText(nonShippableAddress.department);
            await ShopCustomer.presses(savedAddress.addressActions);
            await ShopCustomer.expects(savedAddress.useAsDefaultShippingButton).toBeDisabled();
            await ShopCustomer.expects(savedAddress.useAsDefaultBillingButton).toBeEnabled();
            await ShopCustomer.presses(savedAddress.useAsDefaultBillingButton);

            await ShopCustomer.goesTo(StorefrontAccountAddresses.url());
            await ShopCustomer.expects(
                await StorefrontAccountAddresses.getDefaultBillingAddress({ name: nonShippableAddress.name }),
            ).toBeVisible();
            await ShopCustomer.expects(
                await StorefrontAccountAddresses.getDefaultShippingAddress({ name: address.name }),
            ).toBeVisible();
            const registeredCustomer = await TestDataService.getCustomerByEmail(customer.email);
            const billingAddress = await TestDataService.getCustomerAddress(registeredCustomer.defaultBillingAddressId);
            const shippingAddress = await TestDataService.getCustomerAddress(registeredCustomer.defaultShippingAddressId);
            expect(billingAddress.countryId).toBe(nonShippableCountry.id);
            expect(shippingAddress.countryId).toBe(DefaultSalesChannel.salesChannel.countryId);
        });
    },
);

test(
    'Customers with an existing non-shippable delivery address see the warning after logging in again.',
    {
        tag: [
            '@Account',
            '@Address',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, TestDataService, DefaultSalesChannel, StorefrontAccount, Login, Logout }) => {
        const nonShippableCountry = await TestDataService.createCountry({ shippingAvailable: false });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, nonShippableCountry.id);
        const address = getCountryAddressData();
        const customer = await TestDataService.createCustomer({
            defaultShippingAddress: {
                name: 'Jeff Goldblum',
                street: address.street,
                zipcode: address.postalCode,
                city: address.city,
                countryId: nonShippableCountry.id,
            },
        });
        const warningMessages = {
            'en-GB': 'We cannot deliver to the country that is stored in your delivery address.',
            'zh-CN': '您的收货地址所在国家暂不支持配送。',
        };
        const cannotDeliverToCountryAlert = StorefrontAccount.page.locator('.account-overview .alert-warning').filter({
            hasText: warningMessages[getLanguageCode()],
        });

        await test.step('Customer sees the warning for the stored delivery country', async () => {
            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.expects(cannotDeliverToCountryAlert).toBeVisible();
        });

        await test.step('The same delivery restriction remains after logout and login', async () => {
            await ShopCustomer.attemptsTo(Logout());
            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.expects(cannotDeliverToCountryAlert).toBeVisible();
            const shippingAddress = await TestDataService.getCustomerAddress(customer.defaultShippingAddressId);
            expect(shippingAddress.countryId).toBe(nonShippableCountry.id);
        });
    },
);
