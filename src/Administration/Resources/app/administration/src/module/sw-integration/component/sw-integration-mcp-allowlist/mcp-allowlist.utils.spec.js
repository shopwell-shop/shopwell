/**
 * @sw-package fundamentals@framework
 */
import { buildGroups, humanizeLabel, humanizeCommonPrefix } from './mcp-allowlist.utils';

describe('mcp-allowlist.utils', () => {
    describe('buildGroups', () => {
        it('groups items by key returned from getGroupKey', () => {
            const items = [
                { name: 'swag-orders', prefix: 'swag' },
                { name: 'swag-products', prefix: 'swag' },
                { name: 'acme-reports', prefix: 'acme' },
            ];

            const result = buildGroups(items, (item) => item.prefix);

            expect(result).toEqual({
                swag: [
                    { name: 'swag-orders', prefix: 'swag' },
                    { name: 'swag-products', prefix: 'swag' },
                ],
                acme: [{ name: 'acme-reports', prefix: 'acme' }],
            });
        });

        it('uses "other" as fallback key when getGroupKey returns null', () => {
            const items = [{ name: 'unknown' }];

            const result = buildGroups(items, () => null);

            expect(result).toEqual({ other: [{ name: 'unknown' }] });
        });

        it('returns empty object for empty items array', () => {
            expect(buildGroups([], () => 'x')).toEqual({});
        });
    });

    describe('humanizeLabel', () => {
        it('capitalizes each hyphen-separated word', () => {
            expect(humanizeLabel('shopwell-entity-search')).toBe('Shopwell Entity Search');
        });

        it('capitalizes each underscore-separated word', () => {
            expect(humanizeLabel('my_tool_name')).toBe('My Tool Name');
        });

        it('handles a single word', () => {
            expect(humanizeLabel('shopwell')).toBe('Shopwell');
        });
    });

    describe('humanizeCommonPrefix', () => {
        it('returns empty string for empty names array', () => {
            expect(humanizeCommonPrefix([])).toBe('');
        });

        it('returns full humanized name for a single entry', () => {
            expect(humanizeCommonPrefix(['shopwell-entity-search'])).toBe('Shopwell Entity Search');
        });

        it('returns longest common prefix for multiple names sharing a prefix', () => {
            const names = ['shopwell-entity-search', 'shopwell-entity-read', 'shopwell-entity-aggregate'];

            expect(humanizeCommonPrefix(names)).toBe('Shopwell Entity');
        });

        it('returns only the shared single segment when names diverge after first segment', () => {
            const names = ['swag-orders', 'swag-products'];

            expect(humanizeCommonPrefix(names)).toBe('Swag');
        });

        it('returns empty string when names share no common prefix', () => {
            const names = ['shopwell-search', 'acme-orders'];

            expect(humanizeCommonPrefix(names)).toBe('');
        });
    });
});
