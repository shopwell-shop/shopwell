/**
 * @sw-package framework
 */
describe('shopwell-apps.store', () => {
    const store = Shopwell.Store.get('shopwellApps');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.apps).toStrictEqual([]);
        expect(store.selectedIds).toStrictEqual([]);
    });
});
