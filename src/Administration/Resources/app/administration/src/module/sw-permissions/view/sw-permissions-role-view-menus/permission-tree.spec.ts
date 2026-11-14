/** @sw-package fundamentals@framework */
import PrivilegesService from 'src/app/service/privileges.service';
import en from '../../snippet/en.json';
import {
    createPermissionRows,
    dependencyClosure,
    independentPrivileges,
    revokeMenuOperations,
    selectedMenuRequirements,
    type PermissionNode,
} from './permission-tree';

function service() {
    const privileges = new PrivilegesService();
    privileges.addPrivilegeMappingEntries([
        {
            category: 'permissions',
            key: 'product',
            parent: 'catalogues',
            roles: {
                viewer: { privileges: ['product:read'], dependencies: [] },
                editor: { privileges: ['product:update'], dependencies: ['product.viewer'] },
                creator: { privileges: ['product:create'], dependencies: ['product.editor'] },
            },
        },
        {
            category: 'additional_permissions',
            key: 'product_actions',
            parent: null,
            roles: {
                approve: { resource: 'product', privileges: ['product:approve'], dependencies: ['product.viewer'] },
                export: { resource: 'product', privileges: ['product:export'], dependencies: ['product.viewer'] },
            },
        },
        {
            category: 'additional_permissions',
            key: 'global',
            parent: null,
            roles: {
                run: { privileges: ['global:run'], dependencies: [] },
            },
        },
    ]);
    return privileges;
}
const page: PermissionNode = {
    id: 'products',
    label: 'Products',
    type: 'menu',
    privilege: null,
    routeKey: 'product',
    internalKey: 'product',
    active: true,
    archived: false,
    children: [],
};
const routes = [
    { internalKey: 'product', type: 'menu' as const, parent: null, title: 'Products', privilege: 'product.viewer' },
];

describe('permission dependency ownership', () => {
    it('expands transitive dependencies without granting unrelated write operations', () => {
        const privileges = service();
        expect(dependencyClosure(['product_actions.approve'], privileges)).toEqual([
            'product_actions.approve',
            'product.viewer',
        ]);
        expect(dependencyClosure(['product.creator'], privileges)).toEqual([
            'product.creator',
            'product.editor',
            'product.viewer',
        ]);
    });

    it('removes dependencies only when no selected operation still needs them', () => {
        const privileges = service();
        const roots = independentPrivileges(
            ['product_actions.approve', 'product_actions.export', 'product.viewer'],
            privileges,
        );
        expect(
            dependencyClosure(
                roots.filter((id) => id !== 'product_actions.approve'),
                privileges,
            ),
        ).toEqual(['product_actions.export', 'product.viewer']);
        expect(
            dependencyClosure(
                roots.filter((id) => !id.startsWith('product_actions.')),
                privileges,
            ),
        ).toEqual([]);
    });

    it('preserves opaque grants and does not lose cyclic dependencies', () => {
        const privileges = service();
        privileges.addPrivilegeMappingEntry({
            category: 'permissions',
            key: 'cycle',
            parent: null,
            roles: {
                a: { privileges: [], dependencies: ['cycle.b'] },
                b: { privileges: [], dependencies: ['cycle.a'] },
            },
        });
        const roots = independentPrivileges(['custom:read', 'cycle.a', 'cycle.b'], privileges);
        expect(dependencyClosure(roots, privileges)).toEqual(expect.arrayContaining(['custom:read', 'cycle.a', 'cycle.b']));
    });

    it('includes access restrictions from every selected page ancestor', () => {
        const folder = { ...page, id: 'folder', type: 'folder', privilege: 'global.run', children: [page] };
        expect(selectedMenuRequirements([folder], ['products'], routes)).toEqual(['global.run', 'product.viewer']);
    });

    it('never grants an archived or hidden page through a folder', () => {
        const hidden = { ...page, id: 'hidden', type: 'hidden', privilege: 'global.run' };
        expect(selectedMenuRequirements([hidden], ['hidden'], routes)).toEqual([]);
        expect(createPermissionRows([hidden], routes, service(), (key) => key).some((row) => row.id === 'hidden')).toBe(
            false,
        );
    });

    it('associates only declared operations and retains unassociated grants in other permissions', () => {
        const rows = createPermissionRows([page], routes, service(), (key) => key);
        expect(rows[0].operations.map(({ id }) => id)).toEqual([
            'product.viewer',
            'product.creator',
            'product.editor',
            'product_actions.approve',
            'product_actions.export',
        ]);
        expect(rows[0].operations.some(({ id }) => id === 'product.deleter')).toBe(false);
        expect(
            rows
                .find((row) => row.id === 'other-permissions')
                ?.children.flatMap((row) => row.operations.map(({ id }) => id)),
        ).toEqual(['global.run']);
    });

    it('labels registered publishing and approval operations alongside the standard roles', () => {
        const privileges = service();
        privileges.addPrivilegeMappingEntry({
            category: 'permissions',
            key: 'workflow',
            parent: null,
            roles: {
                publisher: { privileges: ['workflow:publish'], dependencies: [] },
                approver: { privileges: ['workflow:approve'], dependencies: [] },
            },
        });
        const translate = (key: string) => {
            const role = key.replace('sw-privileges.roles.', '') as keyof (typeof en)['sw-privileges']['roles'];
            return en['sw-privileges'].roles[role] ?? key;
        };
        const rows = createPermissionRows([], [], privileges, translate);
        expect(rows[0].children.find((row) => row.id === 'permission:permissions:workflow')?.operations).toEqual([
            { id: 'workflow.publisher', label: 'Publish' },
            { id: 'workflow.approver', label: 'Approve' },
        ]);
    });

    it('restores shared dependencies from an operation outside the unchecked menu', () => {
        const privileges = service();
        const rows = createPermissionRows([page], routes, privileges, (key) => key);
        const roots = revokeMenuOperations(['product.editor', 'global.run', 'outside.run'], rows, ['products'], []);
        privileges.addPrivilegeMappingEntry({
            category: 'additional_permissions',
            key: 'outside',
            parent: null,
            roles: {
                run: { privileges: [], dependencies: ['product.editor'] },
            },
        });
        expect(dependencyClosure(roots, privileges)).toEqual(
            expect.arrayContaining([
                'outside.run',
                'global.run',
                'product.editor',
                'product.viewer',
            ]),
        );
    });
});
