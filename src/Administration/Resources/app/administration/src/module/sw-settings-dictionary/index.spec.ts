/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

describe('sw-settings-dictionary registration', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('registers the protected list route', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-dictionary');
        const route = module?.routes.get('sw.settings.dictionary.index');

        expect(route?.meta).toEqual(expect.objectContaining({ privilege: 'dictionary.viewer' }));
    });

    it.each(['sw-settings-dictionary-list', 'sw-settings-dictionary-detail'])(
        'registers %s for extension resolution',
        (component) => {
            expect(Shopwell.Component.getComponentRegistry().has(component)).toBe(true);
        },
    );
});
