/**
 * @sw-package framework
 */
import type { AxiosInstance } from 'axios';

/**
 * @private
 */
export type SmsCredentialType = 'string' | 'secret' | 'json' | 'bool';

/**
 * One field a provider needs to be usable, as the gateway factory declares it on the server.
 *
 * The type is the vocabulary of the factory, not one of the administration: `secret` says the value
 * is rendered as a password input, `json` that it holds a structure.
 *
 * @private
 */
export interface SmsCredentialDefinition {
    name: string;
    type: SmsCredentialType;
    required: boolean;
    labelSnippet: string;
}

/**
 * The form field types a submission form of a provider can be built from.
 *
 * @private
 */
export type SmsSubmissionFieldType = 'string' | 'text' | 'int' | 'select';

/**
 * A value the core already knows, so the operator confirms it instead of typing it again. The
 * provider names the slot, the administration fills in what it holds for the template.
 *
 * @private
 */
export type SmsSubmissionPrefill = 'templateName' | 'content' | 'scene';

/**
 * @private
 */
export interface SmsSubmissionFieldOption {
    value: string | number;
    labelSnippet: string;
}

/**
 * One field of the form a provider asks for before it takes a template. The shape is the one the
 * gateway factory declares on the server, so a provider an extension adds brings its own form.
 *
 * @private
 */
export interface SmsSubmissionField {
    name: string;
    type: SmsSubmissionFieldType;
    required: boolean;
    labelSnippet: string;
    prefill?: SmsSubmissionPrefill;
    default?: string | number | null;
    helpSnippet?: string;
    options?: SmsSubmissionFieldOption[];
}

/**
 * What a provider can do about the audit of a template.
 *
 * `requiresApproval` says a message needs an approved template, `canSubmit` that the template can be
 * reported over the api, `canReadStatus` that its state can be read back. The three are asked of the
 * provider instead of being derived from its name, which is what keeps the administration free of a
 * list of providers.
 *
 * @private
 */
export interface SmsGatewayAudit {
    requiresApproval: boolean;
    canSubmit: boolean;
    canReadStatus: boolean;
    submissionFields: SmsSubmissionField[];
}

/**
 * One state of the audit with the snippet key of its label, answered by the server so the
 * administration keeps no list of states of its own.
 *
 * @private
 */
export interface SmsApprovalStatus {
    value: string;
    labelSnippet: string;
}

/**
 * @private
 */
export interface SmsGatewayProvider {
    technicalName: string;
    labelSnippet: string;
    supportedRegions: string[];
    supportsTemplateMode: boolean;
    credentialDefinitions: SmsCredentialDefinition[];
    audit: SmsGatewayAudit;
}

/**
 * The whole answer of the provider endpoint: the providers with what they need, and the audit states
 * with their labels. Both are read from the same request because they describe the same feature.
 *
 * @private
 */
export interface SmsGatewayProviderPayload {
    providers: SmsGatewayProvider[];
    approvalStatuses: SmsApprovalStatus[];
}

/**
 * A provider of a payload that predates the audit block, or one an extension answered without it.
 *
 * @private
 */
type RawProvider = Omit<SmsGatewayProvider, 'audit'> & {
    audit?: Partial<SmsGatewayAudit> | null;
};

/**
 * @private
 */
interface RawPayload {
    providers?: RawProvider[] | null;
    approvalStatuses?: SmsApprovalStatus[] | null;
}

interface LoginService {
    getToken(): string | null;
}

/**
 * The audit block of a provider that declares none. An older server answer - or an extension that
 * did not follow the shape - must leave the screens usable, and a capability that is not declared is
 * one the administration must not offer.
 *
 * @private
 */
const NO_AUDIT: SmsGatewayAudit = {
    requiresApproval: false,
    canSubmit: false,
    canReadStatus: false,
    submissionFields: [],
};

/**
 * The providers the shop can send over.
 *
 * A provider is code and lives in the server, the administration only renders the credential form
 * and the submission form it declares, so both are asked for instead of being hard coded here -
 * which is what lets an extension add a provider without touching the administration.
 *
 * @private
 */
export default class SmsGatewayProviderService {
    constructor(
        private readonly httpClient: AxiosInstance,
        private readonly loginService: LoginService,
    ) {}

    async list(): Promise<SmsGatewayProvider[]> {
        const payload = await this.fetch();

        return payload.providers;
    }

    /**
     * The audit states with the snippet keys of their labels, so a state the server adds shows up
     * without a change here.
     */
    async approvalStatuses(): Promise<SmsApprovalStatus[]> {
        const payload = await this.fetch();

        return payload.approvalStatuses;
    }

    /**
     * The whole answer, normalized once: a caller that needs both the providers and the states reads
     * them in one request.
     */
    async describe(): Promise<SmsGatewayProviderPayload> {
        return this.fetch();
    }

    private async fetch(): Promise<SmsGatewayProviderPayload> {
        const response = await this.httpClient.get<RawPayload>('/_action/sms/gateway-providers', {
            headers: this.getHeaders(),
        });

        return {
            providers: (response.data.providers ?? []).map((provider) => this.normalizeProvider(provider)),
            approvalStatuses: Array.isArray(response.data.approvalStatuses) ? response.data.approvalStatuses : [],
        };
    }

    private normalizeProvider(provider: RawProvider): SmsGatewayProvider {
        return {
            technicalName: provider.technicalName,
            labelSnippet: provider.labelSnippet,
            supportedRegions: Array.isArray(provider.supportedRegions) ? provider.supportedRegions : [],
            supportsTemplateMode: provider.supportsTemplateMode === true,
            credentialDefinitions: Array.isArray(provider.credentialDefinitions) ? provider.credentialDefinitions : [],
            audit: SmsGatewayProviderService.normalizeAudit(provider.audit),
        };
    }

    private static normalizeAudit(audit: Partial<SmsGatewayAudit> | null | undefined): SmsGatewayAudit {
        if (!audit || typeof audit !== 'object') {
            // a fresh object per provider, so no two rows can share the list of fields
            return { ...NO_AUDIT, submissionFields: [] };
        }

        return {
            requiresApproval: audit.requiresApproval === true,
            canSubmit: audit.canSubmit === true,
            canReadStatus: audit.canReadStatus === true,
            submissionFields: Array.isArray(audit.submissionFields) ? audit.submissionFields : [],
        };
    }

    private getHeaders(): Record<string, string> {
        const token = this.loginService.getToken();

        return {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
        };
    }
}
