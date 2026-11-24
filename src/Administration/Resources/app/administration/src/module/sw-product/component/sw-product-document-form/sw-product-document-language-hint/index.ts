/**
 * @sw-package inventory
 */
import useDocumentDefaultLanguage from '../use-document-default-language';
import template from './sw-product-document-language-hint.html.twig';

const { Context, Utils } = Shopwell;

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    setup() {
        return useDocumentDefaultLanguage();
    },

    methods: {
        switchToDefaultLanguage(): void {
            Utils.EventBus.emit('on-change-language-clicked', Context.api.systemLanguageId);
        },
    },
});
