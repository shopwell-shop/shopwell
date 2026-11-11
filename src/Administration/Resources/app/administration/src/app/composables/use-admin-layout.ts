/**
 * @sw-package framework
 */
import { readonly, ref, type Ref } from 'vue';

/** @private */
export const USER_NAVIGATION_LAYOUT_CONFIG_KEY = 'core.adminNavigationLayout';

/** @private */
export const NAVIGATION_LAYOUTS = ['sidebar', 'top', 'mixed'] as const;

/** @private */
export type NavigationLayout = (typeof NAVIGATION_LAYOUTS)[number];

const DEFAULT_NAVIGATION_LAYOUT: NavigationLayout = 'sidebar';

type UseAdminLayoutReturn = {
    layout: Readonly<Ref<NavigationLayout>>;
    loadUserLayout: () => Promise<void>;
    saveUserLayout: (layout: NavigationLayout) => Promise<void>;
};

const layout = ref<NavigationLayout>(DEFAULT_NAVIGATION_LAYOUT);
let layoutUserId: string | undefined;

function isNavigationLayout(value: unknown): value is NavigationLayout {
    return NAVIGATION_LAYOUTS.some((option) => option === value);
}

async function loadUserLayout(): Promise<void> {
    const userId = Shopwell.Store.get('session').currentUser?.id;

    if (layoutUserId !== userId) {
        layout.value = DEFAULT_NAVIGATION_LAYOUT;
        layoutUserId = userId;
    }

    if (!userId) {
        return;
    }

    const currentLayout = layout.value;
    const response = await Shopwell.Service('userConfigService').search([USER_NAVIGATION_LAYOUT_CONFIG_KEY]);

    if (!response || userId !== Shopwell.Store.get('session').currentUser?.id) {
        return;
    }

    // Keep a preference saved while this request was loading.
    if (layout.value !== currentLayout) {
        return;
    }

    const preference = response.data?.[USER_NAVIGATION_LAYOUT_CONFIG_KEY] as { layout?: unknown } | undefined;
    layout.value = isNavigationLayout(preference?.layout) ? preference.layout : DEFAULT_NAVIGATION_LAYOUT;
}

async function saveUserLayout(value: NavigationLayout): Promise<void> {
    const userId = Shopwell.Store.get('session').currentUser?.id;

    if (!userId || !isNavigationLayout(value)) {
        return;
    }

    await Shopwell.Service('userConfigService').upsert({
        [USER_NAVIGATION_LAYOUT_CONFIG_KEY]: { layout: value },
    });

    if (userId === Shopwell.Store.get('session').currentUser?.id) {
        layoutUserId = userId;
        layout.value = value;
    }
}

const layoutState: UseAdminLayoutReturn = {
    layout: readonly(layout),
    loadUserLayout,
    saveUserLayout,
};

/**
 * App-wide navigation preference for the current user.
 * @private
 */
export default function useAdminLayout(): UseAdminLayoutReturn {
    return layoutState;
}
