/**
 * @sw-package checkout
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import LoyaltyApiService from './loyalty.api.service';

const TOKEN = 'the-token';

const expectedHeaders: Record<string, string> = {
    Accept: 'application/json',
    Authorization: `Bearer ${TOKEN}`,
    'Content-Type': 'application/json',
};

const createHttpClient = () => ({
    get: jest.fn(),
    post: jest.fn(),
});

const createService = (httpClient: ReturnType<typeof createHttpClient>) => {
    return new LoyaltyApiService(httpClient as unknown as HttpClient, { getToken: () => TOKEN });
};

describe('src/module/sw-loyalty/service/loyalty.api.service', () => {
    it('asks the summary endpoint of a customer with the bearer token', async () => {
        const httpClient = createHttpClient();
        const summary = {
            pointsBalance: 120,
            growthValue: 40,
            membershipTier: null,
            membershipValidUntil: null,
        };
        httpClient.get.mockResolvedValue({ data: summary });

        const result = await createService(httpClient).getCustomerSummary('customer-id');

        expect(result).toEqual(summary);
        expect(httpClient.get).toHaveBeenCalledWith('/_action/loyalty/customer/customer-id/summary', {
            headers: expectedHeaders,
        });
    });

    it('reads the first page of the ledger when no query is given', async () => {
        const httpClient = createHttpClient();
        httpClient.get.mockResolvedValue({ data: { total: 0, transactions: [] } });

        await createService(httpClient).getCustomerTransactions('customer-id');

        expect(httpClient.get).toHaveBeenCalledWith('/_action/loyalty/customer/customer-id/transactions', {
            headers: expectedHeaders,
            params: { page: 1, limit: 25 },
        });
    });

    it('passes the page, the limit and the type of a ledger query', async () => {
        const httpClient = createHttpClient();
        httpClient.get.mockResolvedValue({ data: { total: 0, transactions: [] } });

        await createService(httpClient).getCustomerTransactions('customer-id', { page: 3, limit: 10, type: 'refund' });

        expect(httpClient.get).toHaveBeenCalledWith('/_action/loyalty/customer/customer-id/transactions', {
            headers: expectedHeaders,
            params: { page: 3, limit: 10, type: 'refund' },
        });
    });

    it('sends an empty comment when an adjustment carries none', async () => {
        const httpClient = createHttpClient();
        httpClient.post.mockResolvedValue({ data: null });

        await createService(httpClient).adjustPoints('customer-id', -50);

        expect(httpClient.post).toHaveBeenCalledWith(
            '/_action/loyalty/customer/customer-id/points',
            { points: -50, comment: '' },
            { headers: expectedHeaders },
        );
    });

    it('grants a membership and answers the period of the ledger', async () => {
        const httpClient = createHttpClient();
        const period = { startsAt: '2026-01-01T00:00:00+00:00', expiresAt: '2026-04-01T00:00:00+00:00' };
        httpClient.post.mockResolvedValue({ data: period });

        const result = await createService(httpClient).grantMembership('customer-id', 'tier-id', 90);

        expect(result).toEqual(period);
        expect(httpClient.post).toHaveBeenCalledWith(
            '/_action/loyalty/customer/customer-id/membership',
            { tierId: 'tier-id', durationDays: 90 },
            { headers: expectedHeaders },
        );
    });

    it('sends a null reference when a top up carries none', async () => {
        const httpClient = createHttpClient();
        httpClient.post.mockResolvedValue({ data: null });

        await createService(httpClient).topUpWallet('customer-id', 'currency-id', 1000);

        expect(httpClient.post).toHaveBeenCalledWith(
            '/_action/wallet/customer/customer-id/top-up',
            { currencyId: 'currency-id', amount: 1000, externalReference: null },
            { headers: expectedHeaders },
        );
    });

    it('sends an empty comment when a wallet adjustment carries none', async () => {
        const httpClient = createHttpClient();
        httpClient.post.mockResolvedValue({ data: null });

        await createService(httpClient).adjustWallet('customer-id', 'currency-id', -250);

        expect(httpClient.post).toHaveBeenCalledWith(
            '/_action/wallet/customer/customer-id/adjust',
            { currencyId: 'currency-id', amount: -250, comment: '' },
            { headers: expectedHeaders },
        );
    });
});
