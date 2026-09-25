import { getServicesWithShopwellAccountRequirement, serviceHasShopwellAccountRequirement } from './index';

describe('src/module/sw-settings-services/requirements', () => {
    it('detects services with the Shopwell Account requirement', () => {
        expect(serviceHasShopwellAccountRequirement(['shopwell_account'])).toBe(true);
        expect(serviceHasShopwellAccountRequirement(['service_consent'])).toBe(false);
        expect(serviceHasShopwellAccountRequirement([])).toBe(false);
    });

    it('returns services with the Shopwell Account requirement', () => {
        expect(
            getServicesWithShopwellAccountRequirement([
                {
                    name: 'account-service',
                    label: 'Account Service',
                    requirements: ['shopwell_account'],
                },
                {
                    name: 'regular-service',
                    label: 'Regular Service',
                    requirements: ['service_consent'],
                },
            ]),
        ).toEqual([
            {
                name: 'account-service',
                label: 'Account Service',
            },
        ]);
    });
});
