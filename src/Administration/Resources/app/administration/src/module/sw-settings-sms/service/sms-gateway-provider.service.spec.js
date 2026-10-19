/**
 * @sw-package framework
 */
import SmsGatewayProviderService from './sms-gateway-provider.service';

describe('src/module/sw-settings-sms/service/sms-gateway-provider.service', () => {
    function createService(providers, token = 'the-token') {
        const httpClient = {
            get: jest.fn(() => Promise.resolve({ data: { providers } })),
        };
        const loginService = {
            getToken: jest.fn(() => token),
        };

        return {
            httpClient,
            service: new SmsGatewayProviderService(httpClient, loginService),
        };
    }

    it('should ask the server for the providers with the admin token', async () => {
        const { service, httpClient } = createService([{ technicalName: 'aliyun' }]);

        await expect(service.list()).resolves.toEqual([{ technicalName: 'aliyun' }]);

        expect(httpClient.get).toHaveBeenCalledWith('/_action/sms/gateway-providers', {
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer the-token',
                'Content-Type': 'application/json',
            },
        });
    });

    it('should resolve with an empty list when the response carries no providers', async () => {
        const { service } = createService(undefined);

        await expect(service.list()).resolves.toEqual([]);
    });

    it('should answer the credential definitions of a provider unchanged', async () => {
        const providers = [
            {
                technicalName: 'aliyun',
                labelSnippet: 'sms.gateway.aliyun',
                supportedRegions: ['CN'],
                supportsTemplateMode: true,
                credentialDefinitions: [
                    { name: 'accessKeyId', type: 'string', required: true, labelSnippet: 'sms.gateway.aliyun.accessKeyId' },
                ],
            },
        ];
        const { service } = createService(providers);

        await expect(service.list()).resolves.toEqual(providers);
    });
});
