/** @sw-package framework */
import { visibleTreeOptions, selectedTreePath } from './tree-options';

const options = [{ value: 'root', label: 'Root', selectable: false, children: [{ value: 'page', label: 'Products' }] }];
describe('menu refinements', () => {
    it('expands branches without adding decorative text to labels', () => {
        expect(visibleTreeOptions(options, [], '')).toMatchObject([{ label: 'Root', depth: 0 }]);
        expect(visibleTreeOptions(options, ['root'], '')).toMatchObject([
            { label: 'Root', depth: 0 },
            { label: 'Products', depth: 1 },
        ]);
        expect(selectedTreePath(options, 'page')).toBe('Root / Products');
    });
    it('searches leaves and retains ancestors even if collapsed', () => {
        expect(visibleTreeOptions(options, [], 'products').map((row) => row.value)).toEqual(['root', 'page']);
        expect(visibleTreeOptions(options, [], 'missing')).toEqual([]);
    });
});
