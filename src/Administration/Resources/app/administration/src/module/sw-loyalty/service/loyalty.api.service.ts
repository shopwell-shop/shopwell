/**
 * @sw-package checkout
 */
import type { HttpClient } from 'src/core/factory/http-client.types';

/**
 * One membership tier of a customer, as returned by the loyalty summary endpoint.
 *
 * @private
 */
export interface LoyaltyMembershipTier {
    id: string;
    technicalName: string;
    name: string;
    level: number;
    pointsMultiplier: number;
}

/**
 * One booked point transaction of a customer.
 *
 * @private
 */
export interface LoyaltySummaryTransaction {
    id: string;
    type: string;
    points: number;
    balanceAfter: number;
    orderId: string | null;
    comment: string | null;
    expiresAt: string | null;
    createdAt: string | null;
}

/**
 * The aggregated loyalty state of one customer.
 *
 * @private
 */
export interface LoyaltySummary {
    pointsBalance: number;
    growthValue: number;
    membershipTier: LoyaltyMembershipTier | null;
    membershipValidUntil: string | null;
    transactions: LoyaltySummaryTransaction[];
}

interface LoginService {
    getToken(): string | null;
}

/**
 * Talks to the loyalty and wallet action routes of the Admin API. Balances are owned by the server
 * services, so the administration only asks for the summary and books adjustments through here.
 *
 * @private
 */
export default class LoyaltyApiService {
    constructor(
        private readonly httpClient: HttpClient,
        private readonly loginService: LoginService,
    ) {}

    async getCustomerSummary(customerId: string): Promise<LoyaltySummary> {
        const response = await this.httpClient.get<LoyaltySummary>(`/_action/loyalty/customer/${customerId}/summary`, {
            headers: this.getHeaders(),
        });

        return response.data;
    }

    async adjustPoints(customerId: string, points: number, comment?: string): Promise<void> {
        await this.httpClient.post(
            `/_action/loyalty/customer/${customerId}/points`,
            { points, comment: comment ?? '' },
            { headers: this.getHeaders() },
        );
    }

    async topUpWallet(customerId: string, currencyId: string, amount: number): Promise<void> {
        await this.httpClient.post(
            `/_action/wallet/customer/${customerId}/top-up`,
            { currencyId, amount },
            { headers: this.getHeaders() },
        );
    }

    async adjustWallet(customerId: string, currencyId: string, amount: number, comment?: string): Promise<void> {
        await this.httpClient.post(
            `/_action/wallet/customer/${customerId}/adjust`,
            { currencyId, amount, comment: comment ?? '' },
            { headers: this.getHeaders() },
        );
    }

    private getHeaders(): Record<string, string> {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}
