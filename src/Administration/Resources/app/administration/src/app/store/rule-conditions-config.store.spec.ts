/**
 * @sw-package framework
 */

describe('rule-conditions-config.store', () => {
    const store = Shopwell.Store.get('ruleConditionsConfig');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.config).toBeNull();
    });

    it('has getConfigForType action', () => {
        expect(store.getConfigForType('foo')).toBeNull();

        store.config = { foo: 'bar' };

        expect(store.getConfigForType('foo')).toBe('bar');
    });
});
