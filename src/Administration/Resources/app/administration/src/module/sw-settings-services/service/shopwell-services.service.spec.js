import createHTTPClient from 'src/core/factory/http.factory';
import createLoginService from 'src/core/service/login.service';
import MockAdapter from 'axios-mock-adapter';
import ShopwellServicesService from './shopwell-services.service';
import SystemConfigApiService from '../../../core/service/api/system-config.api.service';

describe('src/module/sw-settings-services/service/shopwell-services-service.ts', () => {
    it.each([
        [undefined, 'en-US', 'en-US'],
        ['zh-CN', 'en-US', 'zh-CN'],
    ])(
        'loads installed services using the correct language',
        async (sessionLanguage, apiContextLanguage, expectedLanguage) => {
            Shopwell.Store.get('session').languageId = sessionLanguage;
            Shopwell.Context.api.languageId = apiContextLanguage;

            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            const loginService = createLoginService(client, Shopwell.Context.api);
            const systemConfigService = jest.fn();
            const shopwellServicesService = new ShopwellServicesService(client, loginService, systemConfigService);

            clientMock.onGet('service/list').reply(200, [
                { name: 'Service1', active: true, requirements: ['service_consent'] },
                { name: 'Service2', active: false, requirements: ['shopwell_account'] },
                { name: 'Service3', active: true, requirements: [] },
            ]);

            const installedServices = await shopwellServicesService.getInstalledServices();

            expect(installedServices).toHaveLength(3);
            expect(installedServices[0].requirements).toEqual(['service_consent']);
            expect(installedServices[1].requirements).toEqual(['shopwell_account']);
            expect(installedServices[2].requirements).toEqual([]);
            expect(clientMock.history.get).toHaveLength(1);
            expect(clientMock.history.get[0].headers['sw-language-id']).toBe(expectedLanguage);
        },
    );

    it.each([
        [
            undefined,
            undefined,
            undefined,
            undefined,
        ],
        [
            true,
            undefined,
            true,
            undefined,
        ],
        [
            false,
            undefined,
            false,
            undefined,
        ],
        [
            true,
            JSON.stringify({
                identifier: 'identifier',
                revision: '2025-07-07',
                consentingUserId: 'consenting-user-id',
                grantedAt: '2025-07-07T00:00:00.000Z',
            }),
            true,
            {
                identifier: 'identifier',
                revision: '2025-07-07',
                consentingUserId: 'consenting-user-id',
                grantedAt: '2025-07-07T00:00:00.000Z',
            },
        ],
    ])(
        'loads the services context',
        async (
            configValueDisabled,
            configValuePermissionsConsent,
            expectedValueDisabled,
            expectedValuePermissionsConsent,
        ) => {
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            const loginService = createLoginService(client, Shopwell.Context.api);
            const systemConfigService = new SystemConfigApiService(client, loginService);
            const shopwellServicesService = new ShopwellServicesService(client, loginService, systemConfigService);

            clientMock.onGet('_action/system-config').reply(200, {
                'core.services.disabled': configValueDisabled,
                'core.services.permissionsConsent': configValuePermissionsConsent,
            });

            const servicesContext = await shopwellServicesService.getServicesContext();

            expect(servicesContext.disabled).toBe(expectedValueDisabled);
            expect(servicesContext.permissionsConsent).toEqual(expectedValuePermissionsConsent);
        },
    );

    it('accepts permissions revision', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopwell.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwellServicesService = new ShopwellServicesService(client, loginService, systemConfigService);

        const revision = '2025-07-07';

        clientMock.onPost(`services/permissions/grant/${revision}`).reply(204, {
            success: true,
        });

        clientMock.onGet('_action/system-config').reply(200, {
            'core.services.disabled': undefined,
            'core.services.permissionsConsent': undefined,
        });

        await shopwellServicesService.acceptRevision(revision);

        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].url).toBe('services/permissions/grant/2025-07-07');
    });

    it('revokes permissions', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopwell.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwellServicesService = new ShopwellServicesService(client, loginService, systemConfigService);

        clientMock.onPost('services/permissions/revoke').reply(204, {
            success: true,
        });

        clientMock.onGet('_action/system-config').reply(200, {
            'core.services.disabled': undefined,
            'core.services.permissionsConsent': undefined,
        });

        await shopwellServicesService.revokePermissions();

        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].url).toBe('services/permissions/revoke');
    });

    it.each([['enable'], ['disable']])('enables and disables all services', async (action) => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopwell.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwellServicesService = new ShopwellServicesService(client, loginService, systemConfigService);

        clientMock.onPost(`services/${action}`).reply(204, {
            success: true,
        });

        clientMock.onGet('_action/system-config').reply(200, {
            'core.services.disabled': undefined,
            'core.services.permissionsConsent': undefined,
        });

        if (action === 'disable') {
            await shopwellServicesService.disableAllServices();
        }

        if (action === 'enable') {
            await shopwellServicesService.enableAllServices();
        }

        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].url).toBe(`services/${action}`);
    });

    it.each([
        ['activate', 'activateService'],
        ['deactivate', 'deactivateService'],
    ])('%s a service', async (action, methodName) => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopwell.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwellServicesService = new ShopwellServicesService(client, loginService, systemConfigService);

        clientMock.onPost(`service/${action}/MyCoolService`).reply(204);

        await shopwellServicesService[methodName]('MyCoolService');

        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].url).toBe(`service/${action}/MyCoolService`);
    });

    it('returns categorized permissions', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopwell.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwellServicesService = new ShopwellServicesService(client, loginService, systemConfigService);

        clientMock.onGet(`services/categorized-permissions/MyCoolService`).reply(200, {
            permissions: {
                user: [
                    {
                        entity: 'admin_user',
                        operation: 'read',
                    },
                ],
            },
        });

        const categorizedPermissions = await shopwellServicesService.getCategorizedPermissions('MyCoolService');

        expect(categorizedPermissions).toEqual({
            permissions: {
                user: [
                    {
                        entity: 'admin_user',
                        operation: 'read',
                    },
                ],
            },
        });
    });
});
