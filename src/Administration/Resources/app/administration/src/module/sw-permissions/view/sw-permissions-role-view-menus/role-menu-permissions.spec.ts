/** @sw-package fundamentals@framework */
import {
    ancestorMenuPrivileges,
    operationIdsForMenus,
    operationMenuLabels,
    menuLeafIds,
    requiredMenuPrivileges,
    permissionKeysForMenus,
    toggleMenuSelection,
} from './role-menu-permissions';

const page = { id: 'article', type: 'menu', routeKey: 'blog', children: [] };
const external = { id: 'docs', type: 'link', children: [] };
const group = { id: 'content', type: 'group', children: [page, external] };
const routes = [{ internalKey: 'blog', privilege: 'blog.viewer' }];

describe('role menu assignment', () => {
    it('selects all descendant entries and excludes grouping nodes', () => {
        expect(menuLeafIds([group])).toEqual(['article', 'docs']);
        expect(toggleMenuSelection(['other'], [group], true)).toEqual(['other', 'article', 'docs']);
        expect(toggleMenuSelection(['other', 'article', 'docs'], [group], false)).toEqual(['other']);
    });
    it('resolves access from the target declaration and keeps extra restrictions', () => {
        expect(requiredMenuPrivileges([{ ...page, privilege: 'custom.viewer' }], routes)).toEqual([
            'blog.viewer',
            'custom.viewer',
        ]);
    });
    it('includes restrictions inherited from all ancestors of the selected page', () => {
        expect(
            ancestorMenuPrivileges(
                [
                    {
                        ...group,
                        privilege: 'content.viewer',
                        children: [{ ...group, id: 'nested', privilege: 'extra.viewer' }],
                    },
                ],
                'article',
            ),
        ).toEqual(['content.viewer', 'extra.viewer']);
    });
    it('shares module permissions across multiple menus pointing at the same page', () => {
        expect(permissionKeysForMenus([page, { ...page, id: 'duplicate' }], routes, ['blog', 'order'])).toEqual(['blog']);
    });
});

describe('page operation ownership', () => {
    const mappings: Parameters<typeof operationIdsForMenus>[2] = [
        {
            category: 'additional_permissions',
            key: 'orders',
            roles: {
                create_discounts: { resource: 'order' },
            },
        },
        {
            category: 'additional_permissions',
            key: 'system',
            roles: {
                system_config: {},
                clear_cache: {},
            },
        },
        { category: 'additional_permissions', key: 'app', roles: { example: {} } },
    ];
    const settingsRoutes = [
        { internalKey: 'basic', privilege: 'system.system_config' },
        { internalKey: 'cart', privilege: 'system.system_config' },
        { internalKey: 'order', privilege: 'order.viewer' },
    ];
    const basic = { ...page, id: 'basic', label: 'Basic settings', routeKey: 'basic' };
    const cart = { ...page, id: 'cart', label: 'Cart settings', routeKey: 'cart' };
    it('associates special actions through an explicit resource without changing dependencies', () => {
        expect(operationIdsForMenus([{ ...page, routeKey: 'order' }], settingsRoutes, mappings)).toEqual([
            'orders.create_discounts',
        ]);
        expect(operationIdsForMenus([basic], settingsRoutes, mappings)).toEqual(['system.system_config']);
    });
    it('reports every page sharing an operation and keeps unassociated operations out', () => {
        expect(operationMenuLabels([basic, cart], settingsRoutes, mappings)).toEqual({
            'system.system_config': ['Basic settings', 'Cart settings'],
        });
    });
    it('includes an inherited page restriction and deduplicates repeated menu labels', () => {
        expect(
            operationMenuLabels(
                [{ ...group, privilege: 'system.clear_cache', children: [basic, { ...basic, id: 'copy' }] }],
                settingsRoutes,
                mappings,
            ),
        ).toEqual({
            'system.clear_cache': ['Basic settings'],
            'system.system_config': ['Basic settings'],
        });
    });
});
