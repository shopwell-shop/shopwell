import template from './sw-mixin-callback.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopwell.Mixin.getByName('media-grid-listener'),
    ],

    props: {
        items: {
            type: Array,
            required: true,
        },
    },

    computed: {
        // The member the mixin expected its host to provide.
        selectableItems() {
            return this.items;
        },
    },

    methods: {
        onReset() {
            this.selectedItems = [];
        },
    },
};
