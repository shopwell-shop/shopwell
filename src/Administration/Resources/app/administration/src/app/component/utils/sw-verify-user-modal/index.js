/**
 * @sw-package framework
 */

import template from './sw-verify-user-modal.html.twig';

const { Mixin } = Shopwell;

/**
 * @private
 */
export default {
    template,

    inject: ['loginService'],

    emits: ['verified', 'close'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            confirmPassword: '',
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {},

        onSubmitConfirmPassword() {
            return this.loginService
                .verifyUserToken(this.confirmPassword)
                .then((verifiedToken) => {
                    const context = { ...Shopwell.Context.api };
                    context.authToken.access = verifiedToken;

                    const authObject = {
                        ...this.loginService.getBearerAuthentication(),
                        access: verifiedToken,
                    };

                    // Stored expiry is a timestamp; the setter expects a lifetime in seconds.
                    this.loginService.setBearerAuthentication({
                        ...authObject,
                        expiry: Math.max(0, (authObject.expiry - Date.now()) / 1000),
                    });

                    this.$emit('verified', context);
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-user.users.user-detail.passwordConfirmation.notificationPasswordErrorMessage'),
                    });
                })
                .finally(() => {
                    this.confirmPassword = '';
                    this.$emit('close');
                });
        },

        onCloseConfirmPasswordModal() {
            this.confirmPassword = '';
            this.$emit('close');
        },
    },
};
