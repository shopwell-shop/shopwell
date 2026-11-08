/**
 * @sw-package fundamentals@framework
 */

/**
 * The labels of one language, as an administrator wrote them. A missing entry means the
 * entry still carries the label its module declares.
 */
type AdminMenuItemTranslation = {
    title: string | null;
    groupTitle: string | null;
};

type AdminMenuItem = {
    id: string;
    parentId: string | null;
    afterMenuItemId: string | null;
    internalKey: string | null;
    title: string | null;
    groupName: string | null;
    translations: Record<string, AdminMenuItemTranslation>;
    icon: string | null;
    color: string | null;
    type: string;
    linkTarget: string | null;
    privilege: string | null;
    active: boolean;
    sysLocked: boolean;
    archived: boolean;
    configOverridden: boolean;
    pluginId: string | null;
    pluginName: string | null;
    roleIds: Array<string>;
};

type RouteAllowlistEntry = {
    internalKey: string;
    title: string;
    type: string;
    parent: string | null;
    privilege: string | null;
};

type MenuTreeNode = AdminMenuItem & {
    children: Array<MenuTreeNode>;
};

/**
 * A node of the tree the server renders for the current user: already stripped of inactive,
 * archived, hidden and role-restricted entries, with entries the database does not know yet
 * synthesized from their core declaration (`virtual:` ids).
 */
type VisibleMenuNode = {
    id: string;
    parentId: string | null;
    afterMenuItemId: string | null;
    internalKey: string | null;
    type: string;
    title: string | null;
    icon: string | null;
    color: string | null;
    linkTarget: string | null;
    privilege: string | null;
    children: Array<VisibleMenuNode>;
};

type AdminMenuApi = {
    getTree: () => Promise<Array<AdminMenuItem>>;
    getVisibleTree: () => Promise<Array<VisibleMenuNode>>;
    getRouteAllowlist: () => Promise<Array<RouteAllowlistEntry>>;
    move: (move: { id: string; parentId: string | null; afterMenuItemId: string | null }) => Promise<unknown>;
    createItem: (payload: Record<string, unknown>) => Promise<string>;
    updateItem: (id: string, payload: Record<string, unknown>) => Promise<string>;
    deleteItem: (id: string) => Promise<unknown>;
};

/**
 * Orders one level by following the `after_menu_item_id` chain: the entry without a
 * predecessor leads, every other entry follows the sibling it is positioned after.
 *
 * This mirrors `VisibleMenuTreeBuilder::sortChain()` on the server, so the management tree
 * and the rendered navigation cannot disagree about the order. Stored data can be
 * inconsistent - an entry may reference a sibling outside the level, two entries may share
 * a predecessor - and both sides keep such entries visible instead of dropping them.
 */
function orderMenuSiblings(items: Array<AdminMenuItem>, parentId: string | null): Array<AdminMenuItem> {
    const level = items.filter((item) => item.parentId === parentId);
    const inLevel = new Set(level.map((item) => item.id));
    const followers = new Map<string, Array<AdminMenuItem>>();
    const heads: Array<AdminMenuItem> = [];

    level.forEach((item) => {
        const after = item.afterMenuItemId;

        if (after === null || !inLevel.has(after)) {
            heads.push(item);

            return;
        }

        followers.set(after, [...(followers.get(after) ?? []), item]);
    });

    const ordered: Array<AdminMenuItem> = [];
    const visited = new Set<string>();

    heads.forEach((head) => {
        let cursor: AdminMenuItem | undefined = head;

        while (cursor !== undefined && !visited.has(cursor.id)) {
            visited.add(cursor.id);
            ordered.push(cursor);
            cursor = followers.get(cursor.id)?.[0];
        }
    });

    level.forEach((item) => {
        if (!visited.has(item.id)) {
            ordered.push(item);
        }
    });

    return ordered;
}

/**
 * Builds the stored tree. Rows the server synthesizes for a core declaration without a
 * stored row (`virtual:` prefixed) are left out on purpose: they have no row to edit, so
 * offering a form for them would offer a save that cannot work.
 */
