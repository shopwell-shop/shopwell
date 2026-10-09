import template from './sw-dashboard-index.html.twig';
import './sw-dashboard-index.scss';

/**
 * @sw-package after-sales
 *
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});
