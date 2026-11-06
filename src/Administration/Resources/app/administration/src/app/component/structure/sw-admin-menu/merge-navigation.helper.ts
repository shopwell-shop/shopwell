/**
 * @sw-package framework
 *
 * Merging the stored navigation (the `admin_menu_item` tree) with the navigation the modules
 * declare in code.
 *
 * The two sources answer different questions. The database owns what an administrator may
 * change - wording, icon, colour, order, visibility, role binding - while the code owns what
 * only code can know: the route behind an entry and the privilege guarding it. Neither can
 * replace the other, so the merge keeps the stored properties where a row exists and falls
 * back to the declaration where it does not.
 *
 * Entries the database carries nothing about (app modules, extensions, custom entities) are
 * passed through untouched: they are not manageable, so the stored tree has no opinion about
 * them and must not be able to hide them.
 */

export type NavigationEntryLike = {
    id?: string;
    label?: string | { label: string; translated?: boolean } | null;
    path?: string | null;
    position?: number | null;
    parent?: string | null;
    privilege?: string | null;
    icon?: string | null;
    color?: string | null;
    params?: Record<string, unknown>;
    [key: string]: unknown;
};

/**
 * A node of the tree `GET /_action/admin-menu/visible` returns: already stripped of the
 * entries the current user may not see, with rows the database does not know yet synthesized
 * from their core declaration (`virtual:` prefixed ids).
 */
export type DatabaseMenuNode = {
    id: string;
    internalKey?: string | null;
    title?: string | null;
    icon?: string | null;
    color?: string | null;
    privilege?: string | null;
    type?: string | null;
    children?: DatabaseMenuNode[];
};

/** Keeps the gap between two siblings the declarations use as well (10, 20, 30, …) */
const POSITION_STEP = 10;

/**
 * Entries whose page lists its own sections.
 *
 * The settings page renders every settings module inside itself, so the entries an
 * administrator files below the settings entry are managed there - and the sidebar opens the
 * page instead of a flyout with the same list a second time. The entries are not dropped:
 * they stay in the management tree, they are only not rendered as a second navigation.
 */
const SELF_NAVIGATING_KEYS = new Set(['sw-settings']);

function nodeKey(node: DatabaseMenuNode): string {
    return node.internalKey ?? node.id.replace(/^virtual:/, '');
}

/**
 * @param declared flat list of the entries the modules declare
 * @param databaseTree nested tree as returned by the `visible` action, already ordered
 * @param translate resolves a stored title; a stored title is a snippet key for core entries
 *                  and plain text for entries an administrator created
 */
export function mergeDatabaseNavigation(
    declared: NavigationEntryLike[],
    databaseTree: DatabaseMenuNode[],
    translate: (key: string) => string = (key) => key,
): NavigationEntryLike[] {
    const declaredById = new Map<string, NavigationEntryLike>();

    declared.forEach((entry) => {
        if (entry.id !== undefined) {
            declaredById.set(entry.id, entry);
        }
    });

    const matched = new Set<string>();
    const merged: NavigationEntryLike[] = [];

    /**
     * A stored title is resolved while the navigation renders, and a single unresolvable
     * entry must not take the whole sidebar down with it: the raw value is the better
     * fallback than no navigation at all.
     */
    const resolveLabel = (key: string): string => {
        try {
            return translate(key) || key;
        } catch {
            return key;
        }
    };

    const collectKeys = (nodes: DatabaseMenuNode[]): void => {
        nodes.forEach((node) => {
            matched.add(nodeKey(node));
            collectKeys(node.children ?? []);
        });
    };

    const renderLevel = (nodes: DatabaseMenuNode[], parentId: string | null): void => {
        nodes.forEach((node, index) => {
            const key = nodeKey(node);
            const source = declaredById.get(key);

            // A row the code does not declare has no route to open and no privilege to guard
            // it. Rendering it would produce a link to nowhere, so the subtree is dropped
            // instead - the same way the server drops an entry whose internal key is unknown.
            if (source === undefined) {
                return;
            }

            matched.add(key);

            const entry: NavigationEntryLike = {
                ...source,
                label: node.title ? { label: resolveLabel(node.title), translated: true } : source.label,
                icon: node.icon ?? source.icon,
                color: node.color ?? source.color,
                privilege: node.privilege ?? source.privilege,
                // `FlatTree` matches top-level entries against `undefined`, not against `null`:
                // a `null` parent puts every root and its whole subtree outside the tree.
                parent: parentId ?? undefined,
                position: (index + 1) * POSITION_STEP,
            };

            merged.push(entry);

            if (SELF_NAVIGATING_KEYS.has(key)) {
                // marked as matched all the same: the fallback pass below would otherwise put
                // every one of them back into the navigation as a level one entry
                collectKeys(node.children ?? []);

                return;
            }

            renderLevel(node.children ?? [], entry.id ?? null);
        });
    };

    renderLevel(databaseTree, null);

    declared.forEach((entry) => {
        if (entry.id !== undefined && matched.has(entry.id)) {
            return;
        }

        merged.push(entry);
    });

    return merged;
}
