import template from './sw-mixin-composable.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopwell.Mixin.getByName('notification'),
        Shopwell.Mixin.getByName('salutation'),
    ],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    methods: {
        onGreet() {
            this.createNotificationSuccess({ message: 'greeted' });
        },
    },
};
