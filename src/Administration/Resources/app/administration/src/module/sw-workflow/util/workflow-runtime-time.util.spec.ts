/** @sw-package framework */
import { relativeTime, absoluteTime, initialOf } from './workflow-runtime-time.util';

describe('workflow runtime time labels', () => {
    it('uses the administrator locale and preserves missing dates as empty values', () => {
        Shopwell.Store.get('session').currentLocale = 'en-GB';
        expect(relativeTime('2026-11-13T10:00:00Z', Date.parse('2026-11-13T11:00:00Z'))).toBe('1 hour ago');
        expect(absoluteTime('invalid')).toBe('');
        expect(relativeTime(null)).toBe('');
        expect(initialOf(' Alice')).toBe('A');
    });

    it.each([
        ['2026-11-13T10:59:00Z', '1 minute ago'],
        ['2026-11-13T12:00:00Z', 'in 1 hour'],
        ['2026-11-12T11:00:00Z', 'yesterday'],
    ])('selects the relative unit for %s', (timestamp, expected) => {
        Shopwell.Store.get('session').currentLocale = 'en-GB';

        expect(relativeTime(timestamp, Date.parse('2026-11-13T11:00:00Z'))).toBe(expected);
    });
});
