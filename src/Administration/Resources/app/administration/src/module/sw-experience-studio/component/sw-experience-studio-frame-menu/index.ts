import template from './sw-experience-studio-frame-menu.html.twig';
import './sw-experience-studio-frame-menu.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    props: {
        label: {
            type: String,
            required: true,
        },
    },

    emits: ['open-another', 'translate'],

    data() {
        return {
            popoverClass: `experience-studio-frame-menu-${Shopwell.Utils.createId()}-entity-results`,
        };
    },
});
