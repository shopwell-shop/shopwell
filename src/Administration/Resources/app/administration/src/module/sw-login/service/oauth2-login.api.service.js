/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class OAuth2LoginService {
    constructor(httpClient) {
        this.httpClient = httpClient;
        this.name = 'oauth2LoginService';
    }

    /**
     * The third party channels an administrator can log in with, each together with the url that
     * starts the round trip to the platform.
     *
     * The endpoint is public on purpose: the login page asks for the channels before anybody is
     * logged in, so no access token is sent.
     *
     * @returns {Promise<Array>}
     */
    listChannels() {
        return this.httpClient
            .get('/_info/oauth2-login-channels', { headers: { Accept: 'application/json' } })
            .then((response) => response.data.channels || []);
    }
}
