/**
 * @sw-package framework
 */
import SmsGatewayProviderService from './sms-gateway-provider.service';

describe('src/module/sw-settings-sms/service/sms-gateway-provider.service', () => {
    function createService(payload, token = 'the-token') {
        const httpClient = {
            get: jest.fn(() => Promise.resolve({ data: payload })),
        };
        const loginService = {
            getToken: jest.fn(() => token),
        };

        return {
            httpClient,
            service: new SmsGatewayProviderService(httpClient, loginService),
        };
    }

    const headers = {
        Accept: 'application/json',
        Authorization: 'Bearer the-token',
        'Content-Type': 'application/json',
    };

    it('should ask the server for the providers with the admin token', async () => {
        const { service, httpClient } = createService({ providers: [{ technicalName: 'aliyun' }] });

        await expect(service.list()).resolves.toEqual([
            expect.objectContaining({ technicalName: 'aliyun' }),
        ]);

        expect(httpClient.get).toHaveBeenCalledWith('/_action/sms/gateway-providers', { headers });
    });

    it('should resolve with an empty list when the response carries no providers', async () => {
        const { service } = createService({});

        await expect(service.list()).resolves.toEqual([]);
    });

    it('should answer the credential definitions of a provider unchanged', async () => {
        const { service } = createService({
            providers: [
                {
                    technicalName: 'aliyun',
                    labelSnippet: 'sms.gateway.aliyun',
                    supportedRegions: ['CN'],
                    supportsTemplateMode: true,
                    credentialDefinitions: [
                        {
                            name: 'accessKeyId',
                            type: 'string',
                            required: true,
                            labelSnippet: 'sms.gateway.aliyun.accessKeyId',
                        },
                    ],
                },
            ],
        });

        const providers = await service.list();

        expect(providers[0].credentialDefinitions).toEqual([
            { name: 'accessKeyId', type: 'string', required: true, labelSnippet: 'sms.gateway.aliyun.accessKeyId' },
        ]);
        expect(providers[0].supportedRegions).toEqual(['CN']);
        expect(providers[0].supportsTemplateMode).toBe(true);
    });

    it('should keep the audit block a provider declares', async () => {
        const audit = {
            requiresApproval: true,
            canSubmit: true,
            canReadStatus: true,
            submissionFields: [
                {
                    name: 'templateName',
                    type: 'string',
                    required: true,
                    labelSnippet: 'sms.submission.aliyun.templateName',
                    prefill: 'templateName',
                },
            ],
        };
        const { service } = createService({
            providers: [{ technicalName: 'aliyun', audit }],
        });

        const providers = await service.list();

        expect(providers[0].audit).toEqual(audit);
        expect(providers[0].audit.submissionFields[0].prefill).toBe('templateName');
    });

    it('should answer a provider without an audit block with capabilities it does not have', async () => {
        const { service } = createService({
            providers: [{ technicalName: 'legacy', supportsTemplateMode: false }],
        });

        const providers = await service.list();

        // an older payload must not break the caller: nothing is offered, nothing is assumed
        expect(providers[0].audit).toEqual({
            requiresApproval: false,
            canSubmit: false,
            canReadStatus: false,
            submissionFields: [],
        });
    });

    it('should not share the field list between providers that declare no audit block', async () => {
        const { service } = createService({
            providers: [{ technicalName: 'first' }, { technicalName: 'second' }],
        });

        const providers = await service.list();

        expect(providers[0].audit.submissionFields).not.toBe(providers[1].audit.submissionFields);
    });

    it('should answer the audit states of the payload with their snippet keys', async () => {
        const approvalStatuses = [
            { value: 'pending', labelSnippet: 'sms.approval.status.pending' },
            { value: 'approved', labelSnippet: 'sms.approval.status.approved' },
        ];
        const { service } = createService({ providers: [], approvalStatuses });

        await expect(service.approvalStatuses()).resolves.toEqual(approvalStatuses);
    });

    it('should answer no audit states when the payload carries none', async () => {
        const { service } = createService({ providers: [] });

        await expect(service.approvalStatuses()).resolves.toEqual([]);
    });

    it('should answer providers and audit states from one description', async () => {
        const { service } = createService({
            providers: [{ technicalName: 'aliyun' }],
            approvalStatuses: [{ value: 'pending', labelSnippet: 'sms.approval.status.pending' }],
        });

        const described = await service.describe();

        expect(described.providers).toHaveLength(1);
        expect(described.approvalStatuses).toHaveLength(1);
    });
});
