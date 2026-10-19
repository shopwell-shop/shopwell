/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

// The module maps its own privileges as it is loaded, the service that collects the mappings is
// registered when the application boots - which a unit test does not do.
Shopwell.Application.addServiceProvider('privileges', () => {
    return new PrivilegesService();
});

describe('src/module/sw-settings-oauth2-login/index.ts', () => {
    beforeAll(async () => {
        await import('src/module/sw-settings-oauth2-login');
    });

    it('should add the privilege mapping of the module', () => {
        const privilegesMappings = Shopwell.Service('privileges').getPrivilegesMappings();
        const privilegeMapping = privilegesMappings.find((entry) => entry.key === 'oauth2_client');

        expect(privilegeMapping).toBeDefined();
        expect(privilegeMapping.parent).toBe('settings');
        expect(privilegeMapping.category).toBe('permissions');

        [
            'viewer',
            'editor',
            'creator',
            'deleter',
        ].forEach((role) => {
            expect(privilegeMapping.roles).toHaveProperty(role);
            expect(privilegeMapping.roles[role].privileges.length).toBeGreaterThan(0);
        });

        expect(privilegeMapping.roles.viewer.privileges).toEqual(['oauth2_client:read']);
        expect(privilegeMapping.roles.editor.privileges).toEqual(['oauth2_client:update']);
        expect(privilegeMapping.roles.creator.privileges).toEqual(['oauth2_client:create']);
        expect(privilegeMapping.roles.deleter.privileges).toEqual(['oauth2_client:delete']);

        expect(privilegeMapping.roles.editor.dependencies).toEqual(['oauth2_client.viewer']);
        expect(privilegeMapping.roles.creator.dependencies).toEqual(['oauth2_client.viewer', 'oauth2_client.editor']);
        expect(privilegeMapping.roles.deleter.dependencies).toEqual(['oauth2_client.viewer']);
    });

    it('should register component', () => {
        expect(Shopwell.Component.getComponentRegistry().has('sw-settings-oauth2-login')).toBeTruthy();
    });

    it('should register the channel service', () => {
        expect(Shopwell.Service().get('oauth2ChannelService')).toBeDefined();
    });

    it('should register module base information', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-oauth2-login');

        expect(module).toBeDefined();

        expect(module.manifest).toEqual({
            type: 'core',
            name: 'settings-oauth2-login',
            title: 'sw-settings-oauth2-login.general.mainMenuItemGeneral',
            description: 'sw-settings-oauth2-login.general.description',
            version: '1.0.0',
            targetVersion: '1.0.0',
            color: 'var(--sw-color-module-neutral-default)',
            icon: 'regular-key',
            favicon: 'icon-module-settings.svg',
            entity: 'oauth2_client',
            display: true,
            routes: expect.any(Object),
            settingsItem: [
                {
                    group: 'general',
                    to: 'sw.settings.oauth2.login.index',
                    icon: 'regular-key',
                    privilege: 'oauth2_client.viewer',
                    id: 'sw-settings-oauth2-login',
                    name: 'settings-oauth2-login',
                    label: 'sw-settings-oauth2-login.general.mainMenuItemGeneral',
                },
            ],
        });
    });

    it('should register module routes', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-oauth2-login');
        const route = module.routes.get('sw.settings.oauth2.login.index');

        expect(module.routes.size).toBe(1);
        expect(route).toBeDefined();
        expect(route.path).toBe('/sw/settings/oauth2/login/index');
        expect(route.meta).toEqual({
            parentPath: 'sw.settings.index',
            privilege: 'oauth2_client.viewer',
        });
    });
});
