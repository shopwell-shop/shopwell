/**
 * @sw-package after-sales
 */
import { getDashboardPeriod, getPeriodBounds } from './dashboard-period';

describe('dashboard today', () => {
    beforeEach(() => {
        Shopwell.Store.get('session').setCurrentUser({ timeZone: 'Asia/Shanghai' } as Entity<'user'>);
    });

    it('uses the operator’s local calendar date across a UTC day boundary', () => {
        expect(getDashboardPeriod(new Date('2026-06-02T18:00:00Z'))).toEqual({ date: '2026-06-03' });
        expect(getPeriodBounds({ date: '2026-06-03' })).toEqual({
            from: '2026-06-02T16:00:00.000Z',
            to: '2026-06-03T15:59:59.999Z',
        });
    });

    it('converts today’s boundaries through daylight saving changes', () => {
        Shopwell.Store.get('session').setCurrentUser({ timeZone: 'America/New_York' } as Entity<'user'>);
        expect(getPeriodBounds({ date: '2026-03-08' })).toEqual({
            from: '2026-03-08T05:00:00.000Z',
            to: '2026-03-09T03:59:59.999Z',
        });
    });
});
