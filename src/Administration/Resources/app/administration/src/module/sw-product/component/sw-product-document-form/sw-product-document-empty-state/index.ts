/**
 * @sw-package inventory
 */
import template from './sw-product-document-empty-state.html.twig';
import './sw-product-document-empty-state.scss';

const { Store } = Shopwell;

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    computed: {
        isSystemDefaultLanguage(): boolean {
            return Store.get('context').isSystemDefaultLanguage;
        },
    },
});
