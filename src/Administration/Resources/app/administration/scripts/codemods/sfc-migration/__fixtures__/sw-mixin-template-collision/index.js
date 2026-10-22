import template from './sw-mixin-template-collision.html.twig';
import { placeholder } from './placeholder.helper';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopwell.Mixin.getByName('placeholder'),
    ],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    methods: {
        shortLabel() {
            return placeholder(this.customer, 'name', '');
        },
    },
};
