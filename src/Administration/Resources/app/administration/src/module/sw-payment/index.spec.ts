/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

describe('sw-payment registration', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('places payment and refund pages directly under Finance without a payment folder', () => {
        const navigation = Shopwell.Module.getModuleRegistry().get('sw-payment')?.manifest.navigation ?? [];
        expect(navigation).toHaveLength(2);
        expect(navigation).toEqual([
            expect.objectContaining({
                id: 'sw-payment-orders',
                parent: 'sw-finance',
                path: 'sw.payment.index',
                position: 10,
                privilege: 'payment_order.viewer',
            }),
            expect.objectContaining({
                id: 'sw-payment-refunds',
                parent: 'sw-finance',
                path: 'sw.payment.refund',
                position: 20,
                privilege: 'payment_refund.viewer',
            }),
        ]);
    });

    it('registers the protected list route', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-payment');
        const route = module?.routes.get('sw.payment.index');

        expect(route?.meta).toEqual(expect.objectContaining({ privilege: 'payment_order.viewer' }));
    });

    it.each(['payment_order.viewer', 'payment_refund.viewer'])('allows %s to read payment route information', (role) => {
        expect(Shopwell.Service('privileges').getPrivileges(role)()).toContain('payment_attempt:read');
    });

    it('allows finance viewers to filter transfer states by their state machine', () => {
        const privileges = Shopwell.Service('privileges').getPrivileges('payment_transfer.viewer')();
        expect(privileges).toEqual(expect.arrayContaining(['state_machine_state:read', 'state_machine:read']));
        expect(privileges).not.toContain('payment_transfer:update');
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
