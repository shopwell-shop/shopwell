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
export type ApprovalTaskAction = ApprovalOutcome | 'return' | 'transfer' | 'addSign';
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
        status: 'waiting' | 'awaiting_approval' | 'awaiting_signal' | 'completed' | 'cancelled';
        outcome: ApprovalOutcome | null;
    }[];
    tasks: {
        id: string;
        executionId: string;
        assigneeId: string;
        status: 'pending' | 'blocked' | 'approved' | 'rejected' | 'cancelled';
        blockedTaskId: string | null;
    }[];
    addSigns: {
        taskId: string;
        addedTaskId: string;
        reason: string;
        addedAt: string | null;
        aggregateVersion: number;
    }[];
    transfers: {
        taskId: string;
        replacementTaskId: string;
        reason: string;
        transferredAt: string | null;
        aggregateVersion: number;
    }[];
    returns: {
        taskId: string;
        targetExecutionId: string;
        reason: string;
        returnedAt: string | null;
        aggregateVersion: number;
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
export interface ApprovalReturnTarget {
    executionId: string;
    nodeId: string;
    sequence: number;
}

/** @private */
export interface ApprovalReturn {
    commandId: string;
    expectedVersion: number;
    targetExecutionId: string;
    reason: string;
}

/** @private */
export interface ApprovalTaskAssignment {
    commandId: string;
    expectedVersion: number;
    assigneeId: string;
    reason: string;
}

/** @private */
export interface ApprovalTransferCandidate {
    id: string;
    name: string;
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
    term?: string;
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

    returnTargets(instanceId: string, taskId: string): Promise<ApprovalReturnTarget[]> {
        return this.httpClient
            .get<{
                data: ApprovalReturnTarget[];
            }>(`/_action/approval-flow/instances/${instanceId}/tasks/${taskId}/return-targets`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data.data);
    }

    returnTask(instanceId: string, taskId: string, command: ApprovalReturn): Promise<ApprovalDecisionReceipt> {
        return this.httpClient
            .post<ApprovalDecisionReceipt>(
                `/_action/approval-flow/instances/${instanceId}/tasks/${taskId}/return`,
                command,
                { headers: this.getBasicHeaders() },
            )
            .then((response) => response.data);
    }

    transferCandidates(instanceId: string, taskId: string, term: string): Promise<ApprovalTransferCandidate[]> {
        return this.httpClient
            .get<{
                data: ApprovalTransferCandidate[];
            }>(`/_action/approval-flow/instances/${instanceId}/tasks/${taskId}/transfer-candidates`, {
                params: { term },
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data.data);
    }

    transferTask(instanceId: string, taskId: string, command: ApprovalTaskAssignment): Promise<ApprovalDecisionReceipt> {
        return this.httpClient
            .post<ApprovalDecisionReceipt>(
                `/_action/approval-flow/instances/${instanceId}/tasks/${taskId}/transfer`,
                command,
                { headers: this.getBasicHeaders() },
            )
            .then((response) => response.data);
    }

    addSign(instanceId: string, taskId: string, command: ApprovalTaskAssignment): Promise<ApprovalDecisionReceipt> {
        return this.httpClient
            .post<ApprovalDecisionReceipt>(
                `/_action/approval-flow/instances/${instanceId}/tasks/${taskId}/add-sign`,
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
