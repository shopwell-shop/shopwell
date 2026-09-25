import type { PaymentOverviewCard } from './overview-cards.store';

describe('module/sw-settings-payment/store/overview-cards.store', () => {
    it('should have the initial state', () => {
        expect(Shopwell.Store.get('paymentOverviewCard').cards).toStrictEqual([]);
    });

    it('should add a payment overview card', () => {
        const paymentOverviewCard1 = { id: 'card-1' } as unknown as PaymentOverviewCard;
        const paymentOverviewCard2 = { id: 'card-2' } as unknown as PaymentOverviewCard;
        Shopwell.Store.get('paymentOverviewCard').add(paymentOverviewCard1);

        expect(Shopwell.Store.get('paymentOverviewCard').cards).toStrictEqual([paymentOverviewCard1]);

        Shopwell.Store.get('paymentOverviewCard').add(paymentOverviewCard2);

        expect(Shopwell.Store.get('paymentOverviewCard').cards).toStrictEqual([paymentOverviewCard1, paymentOverviewCard2]);
    });
});
