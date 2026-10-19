/**
 * @sw-package framework
 */
import SmsTemplateAuditService from './sms-template-audit.service';

describe('src/module/sw-settings-sms/service/sms-template-audit.service', () => {
    const headers = {
        Accept: 'application/json',
        Authorization: 'Bearer the-token',
        'Content-Type': 'application/json',
    };

    function createService(answer = { status: 'pending', templateCode: 'SMS_1', errorCode: null, errorMessage: null }) {
        const httpClient = {
            post: jest.fn(() => Promise.resolve({ data: answer })),
        };
        const loginService = {
            getToken: jest.fn(() => 'the-token'),
        };

        return {
            httpClient,
            service: new SmsTemplateAuditService(httpClient, loginService),
        };
    }

    it('should report the template of a row with the values of the submission form', async () => {
        const { service, httpClient } = createService();

        const answer = await service.submit('mapping-1', { templateName: 'login', templateType: 'otp' });

        expect(httpClient.post).toHaveBeenCalledWith(
            '/_action/sms-template-audit/mapping-1/submit',
            { fieldValues: { templateName: 'login', templateType: 'otp' } },
            { headers },
        );
        expect(answer).toEqual({ status: 'pending', templateCode: 'SMS_1', errorCode: null, errorMessage: null });
    });

    it('should read the state of a row back without a body', async () => {
        const { service, httpClient } = createService({
            status: 'approved',
            templateCode: 'SMS_1',
            errorCode: null,
            errorMessage: null,
        });

        const answer = await service.refresh('mapping-1');

        expect(httpClient.post).toHaveBeenCalledWith(
            '/_action/sms-template-audit/mapping-1/refresh',
            null,
            { headers },
        );
        expect(answer.status).toBe('approved');
    });

    it('should record a state an operator read with its note', async () => {
        const { service, httpClient } = createService({
            status: 'suspended',
            templateCode: 'SMS_1',
            errorCode: null,
            errorMessage: null,
        });

        const answer = await service.confirm('mapping-1', 'suspended', 'read at the console');

        expect(httpClient.post).toHaveBeenCalledWith(
            '/_action/sms-template-audit/mapping-1/confirm',
            { status: 'suspended', note: 'read at the console' },
            { headers },
        );
        expect(answer.status).toBe('suspended');
    });

    it('should answer a refusal of the audit instead of throwing it', async () => {
        const { service, httpClient } = createService();

        httpClient.post.mockImplementation(() =>
            Promise.reject({
                response: {
                    status: 400,
                    data: {
                        status: null,
                        templateCode: null,
                        errorCode: 'SMS__TEMPLATE_SUBMISSION_UNSUPPORTED',
                        errorMessage: 'The provider cannot report a template.',
                    },
                },
            }),
        );

        const answer = await service.submit('mapping-1', {});

        expect(answer).toEqual({
            status: null,
            templateCode: null,
            errorCode: 'SMS__TEMPLATE_SUBMISSION_UNSUPPORTED',
            errorMessage: 'The provider cannot report a template.',
        });
    });

    it('should normalize an answer that leaves fields out', async () => {
        const { service } = createService({ status: 'unknown' });

        await expect(service.refresh('mapping-1')).resolves.toEqual({
            status: 'unknown',
            templateCode: null,
            errorCode: null,
            errorMessage: null,
        });
    });

    it('should throw an error that carries no answer of the audit', async () => {
        const { service, httpClient } = createService();

        httpClient.post.mockImplementation(() => Promise.reject(new Error('Network Error')));

        await expect(service.refresh('mapping-1')).rejects.toThrow('Network Error');
    });
});
