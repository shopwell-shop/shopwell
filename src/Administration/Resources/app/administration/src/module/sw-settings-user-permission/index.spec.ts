/**
 * @sw-package framework
 */
import './index';

describe('sw-settings-user-permission registration', () => {
    it('registers the protected list route', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-user-permission');
        const route = module?.routes.get('sw.settings.user.permission.index');

        expect(route?.meta).toEqual(expect.objectContaining({ privilege: 'user.viewer' }));
    });

    it.each(['sw-settings-user-permission'])('registers %s for extension resolution', (component) => {
        expect(Shopwell.Component.getComponentRegistry().has(component)).toBe(true);
    });
});
