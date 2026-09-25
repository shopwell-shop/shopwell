describe('license-violation.store', () => {
    const store = Shopwell.Store.get('licenseViolation');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.violations).toStrictEqual([]);
        expect(store.warnings).toStrictEqual([]);
        expect(store.other).toStrictEqual([]);
    });
});
