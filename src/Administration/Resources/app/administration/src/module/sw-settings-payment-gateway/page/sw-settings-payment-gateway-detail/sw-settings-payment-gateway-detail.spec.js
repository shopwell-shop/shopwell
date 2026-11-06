/**
 * @sw-package framework
 */
import { cleanup, createWrapper } from './sw-settings-payment-gateway-detail.spec/create-wrapper';
import { availableAccounts, existingApp, platformApp } from './sw-settings-payment-gateway-detail.spec/fixtures';

const accountRows = (wrapper) => wrapper.findAll('.sw-settings-payment-gateway-detail__account');
const save = async (wrapper) => {
    await wrapper.find('.sw-settings-payment-gateway-detail__save-action').trigger('click');
    await flushPromises();
};

describe('payment app accounts and products', () => {
    beforeEach(() => {
        global.activeAclRoles = [
            'payment_app.viewer',
            'payment_app.creator',
            'payment_app.editor',
            'payment_app.deleter',
        ];
    });

    afterEach(cleanup);

    it('shows saved grants as compact rows without merchant configuration fields', async () => {
        const { wrapper } = await createWrapper();
        expect(accountRows(wrapper)).toHaveLength(1);
        expect(wrapper.findAll('.sw-settings-payment-gateway-detail__product')).toHaveLength(4);
        expect(accountRows(wrapper)[0].find('option[value="account-alipay"]').text()).toBe('Merchant');
        expect(wrapper.find('[data-field]').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-payment-gateway-detail__account-name-field').exists()).toBe(false);
        expect(wrapper.find('details').exists()).toBe(false);
        expect(wrapper.find('[name="sw-field--payment-app-notify-url"]').exists()).toBe(false);
    });

    it('groups identity controls and keeps accessible names on compact grant and product controls', async () => {
        const { wrapper } = await createWrapper();
        const identity = wrapper.find('.sw-settings-payment-gateway-detail__identity-grid');
        expect(identity.find('[name="sw-field--payment-app-name"]').exists()).toBe(true);
        expect(identity.find('.sw-settings-payment-gateway-detail__active').exists()).toBe(true);
        const row = accountRows(wrapper)[0];
        expect(row.findAll('.sw-settings-payment-gateway-detail__grant-field')).toHaveLength(3);
        expect(row.text()).toContain('sw-settings-payment-gateway.detail.labelProviderEnabled');
        expect(wrapper.find('th:nth-child(4)').text()).toBe('sw-settings-payment-gateway.detail.grantEnabled');
        const products = wrapper.find('.sw-settings-payment-gateway-detail__products');
        expect(products.findAll('.sw-settings-payment-gateway-detail__product')).toHaveLength(4);
        expect(products.find('label').text()).toBe('支付宝 手机网站支付');
    });

    it('removes a blank row immediately and never saves unsaved grants', async () => {
        const { wrapper, service } = await createWrapper();
        await wrapper.find('.sw-settings-payment-gateway-detail__add-account').trigger('click');
        expect(accountRows(wrapper)).toHaveLength(2);
        await save(wrapper);
        expect(service.saveApp.mock.calls[0][0].accounts).toEqual(existingApp.accounts);
        await wrapper.find('.sw-settings-payment-gateway-detail__add-account').trigger('click');
        await accountRows(wrapper)[1].find('.sw-settings-payment-gateway-detail__remove-account').trigger('click');
        expect(accountRows(wrapper)).toHaveLength(1);
    });

    it('grants a previously ungranted account and saves only account ID, priority and enabled', async () => {
        const { wrapper, service } = await createWrapper();
        await wrapper.find('.sw-settings-payment-gateway-detail__add-account').trigger('click');
        const added = accountRows(wrapper)[1];
        expect(added.find('option[value="account-alipay"]').exists()).toBe(false);
        await added.find('select').setValue('account-ungranted');
        await added.find('.sw-settings-payment-gateway-detail__account-position input').setValue('5');
        await added.find('input[type="checkbox"]').setValue(false);
        await save(wrapper);
        expect(service.saveApp.mock.calls[0][0].accounts).toEqual([
            { accountId: 'account-alipay', enabled: true, position: 0 },
            { accountId: 'account-ungranted', enabled: false, position: '5' },
        ]);
    });

    it('prevents adding an empty grant when no active ungranted accounts remain', async () => {
        const { wrapper } = await createWrapper({
            accounts: availableAccounts.filter((account) => account.id === 'account-alipay'),
        });
        const add = wrapper.find('.sw-settings-payment-gateway-detail__add-account');
        expect(add.attributes('disabled')).toBeDefined();
        await add.trigger('click');
        expect(accountRows(wrapper)).toHaveLength(1);

        await accountRows(wrapper)[0].find('.sw-settings-payment-gateway-detail__remove-account').trigger('click');
        expect(add.attributes('disabled')).toBeUndefined();
        await add.trigger('click');
        expect(accountRows(wrapper)).toHaveLength(1);
        expect(add.attributes('disabled')).toBeDefined();
    });

    it('enables product selection only when an enabled grant matches its provider', async () => {
        const { wrapper } = await createWrapper();
        const products = wrapper.findAll('.sw-settings-payment-gateway-detail__product');
        expect(products[0].find('input').attributes('disabled')).toBeUndefined();
        expect(products[1].find('input').attributes('disabled')).toBeDefined();
        await accountRows(wrapper)[0].find('input[type="checkbox"]').setValue(false);
        expect(products[0].find('input').attributes('disabled')).toBeDefined();
    });

    it('keeps platform grants editable and prevents deletion of the platform app', async () => {
        const { wrapper } = await createWrapper({ appId: platformApp.id, app: platformApp });
        expect(accountRows(wrapper)[0].find('a').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-payment-gateway-detail__delete-action').attributes('disabled')).toBeDefined();
    });

    it('shows provider identifiers for generated account names and preserves custom names', async () => {
        const { wrapper } = await createWrapper({ appId: platformApp.id, app: platformApp });
        const row = accountRows(wrapper)[0];
        expect(row.find('option[value="account-platform"]').text()).toBe('alipay');
        expect(row.text()).not.toContain('alipay platform');
        const custom = await createWrapper({
            accounts: availableAccounts.map((account) =>
                account.id === 'account-alipay' ? { ...account, name: 'Regional merchant', isDefault: true } : account,
            ),
        });
        expect(accountRows(custom.wrapper)[0].find('option[value="account-alipay"]').text()).toBe('Regional merchant');
    });

    it('starts a new app without an implicit payment account grant', async () => {
        const { wrapper, service } = await createWrapper({ appId: 'new' });
        await save(wrapper);
        expect(service.saveApp.mock.calls[0][0].accounts).toEqual([]);
    });

    it('redirects when the requested app no longer exists', async () => {
        const { push } = await createWrapper({ app: null });
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.gateway.list' });
    });

    it('shows a rotated secret once and clears the entered secret after saving', async () => {
        const { wrapper, service } = await createWrapper({
            savedApp: existingApp,
            appSecret: 'rotated-secret',
            section: 'integration',
        });
        await wrapper.find('[name="sw-field--payment-app-secret"]').setValue('new-secret');
        await save(wrapper);
        expect(service.saveApp.mock.calls[0][0].appSecret).toBe('new-secret');
        expect(wrapper.find('[name="sw-field--payment-app-secret"]').element.value).toBe('');
        expect(wrapper.find('.sw-settings-payment-gateway-detail__secret-value').text()).toBe('rotated-secret');
        await wrapper
            .find('.sw-settings-payment-gateway-detail__secret-value')
            .element.parentElement.querySelector('button')
            .click();
        await flushPromises();
        expect(wrapper.find('.sw-settings-payment-gateway-detail__secret-value').exists()).toBe(false);
    });

    it('generates a cryptographically random secret from the visible action', async () => {
        const { wrapper } = await createWrapper({ section: 'integration' });
        await wrapper.find('.sw-settings-payment-gateway-detail__secret-generate').trigger('click');
        expect(wrapper.find('[name="sw-field--payment-app-secret"]').element.value).toMatch(/^[a-f0-9]{64}$/);
    });

    it('disables editing for users without editor permissions', async () => {
        global.activeAclRoles = ['payment_app.viewer'];
        const { wrapper } = await createWrapper();
        expect(wrapper.find('.sw-settings-payment-gateway-detail__save-action').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-settings-payment-gateway-detail__add-account').attributes('disabled')).toBeDefined();
        expect(accountRows(wrapper)[0].find('select').attributes('disabled')).toBeDefined();
    });

    it('routes between collection, cashier and integration tasks', async () => {
        const { wrapper, push } = await createWrapper();
        await wrapper.find('[data-tab="cashier"]').trigger('click');
        expect(push).toHaveBeenCalledWith({
            name: 'sw.settings.payment.gateway.detail',
            params: { id: 'app-1', section: 'cashier' },
        });
    });

    it('opens integration fields directly on the integration route', async () => {
        const { wrapper } = await createWrapper({ section: 'integration' });
        expect(wrapper.find('[name="sw-field--payment-app-notify-url"]').exists()).toBe(true);
        expect(wrapper.find('[name="sw-field--payment-app-technical-name"]').exists()).toBe(true);
        expect(wrapper.find('details').exists()).toBe(false);
        expect(accountRows(wrapper)).toHaveLength(0);
    });

    it('passes the saved app to the cashier editor without rendering collection or API controls', async () => {
        const { wrapper } = await createWrapper({ section: 'cashier' });
        expect(wrapper.find('sw-payment-cashier-profiles-stub').attributes('app-id')).toBe('app-1');
        expect(accountRows(wrapper)).toHaveLength(0);
        expect(wrapper.find('[name="sw-field--payment-app-secret"]').exists()).toBe(false);
    });

    it('deletes the app through its confirmation dialog', async () => {
        const { wrapper, service, push } = await createWrapper();
        await wrapper.find('.sw-settings-payment-gateway-detail__delete-action').trigger('click');
        const buttons = wrapper.findAll('button');
        await buttons[buttons.length - 1].trigger('click');
        await flushPromises();
        expect(service.deleteApp).toHaveBeenCalledWith('app-1');
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.gateway.list' });
    });
});
