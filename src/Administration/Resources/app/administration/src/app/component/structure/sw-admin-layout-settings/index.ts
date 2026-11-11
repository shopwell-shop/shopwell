/**
 * @sw-package framework
 */
import useAdminLayout, { NAVIGATION_LAYOUTS, type NavigationLayout } from 'src/app/composables/use-admin-layout';
import template from './sw-admin-layout-settings.html.twig';
import './sw-admin-layout-settings.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    data() {
        return { layouts: NAVIGATION_LAYOUTS, isSaving: false, hasSaveError: false };
    },

    computed: {
        selectedLayout(): NavigationLayout {
            return useAdminLayout().layout.value;
        },
    },

    methods: {
        async selectLayout(value: NavigationLayout): Promise<void> {
            if (this.isSaving) return;
            this.isSaving = true;
            this.hasSaveError = false;
            try {
                await useAdminLayout().saveUserLayout(value);
            } catch {
                this.hasSaveError = true;
            } finally {
                this.isSaving = false;
            }
        },
    },
});
