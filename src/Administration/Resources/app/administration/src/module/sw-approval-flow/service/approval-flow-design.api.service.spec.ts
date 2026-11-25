/** @sw-package framework */
import ApprovalFlowDesignApiService from './approval-flow-design.api.service';
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';

describe('approval design operations', () => {
    it('discovers exact capabilities and diagnoses graphs without table CRUD methods', async () => {
        const catalog = {
            graphSchema: '{}',
            nodeTypes: [{ key: 'expense:review@1.0.0' }, { key: 'expense:review@2.0.0' }],
            businessTypes: [],
        };
        const get = jest.fn().mockResolvedValue({ data: catalog });
        const post = jest.fn().mockResolvedValue({ data: [] });
        const service = new ApprovalFlowDesignApiService(
            { get, post } as unknown as HttpClient,
            { getToken: () => 'token' } as LoginService,
        );

        expect(await service.catalog()).toEqual(catalog);
        expect(get).toHaveBeenCalledWith('/_action/approval-flow/design/catalog', expect.any(Object));
        expect(await service.diagnose('{"nodes":[]}')).toEqual([]);
        expect(post).toHaveBeenCalledWith(
            '/_action/approval-flow/design/diagnose',
            { graphJson: '{"nodes":[]}' },
            expect.any(Object),
        );
    });

    it('propagates diagnostic failures to the caller', async () => {
        const error = new Error('diagnostics offline');
        const service = new ApprovalFlowDesignApiService(
            { post: jest.fn().mockRejectedValue(error) } as unknown as HttpClient,
            { getToken: () => 'token' } as LoginService,
        );

        await expect(service.diagnose('{}')).rejects.toBe(error);
    });
});
