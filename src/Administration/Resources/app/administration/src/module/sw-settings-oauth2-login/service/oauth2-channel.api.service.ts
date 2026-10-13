/**
 * @sw-package framework
 */
import type { HttpClient, HttpResponse } from 'src/core/factory/http-client.types';

/**
 * The props of one field of the form, in the vocabulary of `sw-form-field-renderer`: `label`,
 * `helpText` and every option label are inline snippets (locale => text), so a channel carries its
 * own translations and needs no snippet file of ours.
 *
 * @private
 */
export interface OAuth2ChannelFieldConfig {
    label?: string | Record<string, string>;
    helpText?: string | Record<string, string>;
    placeholder?: string;
    required?: boolean;
    default?: unknown;
    options?: Array<{ value: string; label: string | Record<string, string> }>;
}

/**
 * One field a channel declares itself. `target` says where the value belongs: the name of a column
 * of `oauth2_client`, or `settings` for the free json the channel keeps its own parameters in.
 *
 * @private
 */
export interface OAuth2ChannelField {
    name: string;
    type: string;
    target: string;
    config?: OAuth2ChannelFieldConfig;
}

/**
 * A channel as the server offers it. Which fields it has is decided by the channel, the
 * administration only renders them.
 *
 * @private
 */
export interface OAuth2Channel {
    technicalName: string;
    labelSnippet: string;
    formFields?: OAuth2ChannelField[];
}

interface LoginService {
    getToken(): string | null;
}

/**
 * The channels the shop can be configured with. They are code and live in the server, the
 * administration only renders them, so the list is asked for instead of being hard coded here -
 * which is what lets an extension ship a channel without touching the administration.
 *
 * @private
 */
export default class OAuth2ChannelService {
    constructor(
        private readonly httpClient: HttpClient,
        private readonly loginService: LoginService,
    ) {}

    async list(): Promise<OAuth2Channel[]> {
        const response: HttpResponse<{ channels?: OAuth2Channel[] }> = await this.httpClient.get<{
            channels?: OAuth2Channel[];
        }>('/_action/sso/oauth2-channels', { headers: this.getHeaders() });

        return response.data.channels ?? [];
    }

    private getHeaders(): Record<string, string> {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}
