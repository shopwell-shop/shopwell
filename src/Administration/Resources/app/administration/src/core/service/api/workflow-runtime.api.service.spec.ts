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
        await service.remindInstance('instance');
        expect(post).toHaveBeenCalledWith('/_action/workflow/instance/instance/remind', {}, expect.anything());
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
    it('preserves task context, form patch and version in authenticated requests', async () => {
        const get = jest.fn().mockResolvedValue({ data: { data: {} } });
        const post = jest.fn().mockResolvedValue({});
        const service = new WorkflowRuntimeApiService(
            { get, post } as unknown as HttpClient,
            Shopwell.Service('loginService'),
        );

        await service.getInstance('instance', 'task');
        await service.approveTask('task', 'Verified', { formData: { note: 'Checked' }, formVersion: 2 });

        expect(get).toHaveBeenCalledWith(
            '/_action/workflow/instance/instance',
            expect.objectContaining({ params: { taskId: 'task' } }),
        );
        expect(post).toHaveBeenCalledWith(
            '/_action/workflow/task/task/approve',
            { comment: 'Verified', formData: { note: 'Checked' }, formVersion: 2 },
            expect.anything(),
        );
    });
    it('uses the authenticated receipt endpoints without accepting a recipient identity', async () => {
        const get = jest.fn().mockResolvedValue({ data: { data: [], meta: { total: 0 } } });
        const post = jest.fn().mockResolvedValue({});
        const service = new WorkflowRuntimeApiService(
            { get, post } as unknown as HttpClient,
            Shopwell.Service('loginService'),
        );

        expect(await service.listCopies({ limit: 10, offset: 20 })).toEqual({ data: [], meta: { total: 0 } });
        await service.markCopyRead('receipt');

        expect(get).toHaveBeenCalledWith(
            '/_action/workflow/copy',
            expect.objectContaining({ params: { limit: 10, offset: 20 } }),
        );
        expect(post).toHaveBeenCalledWith('/_action/workflow/copy/receipt/read', {}, expect.anything());
        expect(JSON.stringify(post.mock.calls)).toContain('Bearer runtime-token');
    });
    it('submits participant actions and preserves request failures', async () => {
        const post = jest.fn().mockResolvedValue({});
        const service = new WorkflowRuntimeApiService({ post } as unknown as HttpClient, Shopwell.Service('loginService'));
        const operation = { action: 'transfer', targetUserId: 'recipient', comment: 'Specialist' };
        await service.participateTask('task', operation);
        expect(post).toHaveBeenCalledWith('/_action/workflow/task/task/participation', operation, expect.anything());
        post.mockRejectedValueOnce(new Error('stale task'));
        await expect(service.participateTask('task', operation)).rejects.toThrow('stale task');
    });
    it('submits a plugin operation without changing its input payload', async () => {
        const post = jest.fn().mockResolvedValue({});
        const service = new WorkflowRuntimeApiService({ post } as unknown as HttpClient, Shopwell.Service('loginService'));
        const operation = { action: 'example:consult', payload: { topic: 'Compliance' }, comment: 'Ask a specialist' };
        await service.executeTaskOperation('task', operation);
        expect(post).toHaveBeenCalledWith('/_action/workflow/task/task/operation', operation, expect.anything());
    });
    it('returns and resubmits with an expected instance round', async () => {
        const post = jest.fn().mockResolvedValue({});
        const service = new WorkflowRuntimeApiService({ post } as unknown as HttpClient, Shopwell.Service('loginService'));
        await service.returnTask('task', 'Please revise');
        await service.resubmitInstance('instance', { round: 1, formData: { reason: 'Revised' }, comment: 'Clarified' });
        expect(post).toHaveBeenCalledWith(
            '/_action/workflow/task/task/return',
            { comment: 'Please revise' },
            expect.anything(),
        );
        expect(post).toHaveBeenCalledWith(
            '/_action/workflow/instance/instance/resubmit',
            { round: 1, formData: { reason: 'Revised' }, comment: 'Clarified' },
            expect.anything(),
        );
    });
});
