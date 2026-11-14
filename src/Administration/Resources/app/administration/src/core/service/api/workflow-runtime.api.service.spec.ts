/** @sw-package framework */
import WorkflowRuntimeApiService from './workflow-runtime.api.service';
import type { HttpClient } from 'src/core/factory/http-client.types';
import initializeApiServices from 'src/app/init-pre/api-services.init';
import type { LoginService } from '../login.service';

/** @private */
Shopwell.Service().register('loginService', () => ({ getToken: () => 'runtime-token' }) as LoginService);

describe('core workflow runtime API', () => {
    afterEach(() => jest.restoreAllMocks());

    it('constructs and registers the discovered runtime API during bootstrap', () => {
        jest.spyOn(Shopwell._private, 'ApiServices').mockReturnValue([WorkflowRuntimeApiService] as never);
        expect(() => initializeApiServices()).not.toThrow();
        expect(Shopwell.Service('workflowRuntimeApiService')).toBeInstanceOf(WorkflowRuntimeApiService);
        expect(Shopwell.Application.getContainer('factory').apiService.getByName('workflowRuntimeApiService')).toBe(
            Shopwell.Service('workflowRuntimeApiService'),
        );
    });

    it('uses authenticated core endpoints for participant lists, details and decisions', async () => {
        const get = jest.fn().mockResolvedValue({ data: { data: [] } });
        const post = jest.fn().mockResolvedValue({ data: { data: { id: 'instance' } } });
        const service = new WorkflowRuntimeApiService(
            { get, post } as unknown as HttpClient,
            Shopwell.Service('loginService'),
        );
        await service.listTodo({ limit: 10, offset: 20 });
        await service.listDone();
        await service.listInitiated();
        await service.getInstance('instance');
        await service.startInstance({ definitionId: 'release', title: 'Request', formData: { amount: '10.50' } });
        await service.approveTask('task', 'Approved');
        await service.rejectTask('task', 'Incorrect');
        await service.cancelInstance('instance', 'Duplicate');
        expect(get.mock.calls.map((args: unknown[]) => args[0])).toEqual([
            '/_action/workflow/task/todo',
            '/_action/workflow/task/done',
            '/_action/workflow/instance/mine',
            '/_action/workflow/instance/instance',
        ]);
        expect(post).toHaveBeenCalledWith(
            '/_action/workflow/instance/instance/cancel',
            { reason: 'Duplicate' },
            expect.anything(),
        );
        expect(get).toHaveBeenCalledWith(
            '/_action/workflow/task/todo',
            expect.objectContaining({ params: { limit: 10, offset: 20 } }),
        );
        expect(JSON.stringify(get.mock.calls)).toContain('Bearer runtime-token');
    });
});
