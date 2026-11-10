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

/** @private */
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
    menuPrivilege?: string | null;
    inheritedMenuPrivileges?: string[];
    groupId?: string;
    groupTitle?: string;
    [key: string]: unknown;
};

/**
 * A node of the tree `GET /_action/admin-menu/visible` returns: already stripped of the
 * entries the current user may not see, with rows the database does not know yet synthesized
 * from their core declaration (`virtual:` prefixed ids).
 */
/** @private */
export type DatabaseMenuNode = {
    id: string;
    internalKey?: string | null;
    routeKey?: string | null;
    title?: string | null;
    icon?: string | null;
    color?: string | null;
    privilege?: string | null;
    type?: string | null;
    linkTarget?: string | null;
    groupName?: string | null;
    groupTitle?: string | null;
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

function navigationTarget(node: DatabaseMenuNode, source: NavigationEntryLike) {
    return node.type === 'link'
        ? { path: undefined, link: node.linkTarget }
        : { path: node.type === 'folder' ? undefined : source.path, link: undefined };
}

function navigationIdentity(source: NavigationEntryLike, node: DatabaseMenuNode, matched: Set<string>): string {
    return source.id && !matched.has(source.id) ? source.id : node.id;
}

function navigationMetadata(node: DatabaseMenuNode, source: NavigationEntryLike, translate: (key: string) => string) {
    return {
        label: node.title ? { label: translate(node.title), translated: true } : source.label,
        icon: node.icon ?? source.icon,
        color: node.color ?? source.color,
        privilege: source.privilege ?? node.privilege ?? undefined,
        menuPrivilege: node.privilege,
    };
}

function resolveNavigationLabel(key: string, translate: (key: string) => string): string {
    try {
        return translate(key) || key;
    } catch {
        return key;
    }
}

function findNavigationSource(
    node: DatabaseMenuNode,
    key: string,
    byId: Map<string, NavigationEntryLike>,
    byPath: Map<string | null | undefined, NavigationEntryLike>,
) {
    const targetKey = node.type === 'menu' ? (node.routeKey ?? key) : key;
    return byId.get(targetKey) ?? byPath.get(targetKey);
}

function canRenderNode(node: DatabaseMenuNode, source: NavigationEntryLike | undefined): boolean {
    return (
        source !== undefined ||
        node.type === 'folder' ||
        (node.type === 'link' && /^https?:\/\//i.test(node.linkTarget ?? ''))
    );
}

/**
 * @param declared flat list of the entries the modules declare
 * @param databaseTree nested tree as returned by the `visible` action, already ordered
 * @param translate resolves a stored title; a stored title is a snippet key for core entries
 *                  and plain text for entries an administrator created
 */
/** @private */
export function mergeDatabaseNavigation(
    declared: NavigationEntryLike[],
    databaseTree: DatabaseMenuNode[],
    translate: (key: string) => string = (key) => key,
    managedKeys: string[] = [],
): NavigationEntryLike[] {
    const declaredById = new Map<string, NavigationEntryLike>();

    declared.forEach((entry) => {
        if (entry.id !== undefined) declaredById.set(entry.id, entry);
    });

    const declaredByPath = new Map(declared.filter((entry) => entry.path).map((entry) => [entry.path, entry]));
    const managed = new Set(managedKeys);
    const matched = new Set<string>();
    const merged: NavigationEntryLike[] = [];

    const resolveLabel = (key: string) => resolveNavigationLabel(key, translate);

    const collectKeys = (nodes: DatabaseMenuNode[]): void => {
        nodes.forEach((node) => {
            matched.add(nodeKey(node));
            collectKeys(node.children ?? []);
        });
    };

    const positions = new Map<string | null, number>();
    const renderLevel = (
        nodes: DatabaseMenuNode[],
        parentId: string | null,
        inheritedMenuPrivileges: string[] = [],
        group: { groupId: string; groupTitle: string } | null = null,
    ): void => {
        nodes.forEach((node) => {
            const restrictions = [
                ...inheritedMenuPrivileges,
                ...[node.privilege].filter((value): value is string => !!value),
            ];
            if (node.type === 'group') {
                matched.add(nodeKey(node));
                renderLevel(node.children ?? [], parentId, restrictions, {
                    groupId: node.id,
                    groupTitle: resolveLabel(node.title ?? ''),
                });
                return;
            }
            const key = nodeKey(node);
            const source = findNavigationSource(node, key, declaredById, declaredByPath);
            if (!canRenderNode(node, source)) return;

            const declaration = source ?? {};
            const id = navigationIdentity(declaration, node, matched);
            matched.add(key);
            if (declaration.id) matched.add(declaration.id);

            const entry: NavigationEntryLike = {
                ...declaration,
                ...navigationMetadata(node, declaration, resolveLabel),
                id,
                ...navigationTarget(node, declaration),
                parent: parentId ?? undefined,
                position: (positions.get(parentId) ?? 0) + POSITION_STEP,
                inheritedMenuPrivileges,
                ...group,
            };

            positions.set(parentId, entry.position ?? 0);
            merged.push(entry);

            if (SELF_NAVIGATING_KEYS.has(key)) {
                // marked as matched all the same: the fallback pass below would otherwise put
                // every one of them back into the navigation as a level one entry
                collectKeys(node.children ?? []);

                return;
            }

            renderLevel(node.children ?? [], entry.id ?? null, restrictions);
        });
    };

    renderLevel(databaseTree, null);

    declared.forEach((entry) => {
        if (
            (entry.id !== undefined && (matched.has(entry.id) || managed.has(entry.id))) ||
            (entry.path && (managed.has(entry.path) || matched.has(entry.path)))
        ) {
            return;
        }

        merged.push(entry);
    });

    return merged;
}

/** Filters inherited restrictions before assigning headings, so an inaccessible first page cannot hide its heading. */
/** @private */
export function prepareNavigationGroups<T extends NavigationEntryLike & { children?: T[] }>(
    entries: T[],
    can: (privilege: string) => boolean,
): T[] {
    let previousGroup: unknown = null;
    return entries
        .filter(
            (entry) =>
                (!entry.privilege || can(entry.privilege)) &&
                (!entry.menuPrivilege || can(entry.menuPrivilege)) &&
                (entry.inheritedMenuPrivileges ?? []).every(can),
        )
        .map((entry) => {
            const groupStart = !!entry.groupId && entry.groupId !== previousGroup;
            previousGroup = entry.groupId;
            return { ...entry, groupStart, children: prepareNavigationGroups(entry.children ?? [], can) };
        });
}
