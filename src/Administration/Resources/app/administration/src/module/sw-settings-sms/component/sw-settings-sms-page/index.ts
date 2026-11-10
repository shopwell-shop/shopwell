/**
 * @sw-package framework
 */
import template from './sw-settings-sms-page.html.twig';

/**
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    props: {
        activeSection: { type: String, required: true },
        backRoute: { type: String, default: null },
    },
});
