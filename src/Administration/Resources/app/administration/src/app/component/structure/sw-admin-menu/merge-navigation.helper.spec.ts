/**
 * @sw-package framework
 */

import { mergeDatabaseNavigation } from './merge-navigation.helper';
import type { DatabaseMenuNode, NavigationEntryLike } from './merge-navigation.helper';

const declared: NavigationEntryLike[] = [
    { id: 'sw-dashboard', label: 'sw-dashboard.general.mainMenuItemGeneral', path: 'sw.dashboard.index', position: 10 },
    { id: 'sw-catalogue', label: 'global.sw-admin-menu.navigation.mainMenuItemProducts', position: 20 },
    { id: 'sw-product', label: 'sw-product.general.mainMenuItemOverview', path: 'sw.product.index', position: 10, parent: 'sw-catalogue' },
    { id: 'sw-order', label: 'sw-order.general.mainMenuItemGeneral', path: 'sw.order.index', position: 40 },
    { id: 'custom-app-entry', label: 'My app', path: 'my.app.index', position: 110 },
];

function node(overrides: Partial<DatabaseMenuNode> & { id: string }): DatabaseMenuNode {
    return { children: [], ...overrides };
}

describe('src/app/component/structure/sw-admin-menu/merge-navigation.helper', () => {
    it('returns the declarations untouched when the database renders nothing', () => {
        expect(mergeDatabaseNavigation(declared, [])).toEqual(declared);
    });

    it('orders the stored entries by the tree and keeps the declared ones that are not stored', () => {
        const tree = [
            node({ id: 'a', internalKey: 'sw-order', title: 'sw-order.general.mainMenuItemGeneral' }),
            node({ id: 'b', internalKey: 'sw-dashboard', title: 'sw-dashboard.general.mainMenuItemGeneral' }),
        ];

        const merged = mergeDatabaseNavigation(declared, tree);

        expect(merged.map((entry) => entry.id)).toEqual([
            'sw-order',
            'sw-dashboard',
            'sw-catalogue',
            'sw-product',
            'custom-app-entry',
        ]);
        expect(merged[0].position).toBe(10);
        expect(merged[1].position).toBe(20);
    });

    it('leaves a stored top level entry without a parent instead of a null parent', () => {
        // `FlatTree` compares roots against `undefined`; a `null` parent silently drops the
        // entry together with its whole subtree
        const tree = [node({ id: 'a', internalKey: 'sw-dashboard', title: 'Dashboard' })];

        const [entry] = mergeDatabaseNavigation(declared, tree);

        expect(entry.parent).toBeUndefined();
    });

    it('translates the stored title and marks the label as already translated', () => {
        const tree = [node({ id: 'a', internalKey: 'sw-dashboard', title: 'sw-dashboard.general.mainMenuItemGeneral' })];
        const translate = jest.fn((key: string) => `translated:${key}`);

        const [entry] = mergeDatabaseNavigation(declared, tree, translate);

        expect(translate).toHaveBeenCalledWith('sw-dashboard.general.mainMenuItemGeneral');
        expect(entry.label).toEqual({
            label: 'translated:sw-dashboard.general.mainMenuItemGeneral',
            translated: true,
        });
    });

    it('keeps the raw stored title when the translator fails', () => {
        // a broken translator is a rendering detail, losing the whole sidebar is not
        const tree = [node({ id: 'a', internalKey: 'sw-dashboard', title: 'sw-dashboard.general.mainMenuItemGeneral' })];
        const translate = jest.fn(() => {
            throw new Error('i18n is not ready');
        });

        const [entry] = mergeDatabaseNavigation(declared, tree, translate);

        expect(entry.label).toEqual({
            label: 'sw-dashboard.general.mainMenuItemGeneral',
            translated: true,
        });
        expect(entry.path).toBe('sw.dashboard.index');
    });

    it('keeps the declared label when the stored row has no title', () => {
        const tree = [{ id: 'a', internalKey: 'sw-dashboard', title: null, children: [] }];

        const [entry] = mergeDatabaseNavigation(declared, tree);

        expect(entry.label).toBe('sw-dashboard.general.mainMenuItemGeneral');
    });

    it('takes the stored icon, colour and privilege but keeps the declared ones when the row leaves them empty', () => {
        const tree = [
            node({
                id: 'a',
                internalKey: 'sw-dashboard',
                title: 'Dashboard',
                icon: 'regular-rocket',
                color: '#f00',
                privilege: 'dashboard.viewer',
            }),
            node({ id: 'b', internalKey: 'sw-order', title: 'Orders', icon: null, color: null, privilege: null }),
        ];

        const [dashboard, order] = mergeDatabaseNavigation(declared, tree);

        expect(dashboard.icon).toBe('regular-rocket');
        expect(dashboard.color).toBe('#f00');
        expect(dashboard.privilege).toBe('dashboard.viewer');
        expect(order.icon).toBeUndefined();
        expect(order.privilege).toBeUndefined();
    });

    it('anchors the children below the stored parent entry', () => {
        const tree = [
            node({
                id: 'a',
                internalKey: 'sw-catalogue',
                title: 'Catalogue',
                children: [node({ id: 'a1', internalKey: 'sw-product', title: 'Products' })],
            }),
        ];

        const merged = mergeDatabaseNavigation(declared, tree);
        const product = merged.find((entry) => entry.id === 'sw-product');

        expect(product?.parent).toBe('sw-catalogue');
        expect(product?.position).toBe(10);
    });

    it('drops a stored entry the code does not declare, including its children', () => {
        const tree = [
            node({
                id: 'a',
                internalKey: 'plugin-folder',
                title: 'Plugin folder',
                children: [node({ id: 'a1', internalKey: 'sw-dashboard', title: 'Dashboard' })],
            }),
        ];

        const merged = mergeDatabaseNavigation(declared, tree);

        expect(merged.some((entry) => entry.id === 'plugin-folder')).toBe(false);
        // The child cannot be attached to a parent that is not rendered, so it falls back to
        // its declaration instead of floating to the root.
        expect(merged).toContainEqual(declared[0]);
        expect(merged.find((entry) => entry.id === 'sw-dashboard')).toBe(declared[0]);
    });

    it('resolves synthesized rows by their internal key', () => {
        const tree = [node({ id: 'virtual:sw-dashboard', internalKey: 'sw-dashboard', title: 'Dashboard' })];

        const merged = mergeDatabaseNavigation(declared, tree);

        expect(merged[0].id).toBe('sw-dashboard');
        expect(merged[0].path).toBe('sw.dashboard.index');
    });

    it('opens the settings page instead of repeating its entries as a flyout', () => {
        // The settings page lists its own sections. Its stored children stay managed, but a
        // second navigation with the same list would be the flyout an operator just closed.
        const declaredWithSettings: NavigationEntryLike[] = [
            ...declared,
            { id: 'sw-settings', label: 'sw-settings.general.mainMenuItemGeneral', path: 'sw.settings.index', position: 990 },
            { id: 'sw-settings-tax', label: 'sw-settings-tax.general.mainMenuItemGeneral', path: 'sw.settings.tax.index', position: 100, parent: 'sw-settings' },
        ];

        const tree = [
            node({
                id: 's',
                internalKey: 'sw-settings',
                title: 'sw-settings.general.mainMenuItemGeneral',
                children: [node({ id: 's1', internalKey: 'sw-settings-tax', title: 'sw-settings-tax.general.mainMenuItemGeneral' })],
            }),
        ];

        const merged = mergeDatabaseNavigation(declaredWithSettings, tree);

        expect(merged.filter((entry) => entry.id === 'sw-settings')).toHaveLength(1);
        // not attached below the settings and not pushed to the top level either
        expect(merged.some((entry) => entry.id === 'sw-settings-tax')).toBe(false);
    });
});
