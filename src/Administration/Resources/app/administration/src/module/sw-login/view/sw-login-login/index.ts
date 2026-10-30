/**
 * @sw-package framework
 */

import getErrorCode from 'src/core/data/error-codes/login.error-codes';
import template from './sw-login-login.html.twig';
import type { LoginConfig } from '../../../../core/service/login.service';

const { Component } = Shopwell;

/**
 * One third party login channel the server offers, see the channel endpoint of the platform.
 */
interface OAuth2LoginChannel {
    technicalName: string;
    labelSnippet: string;
    icon: string | null;
    startUrl: string;
}

interface LoginData {
    username: string;
    password: string;
    rememberMe: boolean;
    loginAlertMessage: string;
    loginErrorMessage: string;
    loginConfig: null | LoginConfig;
    loginConfigLoaded: boolean;
    ssoLoading: boolean;
    oauth2Channels: OAuth2LoginChannel[];
    oauth2ErrorMessage: string;
}

/**
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'loginService',
        'userService',
        'licenseViolationService',
        'oauth2LoginService',
    ],

    emits: [
        'is-loading',
        'is-not-loading',
        'login-success',
        'login-error',
        'config-loaded',
    ],

    data(): LoginData {
        return {
            username: '',
            password: '',
            rememberMe: false,
            loginAlertMessage: '',
            loginErrorMessage: '',
            loginConfig: null,
            loginConfigLoaded: false,
            ssoLoading: false,
            oauth2Channels: [],
            oauth2ErrorMessage: '',
        };
    },

    computed: {
        showLoginAlert() {
            return this.loginAlertMessage?.length >= 1;
        },
    },

    created(): void {
        void this.createdComponent();
    },

    methods: {
        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _reloadPage() {
            window.location.reload();
        },

        _navigateTo(url: string) {
            window.location.href = url;
        },

        async createdComponent() {
            if (!localStorage.getItem('sw-admin-locale')) {
                const localeFactory = Shopwell.Application.getContainer('factory').locale;

                await Shopwell.Store.get('session').setAdminLocale(localeFactory.getLastKnownLocale());
            }

            this.showOAuth2LoginError();

            try {
                this.loginConfig = await this.loginService.getLoginTemplateConfig();
            } catch {
                // Fall back to the password login when the SSO config cannot be loaded.
                this.loginConfig = { useDefault: true, url: '', passwordRecoveryEnabled: true };
            }

            this.$emit('config-loaded', this.loginConfig);

            if (!this.loginConfig.useDefault && this.loginConfig.url) {
                this.doSsoForwarding();
            }

            this.loginConfigLoaded = true;

            void this.loadOAuth2LoginChannels();
        },

        /**
         * The channels are asked for separately from the login config, a shop that only uses the
         * password login still gets the form when the endpoint is unavailable.
         */
        async loadOAuth2LoginChannels() {
            try {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
                this.oauth2Channels = (await this.oauth2LoginService.listChannels()) as OAuth2LoginChannel[];
            } catch {
                this.oauth2Channels = [];
            }
        },

        /**
         * A failed round trip sends the browser back here with the reason in a short lived cookie,
         * see `LoginResponseService::createOAuth2ErrorResponse()` of the platform.
         */
        showOAuth2LoginError() {
            const errorCode = this.readOAuth2LoginErrorCookie();

            if (!errorCode) {
                return;
            }

            this.oauth2ErrorMessage = this.$t(this.resolveOAuth2ErrorMessageKey(errorCode));
            this.clearOAuth2LoginErrorCookie();
        },

        resolveOAuth2ErrorMessageKey(errorCode: string) {
            switch (errorCode) {
                case 'SSO_OAUTH2__IDENTITY_NOT_BOUND':
                case 'SSO_OAUTH2__IDENTITY_ALREADY_BOUND':
                case 'SSO_OAUTH2__MISSING_VERIFIED_EMAIL':
                    return 'sw-login.index.oauth2ErrorNotLinked';
                case 'SSO_OAUTH2__CHANNEL_DISABLED':
                case 'SSO_OAUTH2__PROVIDER_NOT_AVAILABLE':
                case 'SSO_OAUTH2__PROVIDER_NOT_CONFIGURED':
                    return 'sw-login.index.oauth2ErrorNotAvailable';
                default:
                    return 'sw-login.index.oauth2ErrorFailed';
            }
        },

        readOAuth2LoginErrorCookie() {
            const match = document.cookie.match(/(?:^|;\s*)oauth2LoginError=([^;]*)/);

            return match === null ? null : decodeURIComponent(match[1]);
        },

        /**
         * The cookie is scoped to the login page of the administration, so it has to be removed
         * with the same path it was written with.
         */
        clearOAuth2LoginErrorCookie() {
            const { basePath, pathInfo } = Shopwell.Context.api;
            const paths = ['/', `${basePath ?? ''}${pathInfo ?? ''}`].filter(Boolean);

            paths.forEach((path) => {
                document.cookie = `oauth2LoginError=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=${path}`;
            });
        },

        /**
         * The label of a channel comes from the snippet the platform named, a channel without one
         * falls back to its name in the login snippet and finally to its technical name.
         */
        channelLabel(channel: OAuth2LoginChannel) {
            if (channel.labelSnippet && this.$te(channel.labelSnippet)) {
                return this.$t(channel.labelSnippet);
            }

            const labelSnippet = `sw-login.index.oauth2Channel.${channel.technicalName}`;
            const channelName = this.$te(labelSnippet) ? this.$t(labelSnippet) : channel.technicalName;

            return this.$t('sw-login.index.oauth2LoginWith', { channel: channelName });
        },

        loginWithOAuth2(channel: OAuth2LoginChannel) {
            if (!channel?.startUrl) {
                return;
            }

            this._navigateTo(channel.startUrl);
        },

        doSsoForwarding() {
            if (!this.loginConfig) {
                return;
            }

            this.ssoLoading = true;
            window.sessionStorage.setItem('redirectFromLogin', 'true');
            window.sessionStorage.setItem('sw-sso-session', 'true');
            this._navigateTo(this.loginConfig.url);
        },

        loginUserWithPassword() {
            this.$emit('is-loading');

            this.loginErrorMessage = '';
            this.loginService.setRememberMe(this.rememberMe);

            return this.loginService
                .loginByUsername(this.username, this.password)
                .then(() => {
                    void this.handleLoginSuccess();
                    this.$emit('is-not-loading');
                })
                .catch((response) => {
                    this.password = '';

                    this.handleLoginError(response);
                    this.$emit('is-not-loading');
                });
        },

        handleLoginSuccess() {
            this.password = '';

            this.$emit('login-success');

            const animationPromise = new Promise((resolve) => {
                setTimeout(resolve, 150);
            });

            if (this.licenseViolationService) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
                this.licenseViolationService.removeTimeFromLocalStorage(this.licenseViolationService.key.showViolationsKey);
            }

            return animationPromise.then(async () => {
                // @ts-expect-error
                this.$parent.isLoginSuccess = false;
                await this.forwardLogin();

                const shouldReload = sessionStorage.getItem('sw-login-should-reload');

                if (shouldReload) {
                    sessionStorage.removeItem('sw-login-should-reload');
                    // reload page to rebuild the administration with all dependencies
                    this._reloadPage();
                }
            });
        },

        async forwardLogin() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const previousRoute = JSON.parse(sessionStorage.getItem('sw-admin-previous-route') as string);
            sessionStorage.removeItem('sw-admin-previous-route');

            const firstRunWizard = Shopwell.Context.app.firstRunWizard;

            if (
                firstRunWizard &&
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                !this.$router?.currentRoute?.value?.name?.startsWith('sw.first.run.wizard') &&
                this.$router.hasRoute('sw.first.run.wizard.index')
            ) {
                void (await this.$router.push({ name: 'sw.first.run.wizard.index' }));
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (previousRoute?.fullPath) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-member-access
                void (await this.$router.push(previousRoute.fullPath));
                return;
            }

            void (await this.$router.push({ name: 'core' }));
        },

        handleLoginError(response: unknown) {
            this.password = '';

            this.$emit('login-error');
            setTimeout(() => {
                this.$emit('login-error');
            }, 500);

            this.showLoginErrorFromResponse(response);
        },

        showLoginErrorFromResponse(response: unknown) {
            // @ts-expect-error
            if (!response.response) {
                this.loginErrorMessage = this.$t('sw-login.index.messageGeneralError');
                return;
            }

            /* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access */
            // @ts-expect-error
            const url = response.config?.url as string | undefined;
            // @ts-expect-error
            let error = response.response.data?.errors;
            error = Array.isArray(error) ? error[0] : error;

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            if (parseInt(error?.status, 10) === 429) {
                const seconds = Number(error?.meta?.parameters?.seconds) || 10;
                this.loginAlertMessage = this.$t('sw-login.index.messageAuthThrottled', { seconds }, 0);

                setTimeout(() => {
                    this.loginAlertMessage = '';
                }, seconds * 1000);
                return;
            }

            const { message } = getErrorCode(parseInt(error?.code as string, 10)) as {
                message: string;
            };
            /* eslint-enable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access */

            if (message) {
                this.loginErrorMessage = this.$t(message);
                return;
            }

            this.loginErrorMessage = url
                ? this.$t('sw-login.index.messageGeneralRequestError', { url })
                : this.$t('sw-login.index.messageGeneralError');
        },
    },
});
