/**
 * @sw-package framework
 */
import type { AppModuleDefinition } from 'src/core/service/api/app-modules.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface ShopwellAppsState {
    apps: AppModuleDefinition[];
    appsLoaded: boolean;
    selectedIds: string[];
}

const shopwellApps = Shopwell.Store.register({
    id: 'shopwellApps',

    state: (): {
        apps: AppModuleDefinition[];
        appsLoaded: boolean;
        selectedIds: string[];
    } => ({
        apps: [],
        /**
         * Whether `apps` reflects a finished fetch — an empty list is ambiguous otherwise
         */
        appsLoaded: false,
        selectedIds: [],
    }),
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ShopwellApps = ReturnType<typeof shopwellApps>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default shopwellApps;
