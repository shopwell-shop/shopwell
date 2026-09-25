describe('sdk-location.store', () => {
    const store = Shopwell.Store.get('sdkLocation');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.locations).toStrictEqual({});
    });

    it('should add a new location', () => {
        Shopwell.Store.get('sdkLocation').addLocation({
            componentName: 'card',
            locationId: 'sw-example-location',
        });

        expect(store.locations).toStrictEqual({
            'sw-example-location': 'card',
        });
    });
});
