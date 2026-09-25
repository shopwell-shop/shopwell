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

    it('should be able to get cmsPageState backwards compatible', () => {
        // The cmsPageState is deprecated and causes a warning, therefore ignore it
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (_, msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg === 'Shopwell.State.get("cmsPageState") is deprecated! Use Shopwell.Store.get instead.';
            },
        });

        Shopwell.Store.register({
            id: 'cmsPage',
            state: () => ({
                foo: 'bar',
            }),
        });

        expect(Shopwell.Store.get('cmsPage').foo).toBe('bar');
        Shopwell.Store.unregister('cmsPage');
    });

    it('should be able to commit cmsPageState backwards compatible', () => {
        // The cmsPageState is deprecated and causes a warning, therefore ignore it
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (_, msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg === 'Shopwell.State.get("cmsPageState") is deprecated! Use Shopwell.Store.get instead.';
            },
        });

        Shopwell.Store.register({
            id: 'cmsPage',
            state: () => ({
                foo: 'bar',
            }),
            actions: {
                setFoo(foo) {
                    this.foo = foo;
                },
            },
        });

        const store = Shopwell.Store.get('cmsPage');
        expect(store.foo).toBe('bar');

        store.setFoo('jest');
        expect(store.foo).toBe('jest');

        Shopwell.Store.unregister('cmsPage');
    });
});
