/** @sw-package framework */
import ApiService from 'src/core/service/api.service';
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';

/** @private */
export type ApprovalView = 'todo' | 'done' | 'started';
/** @private */
export type ApprovalStatus = 'draft' | 'running' | 'suspended' | 'completed' | 'terminated' | 'withdrawn';
/** @private */
export type ApprovalOutcome = 'approved' | 'rejected';
/** @private */
export type FormValue = string | number | boolean | null | FormValue[] | { [key: string]: FormValue };

/** @private */
export interface ApprovalRequest {
    id: string;
    title: string;
    businessType: string;
    businessId: string;
    submissionRound: number;
    instanceId: string;
    initiatorId: string;
    initiatorName: string | null;
    createdAt: string | null;
    status: ApprovalStatus;
    outcome: ApprovalOutcome | null;
    aggregateVersion: number;
}

/** @private */
export interface ApprovalDetail {
    request: ApprovalRequest;
    form: Record<string, FormValue>;
    formLabels: Record<string, { title: string; labelSnippet: string | null }>;
    participantNames: Record<string, string>;
    executions: {
        id: string;
        nodeId: string;
        sequence: number;
        status: 'waiting' | 'awaiting_approval' | 'completed';
        outcome: ApprovalOutcome | null;
    }[];
    tasks: {
        id: string;
        executionId: string;
        assigneeId: string;
        status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    }[];
    decisions: {
        taskId: string;
        nodeId: string;
        assigneeId: string;
        decision: ApprovalOutcome;
        reason: string;
        decidedAt: string | null;
        aggregateVersion: number;
    }[];
}

/** @private */
export interface ApprovalDecision {
    commandId: string;
    expectedVersion: number;
    outcome: ApprovalOutcome;
    reason: string;
}

/** @private */
export interface ApprovalDecisionReceipt {
    instanceId: string;
    commandId: string;
    status: 'running';
    aggregateVersion: number;
    outcome: null;
}

/** @private */
export interface ApprovalSearch {
    view: ApprovalView;
    limit: number;
    offset: number;
    status?: ApprovalStatus;
}

/** @private */
export interface ApprovalPage {
    data: ApprovalRequest[];
    meta: { total: number };
}

/** @private */
export default class ApprovalFlowApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService) {
        super(httpClient, loginService, 'approval-flow', 'application/json');
    }

    search(params: ApprovalSearch): Promise<ApprovalPage> {
        return this.httpClient
            .get<ApprovalPage>('/_action/approval-flow/requests', { params, headers: this.getBasicHeaders() })
            .then((response) => response.data);
    }

    decide(instanceId: string, taskId: string, command: ApprovalDecision): Promise<ApprovalDecisionReceipt> {
        return this.httpClient
            .post<ApprovalDecisionReceipt>(
                `/_action/approval-flow/instances/${instanceId}/tasks/${taskId}/decide`,
                command,
                { headers: this.getBasicHeaders() },
            )
            .then((response) => response.data);
    }

    detail(id: string): Promise<ApprovalDetail> {
        return this.httpClient
            .get<ApprovalDetail>(`/_action/approval-flow/requests/${id}`, { headers: this.getBasicHeaders() })
            .then((response) => response.data);
    }
}
