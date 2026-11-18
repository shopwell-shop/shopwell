/** @sw-package framework */
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';
import type {
    RuntimeInstance,
    RuntimeInstanceListItem,
    RuntimePage,
    RuntimeTodoTask,
    RuntimeDoneTask,
    RuntimeReceivedCopy,
    RuntimeTaskParticipation,
} from 'src/core/workflow/workflow-runtime.types';

const base = '/_action/workflow';
type PageQuery = { limit?: number; offset?: number };

/** @public */
export default class WorkflowRuntimeApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService) {
        super(httpClient, loginService, 'workflow');
        this.name = 'workflowRuntimeApiService';
    }

    startInstance(payload: {
        definitionId: string;
        title: string;
        formData: Record<string, string | string[]>;
    }): Promise<{ data: { id: string } }> {
        return this.httpClient
            .post<{ data: { id: string } }>(`${base}/instance`, payload, { headers: this.getBasicHeaders() })
            .then((response) => response.data);
    }
    returnTask(id: string, comment: string): Promise<void> {
        return this.httpClient
            .post(`${base}/task/${id}/return`, { comment }, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }

    remindInstance(id: string): Promise<void> {
        return this.httpClient
            .post(`${base}/instance/${id}/remind`, {}, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }

    resubmitInstance(
        id: string,
        payload: { round: number; formVersion?: number; formData: Record<string, string | string[]>; comment: string },
    ): Promise<void> {
        return this.httpClient
            .post(`${base}/instance/${id}/resubmit`, payload, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }

    getInstance(id: string, taskId?: string): Promise<RuntimeInstance> {
        return this.httpClient
            .get<{
                data: RuntimeInstance;
            }>(`${base}/instance/${id}`, { headers: this.getBasicHeaders(), ...(taskId ? { params: { taskId } } : {}) })
            .then((response) => response.data.data);
    }
    listTodo(params: PageQuery = {}): Promise<RuntimePage<RuntimeTodoTask>> {
        return this.httpClient
            .get<RuntimePage<RuntimeTodoTask>>(`${base}/task/todo`, { headers: this.getBasicHeaders(), params })
            .then((response) => response.data);
    }
    listDone(params: PageQuery = {}): Promise<RuntimePage<RuntimeDoneTask>> {
        return this.httpClient
            .get<RuntimePage<RuntimeDoneTask>>(`${base}/task/done`, { headers: this.getBasicHeaders(), params })
            .then((response) => response.data);
    }
    listInitiated(params: PageQuery = {}): Promise<RuntimePage<RuntimeInstanceListItem>> {
        return this.httpClient
            .get<RuntimePage<RuntimeInstanceListItem>>(`${base}/instance/mine`, { headers: this.getBasicHeaders(), params })
            .then((response) => response.data);
    }

    listCopies(params: PageQuery = {}): Promise<RuntimePage<RuntimeReceivedCopy>> {
        return this.httpClient
            .get<RuntimePage<RuntimeReceivedCopy>>(`${base}/copy`, { headers: this.getBasicHeaders(), params })
            .then((response) => response.data);
    }

    markCopyRead(id: string): Promise<void> {
        return this.httpClient
            .post(`${base}/copy/${id}/read`, {}, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }

    approveTask(
        id: string,
        comment: string,
        form?: { formData: Record<string, string | string[]>; formVersion: number },
    ): Promise<void> {
        return this.httpClient
            .post(`${base}/task/${id}/approve`, { comment, ...form }, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }
    rejectTask(id: string, comment: string): Promise<void> {
        return this.httpClient
            .post(`${base}/task/${id}/reject`, { comment }, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }
    participateTask(id: string, operation: RuntimeTaskParticipation): Promise<void> {
        return this.httpClient
            .post(`${base}/task/${id}/participation`, operation, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }

    executeTaskOperation(
        id: string,
        operation: { action: string; comment: string; payload?: Record<string, unknown> },
    ): Promise<void> {
        return this.httpClient
            .post(`${base}/task/${id}/operation`, operation, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }

    cancelInstance(id: string, reason: string): Promise<void> {
        return this.httpClient
            .post(`${base}/instance/${id}/cancel`, { reason }, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }
}
