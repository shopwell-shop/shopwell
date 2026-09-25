import template from './sw-extension-app-module-error-page.html.twig';
import './sw-extension-app-module-error-page.scss';

/**
 * @sw-package checkout
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    methods: {
        goBack(): void {
            this.$router.go(-1);
        },
    },
});
