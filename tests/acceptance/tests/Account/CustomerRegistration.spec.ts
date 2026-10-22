import { test } from '@fixtures/AcceptanceTest';

test(
    'As a new customer, I must be able to register in the Storefront with an email address and a password only.',
    {
        tag: [
            '@Registration',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountLogin, StorefrontAccount, IdProvider }) => {
        const customer = { email: IdProvider.getIdPair().uuid + '@test.com', password: 'shopware' };

        await ShopCustomer.goesTo(StorefrontAccountLogin.url());

        await test.step('Register with the credentials only', async () => {
            // The account registration does not ask for the name or an address anymore,
            // both are collected during the checkout.
            await StorefrontAccountLogin.registerEmailInput.fill(customer.email);
            await StorefrontAccountLogin.registerPasswordInput.fill(customer.password);
            await StorefrontAccountLogin.registerButton.click();
        });

        await test.step('Validate the customer is logged in', async () => {
            await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
        });
    },
);

test(
    'As a new customer, I should not have to provide an address during the registration.',
    {
        tag: [
            '@Registration',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountLogin }) => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());

        await test.step('Validate the registration form only contains the credentials', async () => {
            await ShopCustomer.expects(StorefrontAccountLogin.registerEmailInput).toBeVisible();
            await ShopCustomer.expects(StorefrontAccountLogin.registerPasswordInput).toBeVisible();

            // Neither the personal data nor the address forms are rendered on the account registration page,
            // the (country specific) postal code validation therefore applies to the checkout address only.
            await ShopCustomer.expects(StorefrontAccountLogin.page.locator('[name^="billingAddress"]')).toHaveCount(0);
            await ShopCustomer.expects(StorefrontAccountLogin.page.locator('[name^="shippingAddress"]')).toHaveCount(0);
            // The credentials are rendered inside the `register-personal` fieldset, only the personal data
            // fields (salutation, first and last name, company) are dropped from the form.
            await ShopCustomer.expects(StorefrontAccountLogin.salutationSelect).toHaveCount(0);
            await ShopCustomer.expects(StorefrontAccountLogin.nameInput).toHaveCount(0);
            await ShopCustomer.expects(StorefrontAccountLogin.postalCodeInput).toHaveCount(0);
            await ShopCustomer.expects(StorefrontAccountLogin.differentShippingAddressCheckbox).toHaveCount(0);
        });
    },
);
