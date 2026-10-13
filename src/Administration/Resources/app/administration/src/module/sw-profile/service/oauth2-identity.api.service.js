/**
 * @sw-package fundamentals@framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class OAuth2IdentityService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'oauth2IdentityService';
    }

    /**
     * The third party channels an administrator can log in with, each together with whether the
     * own account is already linked to that platform.
     *
     * The account is resolved from the access token on the server, the administration never sends
     * a user id of its own.
     *
     * @returns {Promise<Array>}
     */
    listBindings() {
        return this.httpClient
            .get('/_action/sso/oauth2-identities', { headers: this.getHeaders() })
            .then((response) => response.data.bindings || []);
    }

    /**
     * Removes the own binding of one channel.
     *
     * @returns {Promise<void>}
     */
    unbind(channel) {
        return this.httpClient.delete(`/_action/sso/oauth2-identities/${encodeURIComponent(channel)}`, {
            headers: this.getHeaders(),
        });
    }

    getHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}
