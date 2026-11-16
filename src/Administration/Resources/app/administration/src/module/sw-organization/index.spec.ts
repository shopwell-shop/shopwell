/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

describe('sw-organization registration', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('registers the protected list route', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-organization');
        const route = module?.routes.get('sw.organization.index');

        expect(route?.components?.default).toBe('sw-organization-detail');
        expect(route?.meta).toEqual(expect.objectContaining({ privilege: 'organization.viewer' }));
    });

    it('reuses the detail page for a creator-protected create route', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-organization');
        const route = module?.routes.get('sw.organization.create');

        expect(route?.components?.default).toBe('sw-organization-detail');
        expect(route?.meta).toEqual(expect.objectContaining({ privilege: 'organization.creator' }));
    });

    it.each([
        'sw-organization-list',
        'sw-organization-detail',
        'sw-organization-unit-tree',
        'sw-organization-unit-tree-item',
    ])('registers %s for extension resolution', (component) => {
        expect(Shopwell.Component.getComponentRegistry().has(component)).toBe(true);
    });
});
