/**
 * @sw-package framework
 */
import initState from 'src/app/init-pre/state.init';

describe('src/app/init-pre/state.init.ts', () => {
    initState();

    it('should contain all state methods', () => {
        expect(Shopwell.State._store).toBeDefined();
        expect(Shopwell.State.list).toBeDefined();
        expect(Shopwell.State.get).toBeDefined();
        expect(Shopwell.State.getters).toBeDefined();
        expect(Shopwell.State.commit).toBeDefined();
        expect(Shopwell.State.dispatch).toBeDefined();
        expect(Shopwell.State.watch).toBeDefined();
        expect(Shopwell.State.subscribe).toBeDefined();
        expect(Shopwell.State.subscribeAction).toBeDefined();
        expect(Shopwell.State.registerModule).toBeDefined();
        expect(Shopwell.State.unregisterModule).toBeDefined();
    });

    it('should initialized all state modules', () => {
        expect(Shopwell.Store.get('shopwellApps')).toBeDefined();
    });
});
