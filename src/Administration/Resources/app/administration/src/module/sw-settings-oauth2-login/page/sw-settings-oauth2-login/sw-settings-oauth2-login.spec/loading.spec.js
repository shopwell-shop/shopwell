/**
 * @sw-package framework
 */
import {
    createWrapper,
    googleClient,
    keycloakChannel,
    officialAccountChannel,
    setupPageHooks,
} from './sw-settings-oauth2-login.fixtures';

describe('src/module/sw-settings-oauth2-login/page/sw-settings-oauth2-login', () => {
    setupPageHooks();

    it('should merge the channels of the server with the configured clients', async () => {
        const { wrapper } = await createWrapper({
            clients: [
                {
                    ...googleClient,
                    scopes: ['openid'],
                },
            ],
        });

        expect(wrapper.vm.channels).toHaveLength(2);

        expect(wrapper.vm.channels[0]).toEqual(
            expect.objectContaining({
                technicalName: 'google',
                label: 'sso.oauth2.channel.google',
                id: 'client-google',
                enabled: true,
                values: {
                    clientId: 'google-id',
                    clientSecret: 'google-secret',
                },
            }),
        );

        expect(wrapper.vm.channels[1]).toEqual(
            expect.objectContaining({
                technicalName: 'wechat',
                label: 'sso.oauth2.channel.wechat',
                id: null,
                enabled: false,
                values: {
                    clientId: '',
                    clientSecret: '',
                },
            }),
        );
    });

    it('should report a failed load and stop the spinner', async () => {
        const notificationSpy = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        const { wrapper } = await createWrapper({ listError: new Error('Could not load the channels') });

        expect(wrapper.vm.channels).toEqual([]);
        expect(wrapper.vm.isLoading).toBe(false);

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'error',
            title: 'global.default.error',
            message: 'Could not load the channels',
        });

        notificationSpy.mockRestore();
    });

    it('should render one field per declared field of the channel', async () => {
        const { wrapper } = await createWrapper({ channels: [keycloakChannel] });

        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(3);

        const fields = wrapper.vm.channels[0].formFields;

        expect(fields.map((field) => field.type)).toEqual(['text', 'password', 'url']);
        expect(fields.map((field) => field.config.label)).toEqual([
            'sw-settings-oauth2-login.channel.clientId',
            'sw-settings-oauth2-login.channel.clientSecret',
            { 'en-GB': 'Auth server', 'zh-CN': '认证服务器' },
        ]);
    });

    it('should add no field of its own for a channel that declares none', async () => {
        const { wrapper } = await createWrapper();

        // google and wechat take one fixed scope, so their form is the credentials and nothing else
        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(2);
        expect(wrapper.vm.channels[0].formFields.map((field) => field.name)).toEqual(['clientId', 'clientSecret']);
    });

    it('should render the scope field of a channel that has more than one', async () => {
        const { wrapper } = await createWrapper({ channels: [officialAccountChannel] });

        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(3);

        const scopes = wrapper.vm.channels[0].formFields[2];

        expect(scopes.name).toBe('scopes');
        expect(scopes.type).toBe('multi-select');
        expect(scopes.config.placeholder).toBe('snsapi_base');
        expect(scopes.config.options).toHaveLength(2);
    });

    it('should select the first channel and keep the selected tab on a reload', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.activeChannel).toBe('google');

        wrapper.vm.activeChannel = 'wechat';

        await wrapper.vm.loadChannels();

        expect(wrapper.vm.activeChannel).toBe('wechat');
    });
});