function buildMenuTree(items: Array<AdminMenuItem>): Array<MenuTreeNode> {
    const stored = items.filter((item) => !item.id.startsWith('virtual:'));

    const attach = (parentId: string | null): Array<MenuTreeNode> => {
        return orderMenuSiblings(stored, parentId).map((item) => {
            return { ...item, children: attach(item.id) };
        });
    };

    return attach(null);
}

/**
 * @sw-package fundamentals@framework
 *
 * The management side of the database driven navigation: the stored items, the internal
 * keys a menu item may point at, and the writes that keep the ordering chain intact.
 *
 * Every action reloads the tree afterwards. The server repairs the `after_menu_item_id`
 * chain of both levels affected by a move, and mirroring that repair locally would mean
 * keeping a second implementation of it in sync - the reload is one request and always
 * shows what the navigation will render.
 */
const adminMenuConfigStore = Shopwell.Store.register({
    id: 'adminMenuConfig',

    state: () => ({
        items: [] as Array<AdminMenuItem>,
        routeAllowlist: [] as Array<RouteAllowlistEntry>,
        visibleTree: [] as Array<VisibleMenuNode>,
        isVisibleLoaded: false,
        isVisibleLoadFailed: false,
        isLoading: false,
        isLoaded: false,
    }),

    getters: {
        tree(): Array<MenuTreeNode> {
            return buildMenuTree(this.items);
        },
        siblingsOf() {
            return (parentId: string | null): Array<AdminMenuItem> => {
                return orderMenuSiblings(this.items, parentId);
            };
        },
        itemById() {
            return (id: string): AdminMenuItem | null => {
                return this.items.find((item) => item.id === id) ?? null;
            };
        },
        isManagementEmpty(): boolean {
            return this.isLoaded && this.items.length === 0;
        },
    },

    actions: {
        api(): AdminMenuApi {
            return Shopwell.Service('adminMenuApiService') as unknown as AdminMenuApi;
        },

        /**
         * Loads the management tree and the route allowlist together: the form offers the
         * allowlist as the choices for what a menu item points at, so one without the other
         * is not usable.
         */
        async load() {
            this.isLoading = true;

            try {
                const [items, routeAllowlist] = await Promise.all([
                    this.api().getTree(),
                    this.api().getRouteAllowlist(),
                ]);

                this.items = items;
                this.routeAllowlist = routeAllowlist;
                this.isLoaded = true;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Loads the navigation tree of the current user.
         *
         * Failure is swallowed on purpose: the administration navigation is rendered from the
         * module declarations as well, so a failed request must degrade to that fallback
         * instead of leaving the user without a sidebar. `isVisibleLoadFailed` is what the
         * caller uses to tell "the database knows nothing" from "the database could not be
         * asked".
         */
        async loadVisibleTree(force = false) {
            if (this.isVisibleLoaded && !force) {
                return;
            }

            try {
                this.visibleTree = await this.api().getVisibleTree();
                this.isVisibleLoadFailed = false;
            } catch {
                this.visibleTree = [];
                this.isVisibleLoadFailed = true;
            } finally {
                this.isVisibleLoaded = true;
            }
        },

        async moveItem(move: { id: string; parentId: string | null; afterMenuItemId: string | null }) {
            await this.api().move(move);
            await this.refreshAfterWrite();
        },

        /**
         * Creates or updates one item and returns its id. A new item is written without a
         * position and placed by the caller with {@link moveItem}: the sibling chain is the
         * server's to repair, and a create plus a move is the same two requests either way.
         */
        async saveItem(payload: Record<string, unknown> & { id?: string }): Promise<string> {
            const { id, ...rest } = payload;

            const itemId = id ? await this.api().updateItem(id, rest) : await this.api().createItem(rest);

            await this.refreshAfterWrite();

            return itemId;
        },

        async deleteItem(id: string) {
            await this.api().deleteItem(id);
            await this.refreshAfterWrite();
        },

        /**
         * Reloads both trees after a write: the management tree to show the repaired ordering
         * chain, the visible tree so the sidebar reflects the change without a page reload.
         */
        async refreshAfterWrite() {
            await Promise.all([this.load(), this.loadVisibleTree(true)]);
        },
    },
});

/**
 * @private
 */
export type AdminMenuConfigStore = ReturnType<typeof adminMenuConfigStore>;

export default adminMenuConfigStore;
