/**
 * @sw-package framework
 */
import type { HttpClient } from 'src/core/factory/http-client.types';

/** @private */
export interface OAuth2LoginChannel {
    technicalName: string;
    labelSnippet: string;
    icon: string | null;
    startUrl: string;
}

/** @private */
export default class OAuth2LoginService {
    readonly name = 'oauth2LoginService';

    constructor(private readonly httpClient: HttpClient) {}

    /** The channel list is public so it is available before login. */
    async listChannels(): Promise<OAuth2LoginChannel[]> {
        const response = await this.httpClient.get<{ channels?: OAuth2LoginChannel[] }>('/_info/oauth2-login-channels', {
            headers: { Accept: 'application/json' },
        });

        return response.data.channels ?? [];
    }
}
