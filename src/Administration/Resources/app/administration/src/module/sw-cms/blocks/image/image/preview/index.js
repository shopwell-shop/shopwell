import template from './sw-cms-preview-image.html.twig';
import './sw-cms-preview-image.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        assetFilter() {
            return Shopwell.Filter.getByName('asset');
        },
    },
};
