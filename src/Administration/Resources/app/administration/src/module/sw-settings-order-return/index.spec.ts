/**
 * @sw-package after-sales
 */

import './index';

const { Module, Component } = Shopwell;

type RouteMeta = {
    privilege?: string;
    parentPath?: string;
};

describe('src/module/sw-settings-order-return/index', () => {
    it('registers the rules page, the reason list and the reason modal', () => {
        const components = Component.getComponentRegistry();

        [
            'sw-settings-order-return-index',
            'sw-settings-order-return-reason-list',
            'sw-settings-order-return-reason-modal',
        ].forEach((component) => {
            expect(components.has(component)).toBe(true);
        });
    });

    it('registers the module with the reason entity', () => {
        const module = Module.getModuleRegistry().get('sw-settings-order-return');

        expect(module).toBeTruthy();
        expect(module?.manifest.entity).toBe('order_return_reason');
    });

    it('registers both screens behind the settings index', () => {
        const routes = Module.getModuleRegistry().get('sw-settings-order-return')?.routes;

        expect(routes?.has('sw.settings.order.return.index')).toBe(true);
        expect(routes?.has('sw.settings.order.return.reason')).toBe(true);

        [
            ['sw.settings.order.return.index', 'system.system_config'],
            ['sw.settings.order.return.reason', 'order_return.viewer'],
        ].forEach(([name, privilege]) => {
            const route = routes?.get(name) as { meta?: RouteMeta } | undefined;

            expect(route?.meta?.parentPath).toBe('sw.settings.index');
            expect(route?.meta?.privilege).toBe(privilege);
        });
    });

    it('registers independent settings entries for channel rules and global reasons', () => {
        const settingsItems = (Module.getModuleRegistry().get('sw-settings-order-return')?.manifest.settingsItem ??
            []) as Array<{ group?: string; to?: string; privilege?: string }>;

        expect(settingsItems).toHaveLength(2);
        expect(settingsItems[0].group).toBe('commerce');
        expect(settingsItems[0].to).toBe('sw.settings.order.return.index');
        expect(settingsItems[0].privilege).toBe('system.system_config');
        expect(settingsItems[1]).toMatchObject({
            group: 'commerce',
            to: 'sw.settings.order.return.reason',
            privilege: 'order_return.viewer',
        });
    });

    it('keeps the reason entry in the commerce settings group without a sidebar shortcut', () => {
        const navigation = Module.getModuleRegistry().get('sw-settings-order-return')?.manifest.navigation ?? [];

        expect(navigation).not.toEqual(
            expect.arrayContaining([expect.objectContaining({ path: 'sw.settings.order.return.reason' })]),
        );
    });
});
