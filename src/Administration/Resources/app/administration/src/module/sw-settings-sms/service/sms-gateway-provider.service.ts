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
 * @private
 */
export interface SmsGatewayProvider {
    technicalName: string;
    labelSnippet: string;
    supportedRegions: string[];
    supportsTemplateMode: boolean;
    credentialDefinitions: SmsCredentialDefinition[];
}

interface LoginService {
    getToken(): string | null;
}

/**
 * The providers the shop can send over.
 *
 * A provider is code and lives in the server, the administration only renders the credential form
 * it declares, so the list is asked for instead of being hard coded here - which is what lets an
 * extension add a provider without touching the administration.
 *
 * @private
 */
export default class SmsGatewayProviderService {
    constructor(
        private readonly httpClient: AxiosInstance,
        private readonly loginService: LoginService,
    ) {}

    async list(): Promise<SmsGatewayProvider[]> {
        const response = await this.httpClient.get<{ providers: SmsGatewayProvider[] }>('/_action/sms/gateway-providers', {
            headers: this.getHeaders(),
        });

        return response.data.providers ?? [];
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
