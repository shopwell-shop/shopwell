/**
 * @sw-package fundamentals@framework
 */

/**
 * A node of the navigation tree the list page renders: the label is already resolved for
 * the displayed language, the children are the next level of the same shape.
 *
 * @private
 */
export type MenuRenderNode = {
    id: string;
    label: string;
    internalKey?: string | null;
    children: MenuRenderNode[];
};

/**
 * The tree a search shows: only the entries whose label or route id matches stay visible,
 * and every ancestor of a match stays so the hit remains nested in its context.
 *
 * @private
 */
export function filterTreeByTerm(nodes: MenuRenderNode[], term: string): MenuRenderNode[] {
    const needle = term.trim().toLowerCase();

    if (!needle) {
        return nodes;
    }

    const keep = (current: MenuRenderNode[]): MenuRenderNode[] => {
        return current.reduce<MenuRenderNode[]>((kept, node) => {
            const children = keep(node.children);

            if (children.length > 0 || matchesTerm(node, needle)) {
                kept.push({ ...node, children });
            }

            return kept;
        }, []);
    };

    return keep(nodes);
}

function matchesTerm(node: MenuRenderNode, needle: string): boolean {
    return node.label.toLowerCase().includes(needle) || (node.internalKey ?? '').toLowerCase().includes(needle);
}

/**
 * The identifiers of every node that has children - a search result is useless when its
 * parents are closed, so a filtered tree is shown fully expanded.
 *
 * @private
 */
export function collectBranchIds(nodes: MenuRenderNode[]): string[] {
    const ids: string[] = [];

    const walk = (current: MenuRenderNode[]): void => {
        current.forEach((node) => {
            if (node.children.length > 0) {
                ids.push(node.id);
                walk(node.children);
            }
        });
    };

    walk(nodes);

    return ids;
}

/**
 * The node with the given id, or `null` when the tree does not carry it - a search can hide
 * an entry from the panel without removing it from the navigation.
 *
 * @private
 */
export function findNode(nodes: MenuRenderNode[], id: string | null): MenuRenderNode | null {
    if (!id) {
        return null;
    }

    for (const node of nodes) {
        if (node.id === id) {
            return node;
        }

        const found = findNode(node.children, id);

        if (found) {
            return found;
        }
    }

    return null;
}

/**
 * The ids below the given node, collected over all levels. Deleting a node deletes these as
 * well, because the foreign key of the parent cascades.
 *
 * @private
 */
export function collectDescendantIds(nodes: MenuRenderNode[], id: string | null): string[] {
    const ids: string[] = [];

    const walk = (current: MenuRenderNode[]): void => {
        current.forEach((node) => {
            ids.push(node.id);
            walk(node.children);
        });
    };

    walk(findNode(nodes, id)?.children ?? []);

    return ids;
}

/**
 * The ids on the path from the root down to the given node, without the node itself. A
 * selection made from a search result is only visible when its parents are open.
 *
 * @private
 */
export function collectAncestorIds(nodes: MenuRenderNode[], id: string | null): string[] {
    const walk = (current: MenuRenderNode[], path: string[]): string[] | null => {
        for (const node of current) {
            if (node.id === id) {
                return path;
            }

            const found = walk(node.children, [...path, node.id]);

            if (found) {
                return found;
            }
        }

        return null;
    };

    return walk(nodes, []) ?? [];
}
