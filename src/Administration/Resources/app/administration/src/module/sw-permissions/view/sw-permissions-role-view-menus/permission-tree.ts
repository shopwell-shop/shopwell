/** @sw-package fundamentals@framework */
import type PrivilegesService from 'src/app/service/privileges.service';
import type { MenuTreeNode, RouteAllowlistEntry } from 'src/app/store/admin-menu-config.store';
import { menuLeafIds, operationIdsForMenus, permissionKeysForMenus, requiredMenuPrivileges } from './role-menu-permissions';

/** @private */
export type PermissionNode = Pick<
    MenuTreeNode,
    'id' | 'type' | 'privilege' | 'routeKey' | 'internalKey' | 'active' | 'archived'
> & {
    label: string;
    children: PermissionNode[];
};
/** @private */
export type PermissionRow = {
    id: string;
    label: string;
    children: PermissionRow[];
    menu: PermissionNode | null;
    ownerMenuId?: string;
    operations: { id: string; label: string; menuIndependent?: boolean }[];
};

/** Resolve the registered graph without converting opaque, independently stored privileges. @private */
export function dependencyClosure(selected: string[], privileges: PrivilegesService): string[] {
    const result = new Set<string>();
    const visit = (id: string) => {
        if (result.has(id)) return;
        result.add(id);
        (privileges.getPrivilegeRole(id)?.dependencies ?? []).forEach(visit);
    };
    selected.forEach(visit);
    return [...result];
}

/** Keep one root even for cyclic mappings; generated dependencies are not independent selections. @private */
export function independentPrivileges(selected: string[], privileges: PrivilegesService): string[] {
    let roots = [...new Set(selected)];
    selected.forEach((id) => {
        const remaining = roots.filter((root) => root !== id);
        if (dependencyClosure(remaining, privileges).includes(id)) roots = remaining;
    });
    return roots;
}

/** Selected pages supply access prerequisites, including restrictions inherited from folders. @private */
export function selectedMenuRequirements(
    nodes: PermissionNode[],
    menuIds: string[],
    routes: RouteAllowlistEntry[],
): string[] {
    const result = new Set<string>();
    const visit = (items: PermissionNode[], inherited: string[]) => {
        items.forEach((item) => {
            if (!item.active || item.archived || item.type === 'hidden') return;
            const required = [...inherited, ...requiredMenuPrivileges([{ ...item, children: [] }], routes)];
            if (menuIds.includes(item.id)) required.forEach((id) => result.add(id));
            visit(item.children, required);
        });
    };
    visit(nodes, []);
    return [...result];
}

function resourceOperations(
    resource: string,
    mappings: ReturnType<PrivilegesService['getPrivilegesMappings']>,
    translate: (key: string) => string,
    qualified = false,
) {
    const roles = mappings.find((mapping) => mapping.category === 'permissions' && mapping.key === resource)?.roles ?? {};
    return Object.keys(roles)
        .sort((a, b) => {
            const order = [
                'viewer',
                'creator',
                'editor',
                'deleter',
            ];
            return (order.indexOf(a) === -1 ? 4 : order.indexOf(a)) - (order.indexOf(b) === -1 ? 4 : order.indexOf(b));
        })
        .map((role) => {
            const id = `${resource}.${role}`;
            const label = translate(
                role === 'viewer' || role === 'creator'
                    ? `sw-permissions.roles.assignment.${role}`
                    : `sw-privileges.roles.${role}`,
            );
            return {
                id,
                label: qualified ? `${translate(`sw-privileges.permissions.${resource}.label`)} · ${label}` : label,
            };
        });
}

type Mapping = ReturnType<PrivilegesService['getPrivilegesMappings']>[number];
type ResourceMapping = Mapping & { key: string };

