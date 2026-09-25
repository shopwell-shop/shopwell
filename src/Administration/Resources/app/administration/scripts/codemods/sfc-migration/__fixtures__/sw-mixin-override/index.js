import template from './sw-mixin-override.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopwell.Mixin.getByName('notification'),
    ],

    methods: {
        // Redefines a member the composable provides: the component's own version wins today, and
        // after the migration both would want the same binding name.
        createNotificationSuccess(config) {
            Shopwell.Store.get('notification').createNotification({ variant: 'success', ...config });
        },

        onSave() {
            this.createNotificationSuccess({ message: 'saved' });
        },
    },
};
