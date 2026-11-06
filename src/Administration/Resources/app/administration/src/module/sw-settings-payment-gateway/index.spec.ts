/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

describe('sw-settings-payment-gateway registration', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('registers the protected settings route', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-settings-payment-gateway');
        const route = module?.routes.get('sw.settings.payment.gateway.list');

        expect(route?.meta).toEqual(expect.objectContaining({ privilege: 'payment_app.viewer' }));
        expect(module?.manifest.settingsItem).toEqual(
            expect.arrayContaining([expect.objectContaining({ group: 'payment' })]),
        );
    });

    it.each([
        'sw-payment-cashier-profiles',
        'sw-settings-payment-gateway-list',
        'sw-settings-payment-gateway-detail',
    ])('registers %s for extension resolution', (component) => {
        expect(Shopwell.Component.getComponentRegistry().has(component)).toBe(true);
    });
});
