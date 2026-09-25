import template from './sw-cms-preview-youtube-video.html.twig';
import './sw-cms-preview-youtube-video.scss';

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
