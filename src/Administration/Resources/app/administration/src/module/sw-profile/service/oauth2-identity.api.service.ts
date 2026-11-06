/**
 * @sw-package fundamentals@framework
 */
import type { HttpClient, HttpResponse } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';

/** @private */
export interface OAuth2IdentityBinding {
    technicalName: string;
    labelSnippet?: string;
    linked: boolean;
    displayName?: string | null;
}

/** @private */
export default class OAuth2IdentityService {
    readonly name = 'oauth2IdentityService';

    constructor(
        private readonly httpClient: HttpClient,
        private readonly loginService: Pick<LoginService, 'getToken'>,
    ) {}

    async listBindings(): Promise<OAuth2IdentityBinding[]> {
        const response = await this.httpClient.get<{ bindings?: OAuth2IdentityBinding[] }>(
            '/_action/sso/oauth2-identities',
            {
                headers: this.getHeaders(),
            },
        );

        return response.data.bindings ?? [];
    }

    unbind(channel: string): Promise<HttpResponse<void>> {
        return this.httpClient.delete(`/_action/sso/oauth2-identities/${encodeURIComponent(channel)}`, {
            headers: this.getHeaders(),
        });
    }

    getHeaders(): Record<string, string> {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}
