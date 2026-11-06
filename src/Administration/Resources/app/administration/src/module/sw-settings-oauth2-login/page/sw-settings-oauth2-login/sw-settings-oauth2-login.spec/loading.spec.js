/**
 * @sw-package framework
 */
import {
    createWrapper,
    googleClient,
    keycloakChannel,
    officialAccountChannel,
    selectChannel,
    save,
    setupPageHooks,
} from './sw-settings-oauth2-login.fixtures';

describe('src/module/sw-settings-oauth2-login/page/sw-settings-oauth2-login', () => {
    setupPageHooks();

    it('should merge the channels of the server with the configured clients', async () => {
        const { wrapper } = await createWrapper({ clients: [{ ...googleClient, scopes: ['openid'] }] });

        expect(wrapper.findAll('[data-channel]')).toHaveLength(2);
        expect(wrapper.get('h3').text()).toBe('sso.oauth2.channel.google');
        expect(wrapper.get('.sw-settings-oauth2-login__channel-enabled').element.checked).toBe(true);
        expect(wrapper.get('.sw-settings-oauth2-login__field-clientId input').element.value).toBe('google-id');
        expect(wrapper.get('.sw-settings-oauth2-login__field-clientSecret input').element.value).toBe('google-secret');
        expect(wrapper.find('.sw-settings-oauth2-login__channel-reset').exists()).toBe(true);

        await selectChannel(wrapper, 'wechat');
        expect(wrapper.get('h3').text()).toBe('sso.oauth2.channel.wechat');
        expect(wrapper.get('.sw-settings-oauth2-login__channel-enabled').element.checked).toBe(false);
        expect(wrapper.get('.sw-settings-oauth2-login__field-clientId input').element.value).toBe('');
        expect(wrapper.get('.sw-settings-oauth2-login__field-clientSecret input').element.value).toBe('');
        expect(wrapper.find('.sw-settings-oauth2-login__channel-reset').exists()).toBe(false);
    });

    it('should report a failed load and stop the spinner', async () => {
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        const { wrapper } = await createWrapper({ listError: new Error('Could not load the channels') });

        expect(wrapper.findAll('[data-channel]')).toHaveLength(0);
        expect(wrapper.find('sw-skeleton-stub').exists()).toBe(false);
        expect(notification).toHaveBeenCalledWith({
            variant: 'error',
            title: 'global.default.error',
            message: 'Could not load the channels',
        });
        notification.mockRestore();
    });

    it('should render one field per declared field of the channel', async () => {
        const { wrapper } = await createWrapper({ channels: [keycloakChannel] });
        const fields = wrapper.findAllComponents({ name: 'sw-form-field-renderer' });

        expect(fields).toHaveLength(3);
        expect(fields.map((field) => field.props('type'))).toEqual(['text', 'password', 'url']);
        expect(fields.map((field) => field.props('config').label)).toEqual([
            'sw-settings-oauth2-login.channel.clientId',
            'sw-settings-oauth2-login.channel.clientSecret',
            { 'en-GB': 'Auth server', 'zh-CN': '认证服务器' },
        ]);
    });

    it('should add no field of its own for a channel that declares none', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(2);
        expect(wrapper.find('.sw-settings-oauth2-login__field-clientId').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-oauth2-login__field-clientSecret').exists()).toBe(true);
    });

    it('should render the scope field of a channel that has more than one', async () => {
        const { wrapper } = await createWrapper({ channels: [officialAccountChannel] });
        const scopes = wrapper.getComponent('.sw-settings-oauth2-login__field-scopes');

        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(3);
        expect(scopes.props('type')).toBe('multi-select');
        expect(scopes.props('config').placeholder).toBe('snsapi_base');
        expect(scopes.props('config').options).toHaveLength(2);
    });

    it('should select the first channel and keep the selected tab on a reload', async () => {
        const { wrapper } = await createWrapper({ clients: [googleClient] });

        expect(wrapper.getComponent({ name: 'mt-tabs' }).props('defaultItem')).toBe('google');
        await selectChannel(wrapper, 'wechat');
        await save(wrapper);
        expect(wrapper.getComponent({ name: 'mt-tabs' }).props('defaultItem')).toBe('wechat');
    });
});
