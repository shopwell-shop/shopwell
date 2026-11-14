/** @sw-package fundamentals@framework */
import fs from 'fs';
import path from 'path';
import PrivilegesService from 'src/app/service/privileges.service';
import { createPermissionRows, dependencyClosure, type PermissionNode, type PermissionRow } from './permission-tree';

const flatten = (rows: PermissionRow[]): PermissionRow[] => rows.flatMap((row) => [row, ...flatten(row.children)]);
const page = (id: string, privilege: string | null = null, type = 'menu'): PermissionNode => ({
    id,
    internalKey: id,
    routeKey: null,
    label: id,
    type,
    privilege,
    active: true,
    archived: false,
    children: [],
});

describe('registered system permission inventory', () => {
    let privileges: PrivilegesService;

    beforeAll(async () => {
        privileges = new PrivilegesService();
        /** @private */
        Shopwell.Service().register('privileges', () => privileges);
        const modules = path.resolve(__dirname, '../../..');
        for (const name of fs.readdirSync(modules)) {
            if (!fs.existsSync(path.join(modules, name, 'acl'))) continue;
            // Load each module's registered contract, including modules without a menu entry.
            await import(`../../../${name}/acl`);
        }
    });

    it('makes every visible registered operation reachable exactly once without menu entries', () => {
        const expected = privileges
            .getPrivilegesMappings()
            .flatMap((mapping) => Object.entries(mapping.roles).map(([role]) => `${mapping.key}.${role}`));
        const actual = flatten(createPermissionRows([], [], privileges, (key) => key)).flatMap((row) =>
            row.operations.map(({ id }) => id),
        );
        expect(actual.sort()).toEqual(expected.sort());
        expect(new Set(actual).size).toBe(actual.length);
    });

    it.each([
        ['sw-settings-payment-provider', 'payment_account.viewer', ['payment_provider_config', 'payment_channel_config']],
        ['sw-settings-sms', 'sms_gateway.viewer', ['sms_template', 'sms_delivery']],
        ['sw-category', 'category.viewer', ['landing_page']],
        ['sw.order.index', 'order.viewer', ['order_refund']],
    ])('keeps %s subfeatures within their real page', (id, entry, children) => {
        const rows = createPermissionRows([page(id, entry)], [], privileges, (key) => key);
        const childRows = rows[0].children;
        expect(childRows.map((row) => row.id)).toEqual(children.map((key) => `${id}:permission:permissions:${key}`));
        expect(childRows.every((row) => row.ownerMenuId === id && row.menu === null)).toBe(true);
        const leftovers = flatten(rows.slice(1)).flatMap((row) => row.operations.map((operation) => operation.id));
        expect(leftovers.some((operation) => children.some((key) => operation.startsWith(`${key}.`)))).toBe(false);
    });

    it('offers only query for read-only SMS deliveries without empty write roles', () => {
        const rows = flatten(
            createPermissionRows([page('sw-settings-sms', 'sms_gateway.viewer')], [], privileges, (key) => key),
        );
        const delivery = rows.find((row) => row.id.endsWith(':permissions:sms_delivery'));
        expect(delivery?.operations.map(({ id }) => id)).toEqual(['sms_delivery.viewer']);
        expect(privileges.existsPrivilege('sms_delivery.editor')).toBe(false);
        expect(dependencyClosure(['sms_delivery.viewer'], privileges)).toEqual(['sms_delivery.viewer']);
    });

    it('keeps integration and its MCP operation on one row in system settings', () => {
        const rows = createPermissionRows([page('sw-settings', null, 'folder')], [], privileges, (key) => key);
        const integration = rows[0].children.filter((row) => row.id.endsWith(':integration'));
        expect(integration).toHaveLength(1);
        expect(integration[0].operations.map(({ id }) => id)).toEqual([
            'integration.viewer',
            'integration.creator',
            'integration.editor',
            'integration.deleter',
            'integration_mcp.editor',
        ]);
        expect(flatten(rows).filter((row) => row.id.endsWith(':integration_mcp'))).toEqual([]);
    });

    it('places extension upload beside extension management without leaking it to unrelated settings', () => {
        const rows = createPermissionRows(
            [
                page('extensions', 'system.plugin_maintain'),
                page('basic-settings', 'system.system_config'),
            ],
            [],
            privileges,
            (key) => key,
        );
        expect(rows[0].operations.map(({ id }) => id)).toEqual(['system.plugin_maintain', 'system.plugin_upload']);
        expect(rows[1].operations.map(({ id }) => id)).toEqual(['system.system_config']);
    });

    it('retains all operations when features are associated with menu pages', () => {
        const nodes = privileges
            .getPrivilegesMappings()
            .filter((mapping) => mapping.category === 'permissions' && !mapping.resource)
            .map((mapping) => page(mapping.menuKeys?.[0] ?? mapping.key!, `${mapping.key}.viewer`));
        const actual = new Set(
            flatten(createPermissionRows(nodes, [], privileges, (key) => key)).flatMap((row) =>
                row.operations.map(({ id }) => id),
            ),
        );
        const expected = privileges
            .getPrivilegesMappings()
            .flatMap((mapping) => Object.entries(mapping.roles).map(([role]) => `${mapping.key}.${role}`));
        expect([...actual].sort()).toEqual(expected.sort());
    });
});
