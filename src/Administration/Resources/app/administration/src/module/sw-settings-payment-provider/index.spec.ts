/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

describe('sw-settings-payment-provider registration', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('registers the protected settings route', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-payment-provider');
        const route = module?.routes.get('sw.settings.payment.provider.index');

        expect(route?.meta).toEqual(expect.objectContaining({ privilege: 'payment_account.viewer' }));
        expect(module?.manifest.settingsItem).toEqual(
            expect.arrayContaining([expect.objectContaining({ group: 'payment' })]),
        );
    });

    it.each([
        'sw-settings-payment-provider-list',
        'sw-settings-payment-provider-detail',
    ])('registers %s for extension resolution', (component) => {
        expect(Shopwell.Component.getComponentRegistry().has(component)).toBe(true);
    });
});
