/** @sw-package fundamentals@framework */

type MenuNode = {
    id: string;
    label?: string;
    type: string;
    routeKey?: string | null;
    internalKey?: string | null;
    privilege?: string | null;
    active?: boolean;
    archived?: boolean;
    children: MenuNode[];
};
type Route = { internalKey: string; privilege: string | null };

/** @private */
export function menuLeafIds(nodes: MenuNode[]): string[] {
    return nodes.flatMap((node) => {
        if (node.active === false || node.archived || node.type === 'hidden') return [];
        return [...(['menu', 'link'].includes(node.type) ? [node.id] : []), ...menuLeafIds(node.children)];
    });
}

/** @private */
export function toggleMenuSelection(selected: string[], nodes: MenuNode[], checked: boolean): string[] {
    const ids = new Set(menuLeafIds(nodes));
    return checked ? [...new Set([...selected, ...ids])] : selected.filter((id) => !ids.has(id));
}

/** @private */
export function requiredMenuPrivileges(nodes: MenuNode[], routes: Route[]): string[] {
    return [
        ...new Set(
            nodes.flatMap((node): string[] => {
                const target = routes.find((route) => route.internalKey === (node.routeKey || node.internalKey));
                return [
                    ...[node.type === 'menu' ? target?.privilege : null, node.privilege].filter(
                        (value): value is string => !!value,
                    ),
                    ...requiredMenuPrivileges(node.children, routes),
                ];
            }),
        ),
    ];
}

/** @private */
export function ancestorMenuPrivileges(nodes: MenuNode[], selectedId: string, inherited: string[] = []): string[] {
    for (const node of nodes) {
        if (node.id === selectedId) return inherited;
        const restrictions = node.privilege ? [...inherited, node.privilege] : inherited;
        const found = ancestorMenuPrivileges(node.children, selectedId, restrictions);
        if (found.length) return found;
    }
    return [];
}

/** @private */
export function permissionKeysForMenus(nodes: MenuNode[], routes: Route[], keys: string[]): string[] {
    const required = requiredMenuPrivileges(nodes, routes);
    return keys.filter((key) => required.some((privilege) => privilege.slice(0, privilege.lastIndexOf('.')) === key));
}

type OperationMapping = {
    category: string;
    key: string | null;
    roles: Record<string, { resource?: string }>;
};

/** @private */
export function operationIdsForMenus(
    nodes: MenuNode[],
    routes: Route[],
    mappings: OperationMapping[],
    inherited: string[] = [],
): string[] {
    const required = new Set([...requiredMenuPrivileges(nodes, routes), ...inherited]);
    const resources = new Set([...required].map((id) => id.slice(0, id.lastIndexOf('.'))));
    return mappings.flatMap((mapping) =>
        mapping.category === 'additional_permissions'
            ? Object.entries(mapping.roles)
                  .filter(
                      ([name, role]) =>
                          required.has(`${mapping.key}.${name}`) || (role.resource && resources.has(role.resource)),
                  )
                  .map(([name]) => `${mapping.key}.${name}`)
            : [],
    );
}

/** @private */
export function operationMenuLabels(
    nodes: MenuNode[],
    routes: Route[],
    mappings: OperationMapping[],
): Record<string, string[]> {
    const labels: Record<string, string[]> = {};
    const visit = (items: MenuNode[], inherited: string[]) => {
        items.forEach((node) => {
            if (node.type === 'menu' || node.type === 'link') {
                operationIdsForMenus([{ ...node, children: [] }], routes, mappings, inherited).forEach((id) => {
                    labels[id] = [...new Set([...(labels[id] ?? []), node.label ?? node.id])];
                });
            }
            visit(node.children, node.privilege ? [...inherited, node.privilege] : inherited);
        });
    };
    visit(nodes, []);
    return labels;
}
