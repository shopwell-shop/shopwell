/** @sw-package framework */
import ApiService from 'src/core/service/api.service';
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';

/** @private */
export interface ApprovalNodeDescriptor {
    key: string;
    type: string;
    version: string;
    labelSnippet: string;
    configurationSchema: string;
    configurationSchemaId: string;
    configurationSchemaHash: string;
    draftConfigurationSchema: string;
    ports: Record<string, 'input' | 'output'>;
    allowedEffects: ('advance' | 'requestApproval' | 'complete' | 'openScope' | 'wait')[];
}

/** @private */
export interface ApprovalBusinessDescriptor {
    key: string;
    type: string;
    version: string;
    labelSnippet: string;
    formSchema: string;
    formSchemaId: string;
    formSchemaHash: string;
}

/** @private */
export interface ApprovalDesignCatalog {
    graphSchema: string;
    nodeTypes: ApprovalNodeDescriptor[];
    businessTypes: ApprovalBusinessDescriptor[];
}

/** @private */
export type ApprovalJsonValue =
    | string
    | number
    | boolean
    | null
    | ApprovalJsonValue[]
    | { [key: string]: ApprovalJsonValue };

/** @private */
export interface ApprovalDesignGraph {
    schemaVersion: string;
    id: string;
    meta: { name: string; editingMode: string; formSchemaRef: string };
    nodes: {
        id: string;
        type: string;
        typeVersion: string;
        config: Record<string, ApprovalJsonValue>;
        ports: { id: string; direction: 'input' | 'output'; channel: 'control'; cardinality: 'one' | 'many' }[];
        position: { x: number; y: number };
        extensions: Record<string, ApprovalJsonValue>;
    }[];
    connections: {
        id: string;
        source: string;
        target: string;
        sourcePort: string;
        targetPort: string;
        condition: { kind: string; [key: string]: ApprovalJsonValue };
        extensions: Record<string, ApprovalJsonValue>;
    }[];
    layout: { algorithm: string; nodes: { nodeId: string; width: number; height: number; collapsed: boolean }[] };
    extensions: Record<string, ApprovalJsonValue>;
}

/** @private */
export interface ApprovalGraphDiagnostic {
    category: 'structure' | 'draft' | 'capability';
    code: string;
    path: string;
    message: string;
}

/** @private */
export interface ApprovalDesignDraft {
    definitionId: string;
    revision: number;
    graphJson: string;
    diagnostics: ApprovalGraphDiagnostic[];
}

/** @private */
export interface ApprovalReleaseActivation {
    businessType: string;
    businessVersion: string;
    expectedRevision: number;
    expectedVersion: number;
}

/** @private */
export default class ApprovalFlowDesignApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService) {
        super(httpClient, loginService, 'approval-flow', 'application/json');
    }

    catalog(): Promise<ApprovalDesignCatalog> {
        return this.httpClient
            .get<ApprovalDesignCatalog>('/_action/approval-flow/design/catalog', { headers: this.getBasicHeaders() })
            .then((response) => response.data);
    }

    diagnose(graphJson: string): Promise<ApprovalGraphDiagnostic[]> {
        return this.httpClient
            .post<
                ApprovalGraphDiagnostic[]
            >('/_action/approval-flow/design/diagnose', { graphJson }, { headers: this.getBasicHeaders() })
            .then((response) => response.data);
    }

    createDraft(name: string, graphJson: string): Promise<ApprovalDesignDraft> {
        return this.httpClient
            .post<ApprovalDesignDraft>(
                '/_action/approval-flow/design/drafts',
                { name, graphJson },
                { headers: this.getBasicHeaders() },
            )
            .then((response) => response.data);
    }

    saveDraft(definitionId: string, expectedRevision: number, graphJson: string): Promise<ApprovalDesignDraft> {
        return this.httpClient
            .patch<ApprovalDesignDraft>(
                `/_action/approval-flow/design/drafts/${definitionId}`,
                { expectedRevision, graphJson },
                { headers: this.getBasicHeaders() },
            )
            .then((response) => response.data);
    }

    previewPublication(definitionId: string, expectedRevision: number): Promise<{ contentHash: string }> {
        return this.httpClient
            .post<{
                contentHash: string;
            }>(`/_action/approval-flow/design/drafts/${definitionId}/publication-preview`, { expectedRevision }, { headers: this.getBasicHeaders() })
            .then((response) => response.data);
    }

    publish(definitionId: string, expectedRevision: number, expectedHash: string): Promise<void> {
        return this.httpClient
            .post(
                `/_action/approval-flow/design/drafts/${definitionId}/publish`,
                { expectedRevision, expectedHash },
                { headers: this.getBasicHeaders() },
            )
            .then(() => undefined);
    }

    activateRelease(releaseId: string, activation: ApprovalReleaseActivation): Promise<void> {
        return this.httpClient
            .post(`/_action/approval-flow/design/releases/${releaseId}/activate`, activation, {
                headers: this.getBasicHeaders(),
            })
            .then(() => undefined);
    }
}
