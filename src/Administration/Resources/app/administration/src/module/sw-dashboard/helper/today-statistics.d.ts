/** @sw-package after-sales */

/** @private */
export type TodayTotals = {
    revenue: number;
    orders: number;
    customers: number;
    refundAmount: number;
    refundCount: number;
    memberships: number | null;
    walletTopUpAmount: number | null;
    walletTopUpCount: number | null;
};

/** @private */
export type HourBucket = { hour: number; amount: number | null; count: number | null };

/** @private */
export type TodayStatistics = {
    date: string;
    today: TodayTotals;
    previous: TodayTotals;
    hours: HourBucket[];
};
