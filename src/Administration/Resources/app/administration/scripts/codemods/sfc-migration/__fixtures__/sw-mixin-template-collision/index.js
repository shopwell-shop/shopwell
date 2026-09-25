import template from './sw-mixin-template-collision.html.twig';
import { salutation } from './salutation.helper';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopwell.Mixin.getByName('salutation'),
    ],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    methods: {
        shortLabel() {
            return salutation(this.customer);
        },
    },
};
