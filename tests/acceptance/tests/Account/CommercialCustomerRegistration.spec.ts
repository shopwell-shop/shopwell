import { expect, getLanguageCode, test } from '@fixtures/AcceptanceTest';

test(
    'New customers can register as commercial customers during checkout.',
    {
        tag: [
            '@Registration',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontAccount,
        StorefrontProductDetail,
        StorefrontCheckoutRegister,
        StorefrontCheckoutConfirm,
        AddProductToCart,
        IdProvider,
        Register,
        TestDataService,
    }) => {
        const uuid = IdProvider.getIdPair().uuid;
        const customer = { isCommercial: true, email: uuid + '@test.com', vatRegNo: uuid + '-VatId' };
        await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });

        const product = await TestDataService.createBasicProduct();

        await test.step('Register as a commercial customer account during checkout', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product));
            await ShopCustomer.presses(StorefrontProductDetail.offCanvasCartGoToCheckoutButton);
            await StorefrontCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });
            await StorefrontAccountLogin.page.locator('[name="createCustomerAccount"]').check();
            await StorefrontAccountLogin.accountTypeSelect.selectOption('business');
            await ShopCustomer.attemptsTo(Register(customer));
        });

        await test.step('Verify successful commercial customer registration', async () => {
            await ShopCustomer.expects(StorefrontCheckoutConfirm.headline).toBeVisible();
            await ShopCustomer.goesTo(StorefrontAccount.url());
            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
            const companyDetails = StorefrontAccount.page.locator('.account-overview-profile');
            await ShopCustomer.expects(companyDetails).toContainText('shopwell - Operations');
            await ShopCustomer.expects(companyDetails).toContainText(customer.vatRegNo.toUpperCase());
            const registeredCustomer = await TestDataService.getCustomerByEmail(customer.email);
            expect(registeredCustomer.accountType).toBe('business');
            expect(registeredCustomer.guest).toBe(false);
            expect(registeredCustomer.company).toBe('shopwell');
            expect(registeredCustomer.vatIds).toEqual([customer.vatRegNo.toUpperCase()]);
        });
    },
);

test(
    'New commercial customer accounts cannot complete checkout registration without a VAT Reg.No.',
    {
        tag: [
            '@Registration',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontProductDetail,
        StorefrontCheckoutRegister,
        AddProductToCart,
        IdProvider,
        Register,
        TestDataService,
        DefaultSalesChannel,
    }) => {
        await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
        const country = await TestDataService.createCountry({ vatIdRequired: true });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, country.id);
        const customer = {
            isCommercial: true,
            email: `${IdProvider.getIdPair().uuid}@test.com`,
            country: country.name,
            vatRegNo: '',
        };

        const product = await TestDataService.createBasicProduct();

        await test.step('Attempt checkout account registration without a VAT Reg.No.', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product));
            await ShopCustomer.presses(StorefrontProductDetail.offCanvasCartGoToCheckoutButton);
            await StorefrontCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });
            await StorefrontAccountLogin.page.locator('[name="createCustomerAccount"]').check();
            await StorefrontAccountLogin.accountTypeSelect.selectOption('business');
            await ShopCustomer.attemptsTo(Register(customer));
        });

        await test.step('Verify registration is blocked due to missing VAT Reg.No.', async () => {
            await ShopCustomer.expects(StorefrontCheckoutRegister.page).toHaveURL(/\/checkout\/register$/);
            await ShopCustomer.expects(StorefrontAccountLogin.registerButton).toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveAttribute('aria-required');
            expect(await TestDataService.getCustomerByEmail(customer.email)).toBeUndefined();
            await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
        });
    },
);

test(
    'New commercial customer accounts cannot complete checkout registration with an invalid VAT Reg.No.',
    {
        tag: [
            '@Registration',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontProductDetail,
        StorefrontCheckoutRegister,
        AddProductToCart,
        IdProvider,
        Register,
        TestDataService,
        DefaultSalesChannel,
    }) => {
        await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
        const country = await TestDataService.createCountry({ checkVatIdPattern: true, vatIdPattern: 'DE\\d{9}' });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, country.id);
        const uuid = IdProvider.getIdPair().uuid;
        const customer = { isCommercial: true, email: uuid + '@test.com', vatRegNo: `${uuid}-VatId`, country: country.name };

        const product = await TestDataService.createBasicProduct();

        await test.step('Attempt checkout account registration with an invalid VAT Reg.No.', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product));
            await ShopCustomer.presses(StorefrontProductDetail.offCanvasCartGoToCheckoutButton);
            await StorefrontCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });
            await StorefrontAccountLogin.page.locator('[name="createCustomerAccount"]').check();
            await StorefrontAccountLogin.accountTypeSelect.selectOption('business');
            await ShopCustomer.attemptsTo(Register(customer));
        });

        await test.step('Verify registration is blocked due to invalid VAT Reg.No. format', async () => {
            await ShopCustomer.expects(StorefrontCheckoutRegister.page).toHaveURL(/\/checkout\/register$/);
            await ShopCustomer.expects(StorefrontAccountLogin.registerButton).toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.vatRegNoInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            const invalidVatMessages = {
                'en-GB': 'The VAT Reg.No. you have entered does not have the correct format.',
                'zh-CN': '增值税号格式不正确',
            };
            await ShopCustomer.expects(StorefrontAccountLogin.page.locator('#vatIds-feedback')).toContainText(
                invalidVatMessages[getLanguageCode()],
            );
            expect(await TestDataService.getCustomerByEmail(customer.email)).toBeUndefined();
        });
    },
);
