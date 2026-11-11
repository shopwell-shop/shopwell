/**
 * @sw-package framework
 */
import type { RouteLocationRaw, RouteParamsRaw } from 'vue-router';
import type { NavigationEntryLike } from '../sw-admin-menu/merge-navigation.helper';

/** @private */
export type LayoutNavigationEntry = NavigationEntryLike & {
    path?: string;
    children: LayoutNavigationEntry[];
    link?: string;
};

/** @private */
export function rebaseNavigationLevels(entries: LayoutNavigationEntry[], level = 1): LayoutNavigationEntry[] {
    return entries.map((entry) => ({
        ...entry,
        ...(level === 1 && !entry.icon ? { icon: 'regular-circle-xxs' } : {}),
        level,
        children: rebaseNavigationLevels(entry.children, level + 1),
    }));
}

/** @private */
export function fitNavigationEntries(widths: number[], available: number, moreWidth: number, gap: number): number {
    let used = moreWidth;
    for (let index = 0; index < widths.length; index += 1) {
        used += widths[index] + gap;
        if (used > available) return index;
    }
    return widths.length;
}

/**
 * Match the existing menu item's route checks before splitting a branch into two navigation regions.
 * @private
 */
export function filterLayoutNavigation(
    entries: LayoutNavigationEntry[],
    canAccessRoute: (name: string) => boolean,
    canViewSettings: boolean,
): LayoutNavigationEntry[] {
    return entries.flatMap((entry) => {
        const children = filterLayoutNavigation(entry.children ?? [], canAccessRoute, canViewSettings);
        const path =
            entry.path && canAccessRoute(entry.path) && (entry.path !== 'sw.settings.index' || canViewSettings)
                ? entry.path
                : undefined;
        return path || entry.link || children.length ? [{ ...entry, path, children }] : [];
    });
}

/** @private */
export function firstNavigationTarget(entry: LayoutNavigationEntry): RouteLocationRaw | null {
    for (const child of entry.children) {
        const target = firstNavigationTarget(child);
        if (target) return target;
    }
    if (!entry.path) return null;
    return entry.params ? { name: entry.path, params: entry.params as RouteParamsRaw } : { name: entry.path };
}
