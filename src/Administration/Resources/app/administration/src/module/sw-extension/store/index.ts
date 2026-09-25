import type { ShopwellClass } from 'src/core/shopwell';
import useSession from '../../../app/composables/use-session';
import './extensions.store';

let initialLoad = false;

/**
 * @sw-package checkout
 * @private
 */
export default function initState(Shopwell: ShopwellClass): void {
    Shopwell.Vue.watch(useSession().languageId, async () => {
        if (!Shopwell.Service('acl').can('system.plugin_maintain')) {
            return;
        }

        // Always on page load setAdminLocale will be called once. Catch it to not load refresh extensions
        if (!initialLoad) {
            initialLoad = true;
            return;
        }

        await Shopwell.Service('shopwellExtensionService').updateExtensionData(false);
    });
}
