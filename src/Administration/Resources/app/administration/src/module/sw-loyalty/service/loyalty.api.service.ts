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
 * The aggregated loyalty state of one customer. The bookings are a separate, paginated call, so a
 * customer with a long history does not load all of it just to see the balance.
 *
 * @private
 */
export interface LoyaltySummary {
    pointsBalance: number;
    growthValue: number;
    membershipTier: LoyaltyMembershipTier | null;
    membershipValidUntil: string | null;
}

/**
 * One page of the point ledger of a customer.
 *
 * @private
 */
export interface LoyaltyTransactionPage {
    total: number;
    transactions: LoyaltySummaryTransaction[];
}

/**
 * The period a granted membership runs for.
 *
 * @private
 */
export interface LoyaltyMembershipPeriod {
    startsAt: string;
    expiresAt: string;
}

interface LoginService {
    getToken(): string | null;
}

interface TransactionQuery {
    page?: number;
    limit?: number;
    type?: string | null;
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

    async getCustomerTransactions(customerId: string, query: TransactionQuery = {}): Promise<LoyaltyTransactionPage> {
        const params: Record<string, string | number> = {
            page: query.page ?? 1,
            limit: query.limit ?? 25,
        };

        if (query.type) {
            params.type = query.type;
        }

        const response = await this.httpClient.get<LoyaltyTransactionPage>(
            `/_action/loyalty/customer/${customerId}/transactions`,
            {
                headers: this.getHeaders(),
                params,
            },
        );

        return response.data;
    }

    async adjustPoints(customerId: string, points: number, comment?: string): Promise<void> {
        await this.httpClient.post(
            `/_action/loyalty/customer/${customerId}/points`,
            { points, comment: comment ?? '' },
            { headers: this.getHeaders() },
        );
    }

    async grantMembership(customerId: string, tierId: string, durationDays: number): Promise<LoyaltyMembershipPeriod> {
        const response = await this.httpClient.post<LoyaltyMembershipPeriod>(
            `/_action/loyalty/customer/${customerId}/membership`,
            { tierId, durationDays },
            { headers: this.getHeaders() },
        );

        return response.data;
    }

    async topUpWallet(customerId: string, currencyId: string, amount: number, externalReference?: string): Promise<void> {
        await this.httpClient.post(
            `/_action/wallet/customer/${customerId}/top-up`,
            { currencyId, amount, externalReference: externalReference ?? null },
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
