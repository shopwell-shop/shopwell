/**
 * @sw-package framework
 */
describe('shopware-apps.store', () => {
    const store = Shopwell.Store.get('shopwareApps');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.apps).toStrictEqual([]);
        expect(store.selectedIds).toStrictEqual([]);
    });
});
