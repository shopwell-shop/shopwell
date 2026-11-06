/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

describe('sw-payment registration', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('registers the protected list route', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-payment');
        const route = module?.routes.get('sw.payment.index');

        expect(route?.meta).toEqual(expect.objectContaining({ privilege: 'payment_order.viewer' }));
    });

    it.each([
        'sw-payment-order-list',
        'sw-payment-order-detail',
        'sw-payment-refund-list',
        'sw-payment-refund-detail',
    ])('registers %s for extension resolution', (component) => {
        expect(Shopwell.Component.getComponentRegistry().has(component)).toBe(true);
    });
});
