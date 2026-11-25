/** @sw-package framework */
import ApprovalFlowApiService from './approval-flow.api.service';
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';

describe('approval flow queries', () => {
    it('uses authenticated requests and preserves the selected queue and pagination', async () => {
        const list = { data: [], meta: { total: 0 } };
        const get = jest.fn().mockResolvedValue({ data: list });
        const service = new ApprovalFlowApiService(
            { get } as unknown as HttpClient,
            { getToken: () => 'approval-token' } as LoginService,
        );

        expect(await service.search({ view: 'done', limit: 25, offset: 50, status: 'completed' })).toEqual(list);

        expect(get).toHaveBeenCalledWith('/_action/approval-flow/requests', {
            params: { view: 'done', limit: 25, offset: 50, status: 'completed' },
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer approval-token',
                'Content-Type': 'application/json',
                'sw-language-id': Shopwell.Context.api.languageId,
            },
        });
        await service.detail('request-id');
        expect(get).toHaveBeenLastCalledWith('/_action/approval-flow/requests/request-id', {
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer approval-token',
                'Content-Type': 'application/json',
                'sw-language-id': Shopwell.Context.api.languageId,
            },
        });
    });

    it('preserves authorization and load failures for the page to present', async () => {
        const failure = new Error('Forbidden');
        const service = new ApprovalFlowApiService(
            { get: jest.fn().mockRejectedValue(failure) } as unknown as HttpClient,
            { getToken: () => 'approval-token' } as LoginService,
        );

        await expect(service.detail('request-id')).rejects.toBe(failure);
    });
});
