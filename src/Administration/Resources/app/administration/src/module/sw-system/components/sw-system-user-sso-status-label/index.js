import template from './sw-system-user-sso-status-label.html.twig';

/**
 * @private
 * @sw-package framework
 */
export default {
    name: 'sw-system-user-sso-status-label',
    template,

    props: {
        user: {
            type: Object,
            required: true,
        },
    },

    computed: {
        status() {
            if (this.user.active) {
                return 'active';
            }

            if (!this.user.active && this.user.email === this.user.name) {
                return 'invited';
            }

            return 'inactive';
        },

        statusText() {
            return this.$t(`sw-system.sso.user-listing.status-label.${this.status}`);
        },

        variant() {
            switch (this.status) {
                case 'active':
                    return 'positive';
                case 'invited':
                    return 'attention';
                default:
                    return 'critical';
            }
        },
    },
};
