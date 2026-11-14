/** @sw-package framework */
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { WorkflowDocument, WorkflowNodeTypeSpecification, WorkflowViolation } from 'src/core/workflow/workflow.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/** @private */
export interface WorkflowDraft {
    id: string;
    name: string;
    revision: number;
    document: WorkflowDocument;
}

/** @public */
export interface WorkflowPublisher {
    name: string;
    labelKey: string;
    canPublish: boolean;
}

/** @public */
export interface WorkflowRelease {
    id: string;
    draftId: string;
    revision: number;
    name: string;
    publisher: string;
    createdAt: string;
    requestAction?: string;
}

/** @private */
export default class WorkflowApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService) {
        super(httpClient, loginService, 'workflow');
        this.name = 'workflowService';
    }

    async getPublishers(): Promise<WorkflowPublisher[]> {
        const response = await this.httpClient.get<{ data: WorkflowPublisher[] }>('/_action/workflow/publishers', {
            headers: this.getBasicHeaders(),
        });
        return ApiService.handleResponse<{ data: WorkflowPublisher[] }>(response).data;
    }

    async publishDraft(id: string, expectedRevision: number, publisher: string): Promise<WorkflowRelease> {
        const response = await this.httpClient.post<WorkflowRelease>(
            `/_action/workflow/draft/${id}/publish`,
            { expectedRevision, publisher },
            { headers: this.getBasicHeaders() },
        );
        return ApiService.handleResponse<WorkflowRelease>(response);
    }

    async getReleases(
        params: { draftId?: string; limit?: number; offset?: number } = {},
    ): Promise<{ data: WorkflowRelease[]; total: number }> {
        const response = await this.httpClient.get<{ data: WorkflowRelease[]; total: number }>(
            '/_action/workflow/releases',
            {
                params: { limit: 25, offset: 0, ...params },
                headers: this.getBasicHeaders(),
            },
        );
        return ApiService.handleResponse<{ data: WorkflowRelease[]; total: number }>(response);
    }

    async getRequestCatalogue(
        params: { limit?: number; offset?: number } = {},
    ): Promise<{ data: WorkflowRelease[]; total: number }> {
        const response = await this.httpClient.get<{ data: WorkflowRelease[]; total: number }>(
            '/_action/workflow/request-catalogue',
            { params, headers: this.getBasicHeaders() },
        );
        return ApiService.handleResponse<{ data: WorkflowRelease[]; total: number }>(response);
    }

    async getFormOptions(source: string): Promise<{ value: string; label: string }[]> {
        const response = await this.httpClient.get<{ data: { value: string; label: string }[] }>(
            `/_action/workflow/form-options/${encodeURIComponent(source)}`,
            { headers: this.getBasicHeaders() },
        );
        return ApiService.handleResponse<{ data: { value: string; label: string }[] }>(response).data;
    }

    async getRelease(id: string): Promise<WorkflowRelease & { document: WorkflowDocument }> {
        const response = await this.httpClient.get<WorkflowRelease & { document: WorkflowDocument }>(
            `/_action/workflow/release/${id}`,
            {
                headers: this.getBasicHeaders(),
            },
        );
        return ApiService.handleResponse<WorkflowRelease & { document: WorkflowDocument }>(response);
    }

    async getDraft(id: string): Promise<WorkflowDraft> {
        const response = await this.httpClient.get<WorkflowDraft>(`/_action/workflow/draft/${id}`, {
            headers: this.getBasicHeaders(),
        });
        return ApiService.handleResponse<WorkflowDraft>(response);
    }

    async createDraft(id: string, name: string, document: WorkflowDocument): Promise<WorkflowDraft> {
        const response = await this.httpClient.post<WorkflowDraft>(
            '/_action/workflow/draft',
            { id, name, document },
            { headers: this.getBasicHeaders() },
        );
        return ApiService.handleResponse<WorkflowDraft>(response);
    }

    async updateDraft(
        id: string,
        name: string,
        document: WorkflowDocument,
        expectedRevision: number,
    ): Promise<WorkflowDraft> {
        const response = await this.httpClient.put<WorkflowDraft>(
            `/_action/workflow/draft/${id}`,
            { name, document, expectedRevision },
            { headers: this.getBasicHeaders() },
        );
        return ApiService.handleResponse<WorkflowDraft>(response);
    }

    async deleteDraft(id: string, expectedRevision: number): Promise<void> {
        await this.httpClient.delete(`/_action/workflow/draft/${id}`, {
            data: { expectedRevision },
            headers: this.getBasicHeaders(),
        });
    }

    async getNodeTypes(): Promise<WorkflowNodeTypeSpecification[]> {
        const response = await this.httpClient.get<{ types: WorkflowNodeTypeSpecification[] }>(
            '/_info/workflow-node-types.json',
            { headers: this.getBasicHeaders() },
        );
        return ApiService.handleResponse<{ types: WorkflowNodeTypeSpecification[] }>(response).types;
    }

    async diagnose(document: WorkflowDocument): Promise<WorkflowViolation[]> {
        const response = await this.httpClient.post<{ violations: WorkflowViolation[] }>(
            '/_action/workflow/diagnose',
            document,
            { headers: this.getBasicHeaders() },
        );
        return ApiService.handleResponse<{ violations: WorkflowViolation[] }>(response).violations;
    }
}
