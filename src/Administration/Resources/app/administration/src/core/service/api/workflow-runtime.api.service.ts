/** @sw-package framework */
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';
import type { RuntimeInstance, RuntimePage, RuntimeTask } from 'src/core/workflow/workflow-runtime.types';

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
        formData: Record<string, string>;
    }): Promise<{ data: { id: string } }> {
        return this.httpClient
            .post<{ data: { id: string } }>(`${base}/instance`, payload, { headers: this.getBasicHeaders() })
            .then((response) => response.data);
    }
    getInstance(id: string): Promise<RuntimeInstance> {
        return this.httpClient
            .get<{ data: RuntimeInstance }>(`${base}/instance/${id}`, { headers: this.getBasicHeaders() })
            .then((response) => response.data.data);
    }
    listTodo(params: PageQuery = {}): Promise<RuntimePage<RuntimeTask>> {
        return this.httpClient
            .get<RuntimePage<RuntimeTask>>(`${base}/task/todo`, { headers: this.getBasicHeaders(), params })
            .then((response) => response.data);
    }
    listDone(params: PageQuery = {}): Promise<RuntimePage<RuntimeTask>> {
        return this.httpClient
            .get<RuntimePage<RuntimeTask>>(`${base}/task/done`, { headers: this.getBasicHeaders(), params })
            .then((response) => response.data);
    }
    listInitiated(params: PageQuery = {}): Promise<RuntimePage<RuntimeInstance>> {
        return this.httpClient
            .get<RuntimePage<RuntimeInstance>>(`${base}/instance/mine`, { headers: this.getBasicHeaders(), params })
            .then((response) => response.data);
    }
    approveTask(id: string, comment: string): Promise<void> {
        return this.httpClient
            .post(`${base}/task/${id}/approve`, { comment }, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }
    rejectTask(id: string, comment: string): Promise<void> {
        return this.httpClient
            .post(`${base}/task/${id}/reject`, { comment }, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }
    cancelInstance(id: string, reason: string): Promise<void> {
        return this.httpClient
            .post(`${base}/instance/${id}/cancel`, { reason }, { headers: this.getBasicHeaders() })
            .then(() => undefined);
    }
}
