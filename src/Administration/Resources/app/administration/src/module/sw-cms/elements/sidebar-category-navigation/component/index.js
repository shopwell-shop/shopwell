import template from './sw-cms-el-category-navigation.html.twig';
import './sw-cms-el-category-navigation.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [Shopwell.Mixin.getByName('cms-element'), Shopwell.Mixin.getByName('placeholder')],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('category-navigation');
        },
    },
};
