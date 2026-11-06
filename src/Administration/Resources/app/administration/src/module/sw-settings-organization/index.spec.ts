/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

describe('sw-settings-organization registration', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('registers the protected list route', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-organization');
        const route = module?.routes.get('sw.settings.organization.index');

        expect(route?.meta).toEqual(expect.objectContaining({ privilege: 'organization.viewer' }));
    });

    it.each([
        'sw-settings-organization-list',
        'sw-settings-organization-detail',
        'sw-settings-organization-unit-tree',
        'sw-settings-organization-unit-tree-item',
    ])('registers %s for extension resolution', (component) => {
        expect(Shopwell.Component.getComponentRegistry().has(component)).toBe(true);
    });
});
