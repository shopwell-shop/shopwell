/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

// The module maps its own privileges as it is loaded, the service that collects the mappings is
// registered when the application boots - which a unit test does not do.
Shopwell.Application.addServiceProvider('privileges', () => {
    return new PrivilegesService();
});

const COMPONENTS = [
    'sw-settings-sms-section-tabs',
    'sw-settings-sms-gateway-list',
    'sw-settings-sms-gateway-detail',
    'sw-settings-sms-template-list',
    'sw-settings-sms-template-detail',
    'sw-settings-sms-delivery-list',
    'sw-settings-sms-template-mapping-grid',
    'sw-settings-sms-template-submission-modal',
];

describe('src/module/sw-settings-sms/index.ts', () => {
    beforeAll(async () => {
        await import('src/module/sw-settings-sms');
    });

    it('should add the privilege mappings of the module', () => {
        const privilegesMappings = Shopwell.Service('privileges').getPrivilegesMappings();

        const gatewayMapping = privilegesMappings.find((entry) => entry.key === 'sms_gateway');
        const templateMapping = privilegesMappings.find((entry) => entry.key === 'sms_template');
        const deliveryMapping = privilegesMappings.find((entry) => entry.key === 'sms_delivery');

        [gatewayMapping, templateMapping, deliveryMapping].forEach((entry) => {
            expect(entry).toBeDefined();
            expect(entry?.parent).toBe('settings');
            expect(entry?.category).toBe('permissions');
        });

        expect(gatewayMapping?.roles.viewer.privileges).toEqual(['sms_gateway:read']);
        expect(gatewayMapping?.roles.editor.privileges).toEqual(['sms_gateway:update']);
        expect(gatewayMapping?.roles.creator.privileges).toEqual(['sms_gateway:create']);
        expect(gatewayMapping?.roles.deleter.privileges).toEqual(['sms_gateway:delete']);

        expect(gatewayMapping?.roles.editor.dependencies).toEqual(['sms_gateway.viewer']);
        expect(gatewayMapping?.roles.creator.dependencies).toEqual(['sms_gateway.viewer', 'sms_gateway.editor']);

        // the mapping of a template onto the account of a provider is edited on the template
        expect(templateMapping?.roles.viewer.privileges).toEqual(['sms_template:read', 'sms_template_mapping:read']);
        expect(templateMapping?.roles.editor.privileges).toEqual([
            'sms_template:update',
            'sms_template_mapping:update',
        ]);
        expect(templateMapping?.roles.creator.privileges).toEqual([
            'sms_template:create',
            'sms_template_mapping:create',
        ]);
        expect(templateMapping?.roles.deleter.privileges).toEqual([
            'sms_template:delete',
            'sms_template_mapping:delete',
        ]);

        // the record of what went out is read only, no role may change an attempt that was made
        expect(deliveryMapping?.roles.viewer.privileges).toEqual(['sms_delivery:read']);
        expect(deliveryMapping?.roles.editor.privileges).toEqual([]);
        expect(deliveryMapping?.roles.creator.privileges).toEqual([]);
        expect(deliveryMapping?.roles.deleter.privileges).toEqual([]);
    });

    it('should register the screens and the mapping grid of the module', () => {
        const registry = Shopwell.Component.getComponentRegistry();

        COMPONENTS.forEach((component) => {
            expect(registry.has(component)).toBe(true);
        });
    });

    it('should register the service that asks for the providers of the server', () => {
        expect(Shopwell.Service().get('smsGatewayProviderService')).toBeDefined();
    });

    it('should register the service of the three audit steps', () => {
        expect(Shopwell.Service().get('smsTemplateAuditService')).toBeDefined();
    });

    it('should register the module base information', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-sms');

        expect(module).toBeDefined();

        const manifest = module?.manifest;

        expect(manifest?.type).toBe('core');
        expect(manifest?.name).toBe('settings-sms');
        expect(manifest?.title).toBe('sw-settings-sms.general.mainMenuItemGeneral');
        expect(manifest?.description).toBe('sw-settings-sms.general.description');
        expect(manifest?.entity).toBe('sms_gateway');
        expect(manifest?.icon).toBe('regular-envelope');

        expect(manifest?.settingsItem).toEqual([
            {
                group: 'system',
                to: 'sw.settings.sms.gateway',
                icon: 'regular-envelope',
                privilege: 'sms_gateway.viewer',
                id: 'sw-settings-sms',
                name: 'settings-sms',
                label: 'sw-settings-sms.general.mainMenuItemGeneral',
            },
        ]);
    });

    it('should route the three screens under the settings index', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-sms');
        const routes = module?.routes;

        expect(Array.from(routes?.keys() ?? []).sort()).toEqual([
            'sw.settings.sms.delivery',
            'sw.settings.sms.gateway',
            'sw.settings.sms.gateway.create',
            'sw.settings.sms.gateway.detail',
            'sw.settings.sms.template',
            'sw.settings.sms.template.create',
            'sw.settings.sms.template.detail',
        ]);

        expect(routes?.get('sw.settings.sms.gateway')?.path).toBe('/sw/settings/sms/gateway');
        expect(routes?.get('sw.settings.sms.template')?.path).toBe('/sw/settings/sms/template');
        expect(routes?.get('sw.settings.sms.delivery')?.path).toBe('/sw/settings/sms/delivery');
        expect(routes?.get('sw.settings.sms.gateway.detail')?.path).toBe('/sw/settings/sms/gateway/detail/:id');
        expect(routes?.get('sw.settings.sms.template.create')?.path).toBe('/sw/settings/sms/template/create');

        expect(routes?.get('sw.settings.sms.gateway')?.meta).toEqual({
            parentPath: 'sw.settings.index',
            privilege: 'sms_gateway.viewer',
        });
        expect(routes?.get('sw.settings.sms.delivery')?.meta).toEqual({
            parentPath: 'sw.settings.index',
            privilege: 'sms_delivery.viewer',
        });
    });
});
