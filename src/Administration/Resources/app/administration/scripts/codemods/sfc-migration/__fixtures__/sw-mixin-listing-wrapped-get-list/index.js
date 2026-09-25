import template from './sw-mixin-listing-wrapped-get-list.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Shopwell.Mixin.getByName('listing'),
    ],

    data() {
        return {
            items: null,
        };
    },

    methods: {
        // A decorated method: the codemod has no function body it could hand over.
        getList: Shopwell.Utils.debounce(function getList() {
            this.repositoryFactory
                .create('product')
                .search(new Shopwell.Data.Criteria(this.page, this.limit), Shopwell.Context.api)
                .then((result) => {
                    this.items = result;
                    this.total = result.total;
                });
        }, 750),
    },
};
