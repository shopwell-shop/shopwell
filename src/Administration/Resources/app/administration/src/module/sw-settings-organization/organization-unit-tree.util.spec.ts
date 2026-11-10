/**
 * @sw-package framework
 */
import { getUnitPath, getDescendantIds } from './organization-unit-tree.util';

const rows = [
    { id: 'root', parentId: null, name: 'Headquarters' },
    { id: 'child', parentId: 'root', name: 'Engineering' },
    { id: 'leaf', parentId: 'child', name: 'Frontend' },
    { id: 'sibling', parentId: 'root', name: 'Sales' },
];
const units = rows as EntitySchema.Entity<'organization_unit'>[];

describe('organization hierarchy', () => {
    it('shows the complete path for identically named departments', () => {
        expect(getUnitPath(units, 'leaf')).toEqual(['Headquarters', 'Engineering', 'Frontend']);
        expect(getUnitPath(units, null)).toEqual([]);
    });

    it('excludes the selected unit and its descendants from valid parent choices', () => {
        expect(getDescendantIds(units, 'child')).toEqual(new Set(['child', 'leaf']));
        expect(getDescendantIds(units, null)).toEqual(new Set());
    });

    it('terminates when legacy data contains a parent cycle', () => {
        const cyclic = [{ ...rows[0], parentId: 'leaf' }, ...rows.slice(1)] as EntitySchema.Entity<'organization_unit'>[];
        expect(getUnitPath(cyclic, 'leaf')).toEqual(['Headquarters', 'Engineering', 'Frontend']);
        expect(getDescendantIds(cyclic, 'child')).toEqual(
            new Set([
                'root',
                'child',
                'leaf',
                'sibling',
            ]),
        );
    });
});
