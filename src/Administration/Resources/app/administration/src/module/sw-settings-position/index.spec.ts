/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

describe('sw-settings-position registration', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('registers the protected list route', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-position');
        const route = module?.routes.get('sw.settings.position.index');

        expect(route?.meta).toEqual(expect.objectContaining({ privilege: 'position.viewer' }));
    });

    it.each(['sw-settings-position-list'])('registers %s for extension resolution', (component) => {
        expect(Shopwell.Component.getComponentRegistry().has(component)).toBe(true);
    });
});
