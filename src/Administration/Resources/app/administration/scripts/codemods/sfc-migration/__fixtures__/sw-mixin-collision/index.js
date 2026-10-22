import template from './sw-mixin-collision.html.twig';
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
        // The module-level helper and the mixin member share a name, which only `this.` keeps apart
        // here — the composable's binding has to be renamed to stay apart from the helper.
        shortLabel() {
            return placeholder(this.customer, 'name', '');
        },

        fullLabel() {
            return this.placeholder(this.customer, 'name', 'no name');
        },
    },
};
