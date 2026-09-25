import type { AxiosError } from 'axios';
import template from './sw-extension-my-extensions-account.html.twig';
import './sw-extension-my-extensions-account.scss';
import extensionErrorHandler from '../../service/extension-error-handler.service';
import type { MappedError } from '../../service/extension-error-handler.service';
import type { UserInfo } from '../../../../core/service/api/store.api.service';

const { Store, Mixin, Filter } = Shopwell;

/**
 * @sw-package checkout
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['systemConfigApiService', 'shopwellExtensionService', 'storeService'],

    mixins: [Mixin.getByName('notification')],

    data(): {
        isLoading: boolean;
        unsubscribeStore: (() => void) | null;
        form: {
            password: string;
            shopwellId: string;
        };
    } {
        return {
            isLoading: true,
            unsubscribeStore: null,
            form: {
                password: '',
                shopwellId: '',
            },
        };
    },

    computed: {
        userInfo(): UserInfo | null {
            return Store.get('shopwellExtensions').userInfo;
        },

        isLoggedIn(): boolean {
            return Store.get('shopwellExtensions').userInfo !== null;
        },

        assetFilter() {
            return Filter.getByName('asset');
        },
    },

    created() {
        this.createdComponent()
            .then(() => {
                this.unsubscribeStore = Store.get('shopwellExtensions').$onAction(({ name, args }) =>
                    this.showErrorNotification({ type: name, payload: args as MappedError[][] }),
                );
            })
            .catch(() => {});
    },

    beforeUnmount() {
        if (this.unsubscribeStore !== null) {
            this.unsubscribeStore();
        }
    },

    methods: {
        async createdComponent() {
            try {
                this.isLoading = true;
                await this.shopwellExtensionService.checkLogin();
            } finally {
                this.isLoading = false;
            }
        },

        async logout() {
            try {
                await this.storeService.logout();
                this.$emit('logout-success');
            } catch (errorResponse) {
                this.commitErrors(
                    errorResponse as AxiosError<{
                        errors: StoreApiException[];
                    }>,
                );
            } finally {
                await this.shopwellExtensionService.checkLogin();
            }
        },

        async login() {
            this.isLoading = true;

            try {
                await this.storeService.login(this.form.shopwellId, this.form.password);

                this.$emit('login-success');

                this.createNotificationSuccess({
                    message: this.$t('sw-extension.my-extensions.account.loginNotificationMessage'),
                });
            } catch (errorResponse) {
                this.commitErrors(
                    errorResponse as AxiosError<{
                        errors: StoreApiException[];
                    }>,
                );
            } finally {
                await this.shopwellExtensionService.checkLogin();
                this.isLoading = false;
            }
        },

        showErrorNotification({ type, payload }: { type: string; payload: MappedError[][] }) {
            if (type !== 'pluginErrorsMapped') {
                return;
            }

            payload.forEach((errors) => {
                errors.forEach((error) => {
                    if (error.parameters) {
                        this.showApiNotification(error);
                        return;
                    }

                    // Methods from mixins are not recognized
                    this.createNotificationError({
                        message: this.$t(error.message),
                    });
                });
            });
        },

        showApiNotification(error: MappedError) {
            // @ts-expect-error
            const docLink = this.$t('sw-extension.errors.messageToTheShopwellDocumentation', error.parameters, 0);

            // Methods from mixins are not recognized
            this.createNotificationError({
                title: error.title,
                message: `${error.message} ${docLink}`,
                autoClose: false,
            });
        },

        commitErrors(errorResponse: AxiosError<{ errors: StoreApiException[] }>): never {
            if (errorResponse.response) {
                const mappedErrors = extensionErrorHandler.mapErrors(errorResponse.response.data.errors);
                Shopwell.Store.get('shopwellExtensions').pluginErrorsMapped(mappedErrors);
            }

            throw errorResponse;
        },
    },
});
