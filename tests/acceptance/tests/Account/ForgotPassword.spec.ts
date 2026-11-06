import { test, getLocale } from '@fixtures/AcceptanceTest';

test(
    'As a customer, I can request a new password with existing customer email address.',
    {
        tag: [
            '@Account',
            '@Password',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountLogin, StorefrontAccountRecover, TestDataService }) => {
        const customer = await TestDataService.createCustomer();
        await test.step('Navigate to the login page and click on forgot password', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await ShopCustomer.presses(StorefrontAccountLogin.forgotPasswordLink);
        });

        await test.step('Fill in the customer email and request a password reset', async () => {
            await ShopCustomer.fillsIn(StorefrontAccountRecover.emailInput, customer.email);
            await ShopCustomer.presses(StorefrontAccountRecover.requestEmailButton);
        });

        await test.step('Verify the password reset email sent message and navigate back to login', async () => {
            await ShopCustomer.expects(StorefrontAccountRecover.passwordResetEmailSentMessage).toBeVisible();
            await ShopCustomer.presses(StorefrontAccountRecover.backButton);
            await ShopCustomer.expects(StorefrontAccountLogin.loginButton).toBeVisible();
        });
    },
);

test(
    'As a customer, I can request a new password without existing customer email address.',
    {
        tag: [
            '@Account',
            '@Password',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountLogin, StorefrontAccountRecover }) => {
        const uniqueEmail = `forgot-password-${test.info().repeatEachIndex}-${test.info().retry}-${Date.now()}@email.net`;

        await test.step('Navigate to login page and initiate password recovery', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await ShopCustomer.presses(StorefrontAccountLogin.forgotPasswordLink);
        });

        await test.step('Attempt to request password reset without entering an email', async () => {
            await ShopCustomer.presses(StorefrontAccountRecover.requestEmailButton);
            await ShopCustomer.expects(StorefrontAccountRecover.passwordResetEmailSentMessage).not.toBeVisible();
        });

        await test.step('Request password reset with a non-existing email', async () => {
            await ShopCustomer.fillsIn(StorefrontAccountRecover.emailInput, uniqueEmail);
            await ShopCustomer.presses(StorefrontAccountRecover.requestEmailButton);
            // Verify that the success message is shown for security reasons
            await ShopCustomer.expects(StorefrontAccountRecover.passwordResetEmailSentMessage).toBeVisible();
        });
    },
);

test(
    'As a customer, I can reset my password using the password recovery process for an existing account and successfully log in with the new password.',
    {
        tag: [
            '@Account',
            '@Password',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontAccountRecover,
        TestDataService,
        MailpitApiContext,
        InstanceMeta,
        Login,
        DefaultSalesChannel,
    }) => {
        test.skip(InstanceMeta.isSaaS || InstanceMeta.isPaaS, 'Skipping test because it requires a local mailpit instance.');

        let passwordResetLink = '';
        const newPassword = 'new-password';
        const customer = await TestDataService.createCustomer();
        const emailContent = getLocale().startsWith('zh')
            ? {
                  subject: '密码找回',
                  greeting: `您好 ${customer.name},`,
                  request: `您申请为 ${DefaultSalesChannel.salesChannel.name} 账户重置密码。`,
                  resetLink: '请点击下方链接重置密码：',
                  validity: '该链接仅在 2 小时内有效。',
                  ignore: '若您不想重置密码，请忽略本邮件，密码不会发生任何变更。',
                  closing: '此致',
                  team: `${DefaultSalesChannel.salesChannel.name} 团队`,
              }
            : {
                  subject: 'Password recovery',
                  greeting: `Hello ${customer.name},`,
                  request: `You have requested a new password for your ${DefaultSalesChannel.salesChannel.name} account.`,
                  resetLink: 'Click on the following link to reset your password:',
                  validity: 'This link is valid for the next 2 hours.',
                  ignore: "If you don't want to reset your password, ignore this email and no changes will be made.",
                  closing: 'Yours sincerely',
                  team: `Your ${DefaultSalesChannel.salesChannel.name} team`,
              };

        await test.step('Navigate to the login page and click on forgot password', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await ShopCustomer.presses(StorefrontAccountLogin.forgotPasswordLink);
        });

        await test.step('Fill in the customer email and request a password reset', async () => {
            await ShopCustomer.fillsIn(StorefrontAccountRecover.emailInput, customer.email);
            await ShopCustomer.presses(StorefrontAccountRecover.requestEmailButton);
        });

        await test.step('Verify the password reset email sent message and navigate back to login', async () => {
            await ShopCustomer.expects(StorefrontAccountRecover.passwordResetEmailSentMessage).toBeVisible();
            await ShopCustomer.presses(StorefrontAccountRecover.backButton);
            await ShopCustomer.expects(StorefrontAccountLogin.loginButton).toBeVisible();
        });

        await test.step('Verify email headers and content', async () => {
            const emailHeaders = await MailpitApiContext.getEmailHeaders(customer.email);
            const emailBody = await MailpitApiContext.getEmailBody(customer.email);
            ShopCustomer.expects(emailHeaders.subject).toContain(emailContent.subject);
            ShopCustomer.expects(emailHeaders.toName).toContain(customer.name);
            ShopCustomer.expects(emailHeaders.toAddress).toContain(customer.email);
            ShopCustomer.expects(emailBody).toContain(emailContent.greeting);
            ShopCustomer.expects(emailBody).toContain(emailContent.request);
            ShopCustomer.expects(emailBody).toContain(emailContent.resetLink);
            ShopCustomer.expects(emailBody).toContain(emailContent.validity);
            ShopCustomer.expects(emailBody).toContain(emailContent.ignore);
            ShopCustomer.expects(emailBody).toContain(emailContent.closing);
            ShopCustomer.expects(emailBody).toContain(emailContent.team);
        });

        await test.step('Update password using link in the email and login with new password successfully', async () => {
            passwordResetLink = await MailpitApiContext.getLinkFromMail(customer.email);
            await ShopCustomer.goesTo(StorefrontAccountRecover.url(passwordResetLink));
            await ShopCustomer.fillsIn(StorefrontAccountRecover.newPasswordInput, newPassword);
            await ShopCustomer.fillsIn(StorefrontAccountRecover.newPasswordConfirmInput, newPassword);
            await ShopCustomer.presses(StorefrontAccountRecover.changePasswordButton);
            await ShopCustomer.expects(StorefrontAccountLogin.passwordUpdatedAlert).toBeVisible();
            customer.password = newPassword;
            await ShopCustomer.expects(StorefrontAccountLogin.loginButton).toBeVisible();
            await ShopCustomer.attemptsTo(Login(customer));
        });

        await test.step('Verify that the recovery link is invalid after used. Request form displays so user can request again.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountRecover.url(passwordResetLink));
            await ShopCustomer.expects(StorefrontAccountRecover.invalidLinkMessage).toBeVisible();
            await ShopCustomer.expects(StorefrontAccountRecover.emailInput).toBeVisible();
            await ShopCustomer.expects(StorefrontAccountRecover.requestEmailButton).toBeVisible();
        });
    },
);
