/**
 * @sw-package fundamentals@framework
 */
import template from './sw-profile-index-oauth2-login.html.twig';

const { Component, Mixin } = Shopwell;

/**
 * The third party accounts of the signed in administrator: which platforms the shop offers for the
 * administration and whether the own account is linked to one of them.
 *
 * A link is created by logging in with the platform once, see the login controller of the platform,
 * so this view only shows the state and removes a link again.
 *
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['oauth2IdentityService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            bindings: [],
            isLoading: true,
            isUnbinding: false,
            removeTarget: null,
        };
    },

    created() {
        void this.loadBindings();
    },

    methods: {
        async loadBindings() {
            this.isLoading = true;

            try {
                this.bindings = await this.oauth2IdentityService.listBindings();
            } catch {
                this.bindings = [];
                this.createNotificationError({
                    message: this.$t('sw-profile.oauth2Login.notificationLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * The label of a channel comes from the snippet the platform named, a channel without one
         * falls back to its technical name.
         */
        channelLabel(binding) {
            return binding.labelSnippet && this.$te(binding.labelSnippet)
                ? this.$t(binding.labelSnippet)
                : binding.technicalName;
        },

        onOpenRemoveModal(binding) {
            this.removeTarget = binding;
        },

        onCloseRemoveModal() {
            this.removeTarget = null;
        },

        onRemoveModalChange(isOpen) {
            if (!isOpen) {
                this.onCloseRemoveModal();
            }
        },

        async onConfirmRemove() {
            const target = this.removeTarget;

            if (!target) {
                return;
            }

            this.isUnbinding = true;

            try {
                await this.oauth2IdentityService.unbind(target.technicalName);
                this.onCloseRemoveModal();

                this.createNotificationSuccess({
                    message: this.$t('sw-profile.oauth2Login.notificationUnbindSuccess'),
                });

                await this.loadBindings();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-profile.oauth2Login.notificationUnbindError'),
                });
            } finally {
                this.isUnbinding = false;
            }
        },
    },
});
