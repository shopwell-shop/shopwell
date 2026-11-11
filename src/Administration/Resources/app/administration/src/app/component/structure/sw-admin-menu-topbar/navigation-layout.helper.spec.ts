/**
 * @sw-package framework
 */
import { fitNavigationEntries, firstNavigationTarget, filterLayoutNavigation } from './navigation-layout.helper';

describe('horizontal navigation', () => {
    it('reserves room for More and moves only the overflowing suffix', () => {
        expect(fitNavigationEntries([90, 100, 110], 300, 70, 8)).toBe(2);
        expect(fitNavigationEntries([90, 100], 60, 70, 8)).toBe(0);
        expect(fitNavigationEntries([90, 100], 400, 70, 8)).toBe(2);
        expect(fitNavigationEntries([90, 100], 200, 70, 8)).toBe(2);
    });

    it('removes inaccessible routes and empty folders before splitting the tree', () => {
        const entries = filterLayoutNavigation(
            [
                { id: 'empty', children: [{ id: 'denied', path: 'denied', children: [] }] },
                { id: 'settings', path: 'sw.settings.index', children: [] },
                { id: 'products', children: [{ id: 'list', path: 'list', children: [] }] },
                { id: 'external', link: 'https://example.com', children: [] },
            ],
            (name) => name !== 'denied',
            false,
        );
        expect(entries.map((entry) => entry.id)).toEqual(['products', 'external']);
        expect(firstNavigationTarget(entries[0])).toEqual({ name: 'list' });
    });

    it('chooses the first internal leaf and preserves app route parameters', () => {
        expect(
            firstNavigationTarget({
                id: 'root',
                children: [
                    { id: 'external', link: 'https://example.com', children: [] },
                    { id: 'app', path: 'app.index', params: { app: 'reports' }, children: [] },
                ],
            }),
        ).toEqual({ name: 'app.index', params: { app: 'reports' } });
        expect(firstNavigationTarget({ id: 'leaf', path: 'dashboard', children: [] })).toEqual({ name: 'dashboard' });
    });
});