function permissionCatalog(privileges: PrivilegesService, translate: (key: string) => string) {
    const mappings = privileges.getPrivilegesMappings();
    const resources = mappings.filter(
        (mapping): mapping is ResourceMapping => mapping.category === 'permissions' && typeof mapping.key === 'string',
    );
    const operationLabel = (id: string) => {
        const [key, role] = id.split('.');
        if (key === 'app' && role !== 'all') return role;
        return translate(`sw-privileges.additional_permissions.${key}.${role}`);
    };
    const operation = (id: string) => ({
        id,
        label: operationLabel(id),
        ...(privileges.getPrivilegeRole(id)?.menuIndependent ? { menuIndependent: true } : {}),
    });
    return {
        mappings,
        resources,
        keys: resources.map((mapping) => mapping.key),
        operation,
        resourceLabel: (key: string) => translate(`sw-privileges.permissions.${key}.label`),
        operationsForResource: (key: string) => [
            ...resourceOperations(key, mappings, translate),
            ...mappings.flatMap((mapping) =>
                mapping.category === 'additional_permissions'
                    ? Object.entries(mapping.roles)
                          .filter(([, role]) => role.resource === key)
                          .map(([role]) => operation(`${mapping.key}.${role}`))
                    : [],
            ),
        ],
    };
}

type PermissionCatalog = ReturnType<typeof permissionCatalog>;

function subresourceRows(
    context: { catalog: PermissionCatalog; owned: Set<string> },
    key: string,
    ownerMenuId: string,
    trail: string[] = [],
): PermissionRow[] {
    const { catalog, owned } = context;
    return catalog.resources
        .filter((mapping) => mapping.resource === key && ![key, ...trail].includes(mapping.key))
        .map((mapping) => {
            const operations = catalog.operationsForResource(mapping.key);
            operations.forEach(({ id }) => owned.add(id));
            return {
                id: `${ownerMenuId}:permission:permissions:${mapping.key}`,
                label: catalog.resourceLabel(mapping.key),
                menu: null,
                ownerMenuId,
                operations,
                children: subresourceRows(context, mapping.key, ownerMenuId, [...trail, key]),
            };
        });
}

function findBusinessContainer(items: PermissionRow[], key: string): PermissionRow | undefined {
    for (const row of items) {
        if (row.menu && (row.menu.routeKey || row.menu.internalKey) === key) return row;
        const found = findBusinessContainer(row.children, key);
        if (found) return found;
    }
    return undefined;
}

function attachUnownedResources(rows: PermissionRow[], catalog: PermissionCatalog, owned: Set<string>): PermissionRow[] {
    const others: PermissionRow[] = [];
    const fallback = new Map<string, PermissionRow>();
    catalog.resources.forEach((mapping) => {
        const operations = catalog.operationsForResource(mapping.key).filter(({ id }) => !owned.has(id));
        if (!operations.length) return;
        operations.forEach(({ id }) => owned.add(id));
        fallback.set(mapping.key, {
            id: `permission:permissions:${mapping.key}`,
            menu: null,
            children: [],
            operations,
            label: catalog.resourceLabel(mapping.key),
        });
    });
    const folders: Record<string, string> = {
        catalogues: 'sw-catalogue',
        customers: 'sw-customer',
        orders: 'sw-order',
        content: 'sw-content',
        marketing: 'sw-marketing',
        settings: 'sw-settings',
        system: 'sw-system',
        payment_gateway: 'sw-finance',
    };
    fallback.forEach((row, key) => {
        const mapping = catalog.resources.find((entry) => entry.key === key);
        if (!mapping) return;
        // Cyclic extension metadata must not make a permission disappear from the table.
        const ancestors = new Set([key]);
        let ancestor = mapping.resource;
        while (ancestor && !ancestors.has(ancestor)) {
            ancestors.add(ancestor);
            ancestor = catalog.resources.find((entry) => entry.key === ancestor)?.resource;
        }
        const parent = !ancestor && mapping.resource ? fallback.get(mapping.resource) : undefined;
        const folder = findBusinessContainer(rows, folders[mapping.parent]);
        (parent?.children ?? folder?.children ?? others).push(row);
    });
    return others;
}

