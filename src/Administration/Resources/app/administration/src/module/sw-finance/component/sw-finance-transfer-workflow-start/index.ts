/** @sw-package framework */
import template from './sw-finance-transfer-workflow-start.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['acl'],
});
