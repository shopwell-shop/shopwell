/**
 * @sw-package framework
 */
import type { AxiosError, AxiosInstance } from 'axios';

/**
 * The one answer shape the three steps of the audit share.
 *
 * `status` and `templateCode` are null on a failure, because the shop did not learn them: the row
 * keeps whatever it had and the administration reloads it. A failure is answered and not thrown, so
 * the caller has one branch for all three buttons, and the message the shop rendered is what an
 * operator reads.
 *
 * @private
 */
export interface SmsTemplateAuditAnswer {
    status: string | null;
    templateCode: string | null;
    errorCode: string | null;
    errorMessage: string | null;
}

/**
 * What a partial or unexpected answer is normalized to.
 *
 * @private
 */
const NO_ANSWER: SmsTemplateAuditAnswer = {
    status: null,
    templateCode: null,
    errorCode: null,
    errorMessage: null,
};

interface LoginService {
    getToken(): string | null;
}

/**
 * The three steps of the audit loop of one mapping row: report the template at the provider, read
 * its state back, and record a state an operator read at the console.
 *
 * One service for the three, because the server answers them with one shape - and because the
 * administration must not know which provider can do what: whether a step is offered is read from
 * the audit block of the row's provider, never decided here.
 *
 * @private
 */
export default class SmsTemplateAuditService {
    constructor(
        private readonly httpClient: AxiosInstance,
        private readonly loginService: LoginService,
    ) {}

    /**
     * Reports the template of the row at the provider account it points to.
     *
     * The values of the submission form travel under `fieldValues`, the shape the endpoint reads.
     */
    async submit(mappingId: string, fieldValues: Record<string, unknown>): Promise<SmsTemplateAuditAnswer> {
        return this.post(this.baseUrl(mappingId, 'submit'), { fieldValues });
    }

    /**
     * Reads the state of the reported template back, for a provider that can be asked for it.
     */
    async refresh(mappingId: string): Promise<SmsTemplateAuditAnswer> {
        return this.post(this.baseUrl(mappingId, 'refresh'), null);
    }

    /**
     * Records the state an operator read at the console of a provider that cannot be asked.
     *
     * The note is part of the audit trail the shop writes, so it is handed on even though it is
     * mandatory on the server - a missing one is reported as the violation it is.
     */
    async confirm(mappingId: string, status: string, note: string): Promise<SmsTemplateAuditAnswer> {
        return this.post(this.baseUrl(mappingId, 'confirm'), { status, note });
    }

    private baseUrl(mappingId: string, step: string): string {
        return `/_action/sms-template-audit/${mappingId}/${step}`;
    }

    /**
     * A refusal of the audit is a value, not an exception: the endpoint answers a failure with the
     * http status of the reason and the very same body, so the answer is handed on as the caller's
     * branch expects it. An error without a body is no answer at all - it is thrown, and the caller
     * shows the message of the transport.
     */
    private async post(url: string, body: Record<string, unknown> | null): Promise<SmsTemplateAuditAnswer> {
        try {
            const response = await this.httpClient.post<Partial<SmsTemplateAuditAnswer>>(url, body, {
                headers: this.getHeaders(),
            });

            return this.normalize(response.data);
        } catch (error) {
            const answer = (error as AxiosError<Partial<SmsTemplateAuditAnswer>>).response?.data;

            if (answer !== undefined && answer !== null && typeof answer === 'object') {
                return this.normalize(answer);
            }

            throw error;
        }
    }

    private normalize(answer: Partial<SmsTemplateAuditAnswer> | null | undefined): SmsTemplateAuditAnswer {
        if (!answer || typeof answer !== 'object') {
            return { ...NO_ANSWER };
        }

        return {
            status: typeof answer.status === 'string' ? answer.status : null,
            templateCode: typeof answer.templateCode === 'string' ? answer.templateCode : null,
            errorCode: typeof answer.errorCode === 'string' ? answer.errorCode : null,
            errorMessage: typeof answer.errorMessage === 'string' ? answer.errorMessage : null,
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
