/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import { DEFAULT_NAVIGATION_LAYOUT, NAVIGATION_LAYOUTS, type NavigationLayout } from 'src/app/composables/use-admin-layout';
import template from './sw-admin-layout-settings.html.twig';
import './sw-admin-layout-settings.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    emits: ['update:value'],

    props: {
        value: { type: String as PropType<NavigationLayout>, default: DEFAULT_NAVIGATION_LAYOUT },
    },

    data() {
        return { layouts: NAVIGATION_LAYOUTS };
    },
});
