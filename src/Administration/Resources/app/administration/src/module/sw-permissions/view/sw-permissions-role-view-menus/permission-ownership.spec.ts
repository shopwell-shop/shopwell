/** @sw-package fundamentals@framework */
import PrivilegesService from 'src/app/service/privileges.service';
import { createPermissionRows, revokeMenuOperations, type PermissionNode, type PermissionRow } from './permission-tree';

const translate = (key: string) => key;
const page = (id: string, privilege: string | null = null): PermissionNode => ({
    id,
    internalKey: id,
    routeKey: null,
    type: 'menu',
    label: id,
    privilege,
    active: true,
    archived: false,
    children: [],
});
const flatten = (rows: PermissionRow[]): PermissionRow[] => rows.flatMap((row) => [row, ...flatten(row.children)]);

describe('business ownership of permissions', () => {
    it('keeps personal profile access independent of selecting or revoking user management', () => {
        const privileges = new PrivilegesService();
        privileges.addPrivilegeMappingEntries([
            {
                category: 'permissions',
                key: 'user',
                parent: 'system',
                roles: { viewer: { privileges: [], dependencies: [] } },
            },
            {
                category: 'additional_permissions',
                key: 'user',
                parent: null,
                roles: {
                    update_profile: {
                        resource: 'user',
                        menuIndependent: true,
                        privileges: ['user_change_me'],
                        dependencies: [],
                    },
                },
            },
        ]);
        const rows = createPermissionRows([page('users', 'user.viewer')], [], privileges, translate);
        expect(rows).toHaveLength(1);
        expect(rows[0].operations).toContainEqual(
            expect.objectContaining({ id: 'user.update_profile', menuIndependent: true }),
        );
        expect(revokeMenuOperations(['user.viewer', 'user.update_profile'], rows, ['users'], [])).toEqual([
            'user.update_profile',
        ]);
    });

    it('places global settings actions and app access in their business containers', () => {
        const privileges = new PrivilegesService();
        privileges.addPrivilegeMappingEntries([
            {
                category: 'additional_permissions',
                key: 'system',
                parent: null,
                roles: { import_export: { privileges: [], dependencies: [] } },
            },
            {
                category: 'additional_permissions',
                key: 'app',
                parent: null,
                roles: { example: { privileges: [], dependencies: [] } },
            },
        ]);
        const rows = createPermissionRows(
            [page('sw-settings'), { ...page('sw-extension'), type: 'folder' }],
            [],
            privileges,
            translate,
        );
        expect(rows).toHaveLength(2);
        expect(rows[0].children[0].operations.map(({ id }) => id)).toEqual(['system.import_export']);
        expect(rows[1].children[0].operations.map(({ id }) => id)).toEqual(['app.example']);
    });

    it('keeps page subresources under their owner and revokes them with the page', () => {
        const privileges = new PrivilegesService();
        privileges.addPrivilegeMappingEntries([
            {
                category: 'permissions',
                key: 'account',
                parent: 'settings',
                roles: { viewer: { privileges: ['account:read'], dependencies: [] } },
            },
            {
                category: 'permissions',
                key: 'provider',
                parent: 'settings',
                resource: 'account',
                roles: { editor: { privileges: ['provider:update'], dependencies: [] } },
            },
        ]);
        const rows = createPermissionRows([page('accounts', 'account.viewer')], [], privileges, translate);
        expect(rows).toHaveLength(1);
        expect(rows[0].children[0].operations.map(({ id }) => id)).toEqual(['provider.editor']);
        expect(rows[0].children[0].ownerMenuId).toBe('accounts');
        expect(revokeMenuOperations(['provider.editor'], rows, ['accounts'], [])).toEqual([]);
    });

    it('merges additional operations into their business resource even without a menu', () => {
        const privileges = new PrivilegesService();
        privileges.addPrivilegeMappingEntries([
            {
                category: 'additional_permissions',
                key: 'integration_mcp',
                parent: null,
                roles: { editor: { resource: 'integration', privileges: [], dependencies: ['integration.viewer'] } },
            },
            {
                category: 'permissions',
                key: 'integration',
                parent: 'settings',
                roles: { viewer: { privileges: ['integration:read'], dependencies: [] } },
            },
        ]);
        const rows = createPermissionRows([], [], privileges, translate);
        expect(rows[0].children).toHaveLength(1);
        expect(rows[0].children[0].operations.map(({ id }) => id)).toEqual([
            'integration.viewer',
            'integration_mcp.editor',
        ]);
    });

    it.each(['menu', 'folder'])('attaches functions to a %s that contains their business pages', (type) => {
        const privileges = new PrivilegesService();
        privileges.addPrivilegeMappingEntry({
            category: 'permissions',
            key: 'integration',
            parent: 'settings',
            roles: { viewer: { privileges: [], dependencies: [] } },
        });
        const rows = createPermissionRows([{ ...page('sw-settings'), type }], [], privileges, translate);
        expect(rows).toHaveLength(1);
        expect(rows[0].children[0].operations.map(({ id }) => id)).toEqual(['integration.viewer']);
        expect(rows[0].children[0].menu).toBeNull();
    });

    it('allows explicit menu ownership when the entry ACL is a shared system setting', () => {
        const privileges = new PrivilegesService();
        privileges.addPrivilegeMappingEntry({
            category: 'permissions',
            key: 'measurement',
            parent: 'settings',
            menuKeys: ['measurement-settings'],
            roles: { editor: { privileges: [], dependencies: [] } },
        });
        const rows = createPermissionRows(
            [
                page('measurement-settings', 'system.system_config'),
                page('other-settings', 'system.system_config'),
            ],
            [],
            privileges,
            translate,
        );
        expect(rows[0].operations.map(({ id }) => id)).toEqual(['measurement.editor']);
        expect(rows[1].operations).toEqual([]);
    });

    it('keeps shared resources on both pages but never duplicates them in the fallback', () => {
        const privileges = new PrivilegesService();
        privileges.addPrivilegeMappingEntries([
            {
                category: 'permissions',
                key: 'account',
                parent: 'settings',
                roles: { viewer: { privileges: [], dependencies: [] } },
            },
            {
                category: 'permissions',
                key: 'provider',
                parent: 'settings',
                resource: 'account',
                roles: { editor: { privileges: [], dependencies: [] } },
            },
        ]);
        const rows = createPermissionRows(
            [
                page('accounts', 'account.viewer'),
                page('accounts-copy', 'account.viewer'),
            ],
            [],
            privileges,
            translate,
        );
        expect(flatten(rows).flatMap((row) => row.operations.map(({ id }) => id))).toEqual([
            'account.viewer',
            'provider.editor',
            'account.viewer',
            'provider.editor',
        ]);
        expect(revokeMenuOperations(['provider.editor'], rows, ['accounts'], ['accounts-copy'])).toEqual([
            'provider.editor',
        ]);
    });
});
