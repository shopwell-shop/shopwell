import template from './sw-mixin-props-spread.html.twig';
import { sharedFieldProps } from './shared-field-props';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopwell.Mixin.getByName('validation'),
    ],

    props: {
        ...sharedFieldProps,

        value: {
            type: String,
            required: false,
            default: null,
        },
    },

    methods: {
        onCheck() {
            return this.validate(this.value);
        },
    },
};
