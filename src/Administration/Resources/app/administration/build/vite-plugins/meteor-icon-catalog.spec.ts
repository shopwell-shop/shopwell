/** @sw-package framework */
import { readdirSync } from 'node:fs';
import path from 'node:path';
import { meteorIconNames } from './meteor-icon-catalog';

describe('menu refinements', () => {
    it('discovers the complete installed Meteor icon catalog without importing SVG contents', () => {
        const names = meteorIconNames();
        const root = path.dirname(require.resolve('@shopwell-ag/meteor-icon-kit/package.json'));
        const expected = ['regular', 'solid'].flatMap((mode) =>
            readdirSync(path.join(root, 'icons', mode))
                .filter((file) => file.endsWith('.svg'))
                .map((file) => `${mode}-${file.slice(0, -4)}`),
        );
        expect(names).toEqual(expected.sort());
        expect(new Set(names).size).toBe(names.length);
    });
});
