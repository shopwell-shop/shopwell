/**
 * @sw-package framework
 */
import { readonly, ref } from 'vue';

/** @private */
export const NAVIGATION_LAYOUTS = ['sidebar', 'top', 'mixed'] as const;

/** @private */
export type NavigationLayout = (typeof NAVIGATION_LAYOUTS)[number];

const CONFIG_KEY = 'core.adminNavigationLayout';
const layout = ref<NavigationLayout>('sidebar');
let revision = 0;

function currentUserId(): string | undefined {
    return Shopwell.Store.get('session').currentUser?.id;
}

function isLayout(value: unknown): value is NavigationLayout {
    return NAVIGATION_LAYOUTS.includes(value as NavigationLayout);
}

function cacheLayout(userId: string, value: NavigationLayout): void {
    try {
        localStorage.setItem(`sw-admin-layout:${userId}`, value);
    } catch {
        // Browser storage is optional; server-side preferences remain usable.
    }
}

function cachedLayout(userId: string): NavigationLayout {
    try {
        const cached = localStorage.getItem(`sw-admin-layout:${userId}`);
        return isLayout(cached) ? cached : 'sidebar';
    } catch {
        return 'sidebar';
    }
}

async function loadUserLayout(): Promise<void> {
    const request = ++revision;
    const userId = currentUserId();
    layout.value = userId ? cachedLayout(userId) : 'sidebar';
    if (!userId) return;

    try {
        const response = await Shopwell.Service('userConfigService').search([CONFIG_KEY]);
        if (!response || request !== revision || userId !== currentUserId()) return;

        const value = response.data?.[CONFIG_KEY] as { layout?: unknown } | undefined;
        layout.value = isLayout(value?.layout) ? value.layout : 'sidebar';
        cacheLayout(userId, layout.value);
    } catch {
        // Retain this account's cached choice when the server is unavailable.
    }
}

async function saveUserLayout(value: NavigationLayout): Promise<void> {
    const userId = currentUserId();
    if (!userId || !isLayout(value)) return;

    // A pending preference load must never overwrite an explicit selection.
    revision += 1;
    layout.value = value;
    cacheLayout(userId, value);

    await Shopwell.Service('userConfigService').upsert({ [CONFIG_KEY]: { layout: value } });
}

/**
 * Shared account preference; viewport adaptation never overwrites the user's choice.
 * @private
 */
export default function useAdminLayout() {
    return { layout: readonly(layout), loadUserLayout, saveUserLayout };
}