function unownedOperations(
    catalog: PermissionCatalog,
    owned: Set<string>,
    translate: (key: string) => string,
    rows: PermissionRow[],
): PermissionRow[] {
    return catalog.mappings.flatMap((mapping) => {
        if (!mapping.key || mapping.category !== 'additional_permissions') return [];
        const operations = Object.keys(mapping.roles)
            .filter((role) => !owned.has(`${mapping.key}.${role}`))
            .map((role) => catalog.operation(`${mapping.key}.${role}`));
        if (!operations.length) return [];
        const row: PermissionRow = {
            id: `permission:${mapping.category}:${mapping.key}`,
            menu: null,
            children: [],
            operations,
            label: translate(`sw-privileges.${mapping.category}.${mapping.key}.label`),
        };
        const containers: Record<string, string> = { system: 'sw-settings', app: 'sw-extension', user: 'sw-system' };
        const container = findBusinessContainer(rows, containers[mapping.key]);
        if (!container) return [row];
        container.children.push(row);
        return [];
    });
}

/** One row per page, subfeatures below their owner, and unmapped functions under their business folder. @private */
export function createPermissionRows(
    nodes: PermissionNode[],
    routes: RouteAllowlistEntry[],
    privileges: PrivilegesService,
    translate: (key: string) => string,
): PermissionRow[] {
    const catalog = permissionCatalog(privileges, translate);
    const { mappings, resources, keys } = catalog;
    const owned = new Set<string>();
    const visit = (items: PermissionNode[], inherited: string[]): PermissionRow[] =>
        items.flatMap((item) => {
            if (!item.active || item.archived || item.type === 'hidden') return [];
            const page = { ...item, children: [] };
            const isPage = item.type === 'menu' || item.type === 'link';
            const pageKeys = isPage
                ? [
                      ...new Set([
                          ...permissionKeysForMenus([page], routes, keys),
                          ...resources
                              .filter((mapping) => mapping.menuKeys?.includes(item.routeKey || item.internalKey || ''))
                              .map((mapping) => mapping.key),
                      ]),
                  ]
                : [];
            const operations = pageKeys.flatMap((key) => resourceOperations(key, mappings, translate, pageKeys.length > 1));
            operations.forEach(({ id }) => owned.add(id));
            (isPage ? operationIdsForMenus([page], routes, mappings, inherited) : []).forEach((id) => {
                owned.add(id);
                operations.push(catalog.operation(id));
            });
            const children = visit(item.children, item.privilege ? [...inherited, item.privilege] : inherited);
            children.push(...pageKeys.flatMap((key) => subresourceRows({ catalog, owned }, key, item.id)));
            return [{ id: item.id, label: item.label, menu: item, operations, children }];
        });
    const rows = visit(nodes, []);
    const others = [...attachUnownedResources(rows, catalog, owned), ...unownedOperations(catalog, owned, translate, rows)];
    if (others.length)
        rows.push({
            id: 'other-permissions',
            menu: null,
            operations: [],
            children: others,
            label: translate('sw-permissions.roles.assignment.other'),
        });
    return rows;
}

/** Remove only operations owned by the unchecked branch, retaining operations shared by selected pages. @private */
export function revokeMenuOperations(
    roots: string[],
    rows: PermissionRow[],
    removedIds: string[],
    remainingIds: string[],
): string[] {
    const removed = new Set<string>();
    const shared = new Set<string>();
    const visit = (items: PermissionRow[]) =>
        items.forEach((row) => {
            const menuId = row.ownerMenuId ?? row.id;
            if (removedIds.includes(menuId))
                row.operations.filter((operation) => !operation.menuIndependent).forEach(({ id }) => removed.add(id));
            if (remainingIds.includes(menuId)) row.operations.forEach(({ id }) => shared.add(id));
            visit(row.children);
        });
    visit(rows);
    return roots.filter((id) => !removed.has(id) || shared.has(id));
}

/** @private */
export function rowMenuIds(row: PermissionRow): string[] {
    return row.menu ? menuLeafIds([row.menu]) : [];
}
