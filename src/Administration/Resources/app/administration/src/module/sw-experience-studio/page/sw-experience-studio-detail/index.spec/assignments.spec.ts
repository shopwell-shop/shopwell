import detailComponent from '../index';

describe('Experience Studio assignment action', () => {
    it.each([
        ['product', false, true],
        ['category', false, true],
        ['landing_page', false, true],
        ['product', true, false],
        ['category', true, false],
        ['landing_page', true, false],
        ['none', false, false],
        [null, false, false],
    ])('gates assignments for root %s in create mode %s', (layoutRootSource, isCreateMode, expected) => {
        const computed = (detailComponent as unknown as { computed: Record<string, () => unknown> }).computed;

        expect(computed.canManageAssignments.call({ layoutRootSource, isCreateMode })).toBe(expected);
    });
});
