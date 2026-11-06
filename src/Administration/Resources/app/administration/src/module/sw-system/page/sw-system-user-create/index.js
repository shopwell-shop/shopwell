/**
 * @sw-package fundamentals@framework
 */
import template from './sw-system-user-create.html.twig';

const { Component } = Shopwell;
const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        ...mapPropertyErrors('user', ['password']),

        /**
         * A new user is filled in on a single page: the tab bar is removed in the template and every
         * section stays visible, so nothing can be missed behind a tab.
         */
        isCreationMode() {
            return true;
        },
    },

    methods: {
        loadUser() {
            if (this.user) {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                this.user = this.userRepository.create(this.context);
                this.user.active = true;
                resolve();
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.system.user.detail',
                params: { id: this.user.id },
            });
        },

        onSave() {
            if (!this.user.localeId) {
                this.user.localeId = this.currentUser.localeId;
            }
            this.$super('onSave');
        },
    },
};
