import { test, expect, getLocale, getLanguageCode } from '@fixtures/AcceptanceTest';
import { getAddressDataFromLocale } from '../../helpers/locale-helpers';

test.describe('Customer Registration Form', () => {
    test.beforeEach(async ({ TestDataService, InstanceMeta }) => {
        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV2: {
                    name: 'googleReCaptchaV2',
                    isActive: false,
                    config: {
                        siteKey: '',
                        secretKey: '',
                        invisible: false,
                    },
                },
            },
        });
    });

    test(
        'As a customer, the registration privacy notice matches the checkbox and legal guarantee configuration.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Storefront',
            ],
        },
        async ({ ShopCustomer, StorefrontAccountLogin, TestDataService }) => {
            const messages = {
                'en-GB': {
                    privacy: 'Please note our privacy policy and general terms and conditions.',
                    register: 'Register',
                    guarantee: 'Your legal guarantee rights',
                },
                'zh-CN': {
                    privacy: '请查阅我们的 隐私政策 和 服务条款。',
                    register: '注册',
                    guarantee: '您的法定保修权益',
                },
            }[getLanguageCode()];
            const privacyNotice = StorefrontAccountLogin.page.locator('.register-form .privacy-notice');
            const dataProtectionCheckbox = privacyNotice.locator('input[name="acceptedDataProtection"]');
            const privacyLabel = privacyNotice.locator('label[for="acceptedDataProtection"], .data-protection-information');
            const legalGuaranteeNoticeParagraph = privacyNotice.locator('.legal-guarantee-notice');
            const legalGuaranteeNoticeLink = privacyNotice.locator('[data-bs-target="#legalGuaranteeNoticeModal"]');
            const legalGuaranteeNoticeModal = StorefrontAccountLogin.page.locator('#legalGuaranteeNoticeModal');

            // Checkbox disabled, legal guarantee notice disabled: only the passive privacy notice is shown.
            await TestDataService.setSystemConfig({
                'core.loginRegistration.requireDataProtectionCheckbox': false,
                'core.cart.showLegalGuaranteeNotice': false,
            });
            await ShopCustomer.goesTo(`${StorefrontAccountLogin.url()}?privacy-checkbox=disabled&guarantee=disabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toHaveCount(0);
            await ShopCustomer.expects(privacyLabel).toHaveText(messages.privacy);
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toHaveCount(0);
            await ShopCustomer.expects(StorefrontAccountLogin.registerButton).toHaveText(messages.register);

            // Checkbox disabled, legal guarantee notice enabled: the notice is shown as its own paragraph.
            await TestDataService.setSystemConfig({
                'core.cart.showLegalGuaranteeNotice': true,
            });
            await ShopCustomer.goesTo(`${StorefrontAccountLogin.url()}?privacy-checkbox=disabled&guarantee=enabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toBeVisible();

            await legalGuaranteeNoticeLink.click();

            await ShopCustomer.expects(legalGuaranteeNoticeModal).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeModal).toContainText(messages.guarantee);
            await ShopCustomer.expects(legalGuaranteeNoticeModal.getByRole('link')).toBeVisible();

            // Checkbox enabled, legal guarantee notice enabled: both the checkbox and the notice paragraph are shown.
            await TestDataService.setSystemConfig({
                'core.loginRegistration.requireDataProtectionCheckbox': true,
            });
            await ShopCustomer.goesTo(`${StorefrontAccountLogin.url()}?privacy-checkbox=enabled&guarantee=enabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toBeVisible();
            await ShopCustomer.expects(privacyLabel).not.toContainText(messages.privacy);
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toBeVisible();

            // Checkbox enabled, legal guarantee notice disabled: the checkbox stays, the notice paragraph disappears.
            await TestDataService.setSystemConfig({
                'core.cart.showLegalGuaranteeNotice': false,
            });
            await ShopCustomer.goesTo(`${StorefrontAccountLogin.url()}?privacy-checkbox=enabled&guarantee=disabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toHaveCount(0);
        },
    );

    test(
        'As a customer, I can perform a registration without captcha protection.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Storefront',
            ],
        },
        async ({ ShopCustomer, StorefrontAccountLogin, StorefrontAccount, IdProvider, Register }) => {
            const customer = { email: `${IdProvider.getIdPair().uuid}@test.com` };

            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await ShopCustomer.attemptsTo(Register(customer));

            await StorefrontAccountLogin.page.waitForURL('**/account', { waitUntil: 'commit' });

            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        },
    );

    test(
        'As a customer, I can perform a registration with full customer data without captcha protection.',
        {
            tag: [
                '@Form',
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
            TestDataService,
        }) => {
            const locale = getLocale();
            const addressData = getAddressDataFromLocale(locale);

            const customer = {
                name: 'Jeff Goldblum',
                email: `${IdProvider.getIdPair().uuid}@test.com`,
                password: 'shopwell',
                street: addressData.street,
                city: addressData.city,
                country: addressData.country,
                postalCode: addressData.postalCode,
            };

            const product = await TestDataService.createBasicProduct();
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product));
            await ShopCustomer.presses(StorefrontProductDetail.offCanvasCartGoToCheckoutButton);
            await StorefrontCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });
            await StorefrontAccountLogin.page.locator('[name="createCustomerAccount"]').check();

            await StorefrontAccountLogin.nameInput.fill(customer.name);
            await StorefrontAccountLogin.registerEmailInput.fill(customer.email);
            await StorefrontAccountLogin.registerPasswordInput.fill(customer.password);

            await StorefrontAccountLogin.streetAddressInput.fill(customer.street);
            await StorefrontAccountLogin.postalCodeInput.fill(customer.postalCode);
            await StorefrontAccountLogin.cityInput.fill(customer.city);
            await StorefrontAccountLogin.countryInput.selectOption({ label: customer.country });
            const countryId = await StorefrontAccountLogin.countryInput.inputValue();

            await StorefrontAccountLogin.registerButton.click();

            await ShopCustomer.expects(StorefrontCheckoutConfirm.headline).toBeVisible();
            const registeredCustomer = await TestDataService.getCustomerByEmail(customer.email);
            TestDataService.addCreatedRecord('customer', registeredCustomer.id);
            expect(registeredCustomer.guest).toBe(false);
            expect(registeredCustomer.name).toBe(customer.name);
            const billingAddress = await TestDataService.getCustomerAddress(registeredCustomer.defaultBillingAddressId);
            expect(billingAddress).toMatchObject({
                name: customer.name,
                street: customer.street,
                city: customer.city,
                zipcode: customer.postalCode,
                countryId,
            });

            await ShopCustomer.goesTo(StorefrontAccount.url());
            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        },
    );

    test(
        'As a customer, I can perform a registration with validation errors without captcha protection.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Storefront',
            ],
        },
        async ({
            ShopCustomer,
            StorefrontAccountLogin,
            StorefrontProductDetail,
            StorefrontCheckoutRegister,
            StorefrontCheckoutConfirm,
            StorefrontAccount,
            AddProductToCart,
            IdProvider,
            TestDataService,
        }) => {
            const locale = getLocale();
            const addressData = getAddressDataFromLocale(locale);

            const customer = {
                // name is missing intentionally
                email: `${IdProvider.getIdPair().uuid}@test.com`,
                password: 'shopwell',
                street: addressData.street,
                city: addressData.city,
                country: addressData.country,
                postalCode: addressData.postalCode,
            };

            const product = await TestDataService.createBasicProduct();
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product));
            await ShopCustomer.presses(StorefrontProductDetail.offCanvasCartGoToCheckoutButton);
            await StorefrontCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });
            await StorefrontAccountLogin.page.locator('[name="createCustomerAccount"]').check();

            await StorefrontAccountLogin.registerEmailInput.fill(customer.email);
            await StorefrontAccountLogin.registerPasswordInput.fill(customer.password);

            await StorefrontAccountLogin.streetAddressInput.fill(customer.street);
            await StorefrontAccountLogin.postalCodeInput.fill(customer.postalCode);
            await StorefrontAccountLogin.cityInput.fill(customer.city);
            await StorefrontAccountLogin.countryInput.selectOption({ label: customer.country });
            const countryId = await StorefrontAccountLogin.countryInput.inputValue();

            await StorefrontAccountLogin.registerButton.click();

            await ShopCustomer.expects(StorefrontAccountLogin.nameInput).toHaveClass(/(^|\s)is-invalid(\s|$)/);
            await ShopCustomer.expects(StorefrontCheckoutRegister.page).toHaveURL(/\/checkout\/register$/);
            expect(await TestDataService.getCustomerByEmail(customer.email)).toBeUndefined();

            await StorefrontAccountLogin.nameInput.fill('Jeff Goldblum');
            await StorefrontAccountLogin.registerButton.click();

            await ShopCustomer.expects(StorefrontCheckoutConfirm.headline).toBeVisible();
            const registeredCustomer = await TestDataService.getCustomerByEmail(customer.email);
            TestDataService.addCreatedRecord('customer', registeredCustomer.id);
            expect(registeredCustomer.guest).toBe(false);
            expect(registeredCustomer.name).toBe('Jeff Goldblum');
            const billingAddress = await TestDataService.getCustomerAddress(registeredCustomer.defaultBillingAddressId);
            expect(billingAddress).toMatchObject({
                name: 'Jeff Goldblum',
                street: customer.street,
                city: customer.city,
                zipcode: customer.postalCode,
                countryId,
            });

            await ShopCustomer.goesTo(StorefrontAccount.url());
            await ShopCustomer.expects(StorefrontAccountLogin.page.getByText(customer.email, { exact: true })).toBeVisible();
        },
    );
});
