import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ShopwellComponent from './component';
import { Shopwell } from './shopwell';

class LifecycleTestComponent extends ShopwellComponent {
    public static initCount = 0;

    public static destroyCount = 0;

    init(): void {
        LifecycleTestComponent.initCount += 1;
    }

    destroy(): void {
        LifecycleTestComponent.destroyCount += 1;
    }

    ping(value: string): string {
        return value;
    }
}

describe('Shopwell runtime component lifecycle', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    beforeEach(() => {
        document.body.innerHTML = '';
        LifecycleTestComponent.initCount = 0;
        LifecycleTestComponent.destroyCount = 0;

        const mutableShopwell = Shopwell as unknown as {
            componentRegistry: Map<string, typeof ShopwellComponent | null>;
            instanceRegistry: unknown[];
            interceptionRegistry: Map<string, unknown[]>;
            instanceIndexByElement: WeakMap<Node, Map<string, ShopwellComponent>>;
        };

        mutableShopwell.componentRegistry = new Map();
        mutableShopwell.instanceRegistry = [];
        mutableShopwell.interceptionRegistry = new Map();
        mutableShopwell.instanceIndexByElement = new WeakMap();
    });

    it('prevents duplicate initialization on the same element', () => {
        const componentName = 'Sw:Lifecycle:Duplicate';
        const element = document.createElement('div');

        const first = Shopwell.initializeComponentOnElement(componentName, LifecycleTestComponent, element);
        const second = Shopwell.initializeComponentOnElement(componentName, LifecycleTestComponent, element);

        expect(first).toBe(second);
        expect(LifecycleTestComponent.initCount).toBe(1);
        expect(Shopwell.getComponentInstances(componentName)).toHaveLength(1);
    });

    it('initializes and destroys nested components recursively', async () => {
        const componentName = 'Sw:Lifecycle:Nested';
        const root = document.createElement('div');
        root.setAttribute('data-component', componentName);
        const child = document.createElement('div');
        child.setAttribute('data-component', componentName);
        root.appendChild(child);

        const host = document.createElement('div');
        host.appendChild(root);

        const mutableShopwell = Shopwell as unknown as {
            componentRegistry: Map<string, typeof ShopwellComponent>;
            handleAddedNodes(nodes: NodeList): Promise<void>;
            handleRemovedNodes(nodes: NodeList): void;
        };

        mutableShopwell.componentRegistry.set(componentName, LifecycleTestComponent);
        await mutableShopwell.handleAddedNodes(host.childNodes);

        expect(Shopwell.getComponentInstances(componentName)).toHaveLength(2);

        mutableShopwell.handleRemovedNodes(host.childNodes);
        expect(LifecycleTestComponent.destroyCount).toBe(2);
        expect(Shopwell.getComponentInstances(componentName)).toHaveLength(0);
    });

    it('destroys all component instances attached to the same removed node', () => {
        const node = document.createElement('div');
        Shopwell.initializeComponentOnElement('Sw:Lifecycle:One', LifecycleTestComponent, node);
        Shopwell.initializeComponentOnElement('Sw:Lifecycle:Two', LifecycleTestComponent, node);

        const host = document.createElement('div');
        host.appendChild(node);

        const mutableShopwell = Shopwell as unknown as {
            handleRemovedNodes(nodes: NodeList): void;
        };
        mutableShopwell.handleRemovedNodes(host.childNodes);

        expect(LifecycleTestComponent.destroyCount).toBe(2);
        expect(Shopwell.getComponentInstances('Sw:Lifecycle:One')).toHaveLength(0);
        expect(Shopwell.getComponentInstances('Sw:Lifecycle:Two')).toHaveLength(0);
    });

    it('clears indexed lookups after node removal', () => {
        const componentName = 'Sw:Lifecycle:IndexedLookup';
        const node = document.createElement('div');
        const host = document.createElement('div');
        host.appendChild(node);

        const instance = Shopwell.initializeComponentOnElement(componentName, LifecycleTestComponent, node);
        expect(instance).toBeDefined();
        expect(Shopwell.getComponentInstanceByElement(componentName, node)).toBe(instance);

        const mutableShopwell = Shopwell as unknown as {
            handleRemovedNodes(nodes: NodeList): void;
        };
        mutableShopwell.handleRemovedNodes(host.childNodes);

        expect(Shopwell.getComponentInstanceByElement(componentName, node)).toBeUndefined();
    });

    it('returns undefined and logs errors when component import fails', async () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        const component = await Shopwell.getComponent('non-existing-component-specifier');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
    });

    it('caches failed component imports to avoid repeated retries', async () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        const firstResult = await Shopwell.getComponent('non-existing-component-specifier');
        const secondResult = await Shopwell.getComponent('non-existing-component-specifier');

        expect(firstResult).toBeUndefined();
        expect(secondResult).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
    });

    it('allows cross-origin component imports resolved from import maps', async () => {
        const importMapScript = document.createElement('script');
        importMapScript.type = 'importmap';
        importMapScript.textContent = JSON.stringify({
            imports: {
                'Sw:FromCdn': 'https://cdn.example.com/component-from-cdn.js',
            },
        });
        document.body.appendChild(importMapScript);

        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const component = await Shopwell.getComponent('Sw:FromCdn');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
        expect(errorSpy.mock.calls[0]?.[0]).toBe('Failed to import component Sw:FromCdn:');
    });

    it('tries to import cross-origin component specifiers directly', async () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const component = await Shopwell.getComponent('https://evil.example/blocked-component.js');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
        expect(errorSpy.mock.calls[0]?.[0]).toBe('Failed to import component https://evil.example/blocked-component.js:');
    });

    it('tries to import unsafe-protocol component specifiers directly', async () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const component = await Shopwell.getComponent('javascript:alert(1)');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
        expect(errorSpy.mock.calls[0]?.[0]).toBe('Failed to import component javascript:alert(1):');
    });

    it('allows same-origin import-map URLs and only fails on missing module', async () => {
        const importMapScript = document.createElement('script');
        importMapScript.type = 'importmap';
        importMapScript.textContent = JSON.stringify({
            imports: {
                'Sw:Local': `${window.location.origin}/does-not-exist-component.js`,
            },
        });
        document.body.appendChild(importMapScript);

        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const component = await Shopwell.getComponent('Sw:Local');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
        expect(errorSpy.mock.calls[0]?.[0]).toBe('Failed to import component Sw:Local:');
    });

    it('allows loopback Vite /@fs/ component URLs in dev-server mode', async () => {
        const importMapScript = document.createElement('script');
        importMapScript.type = 'importmap';
        importMapScript.textContent = JSON.stringify({
            imports: {
                'Sw:DevFs': 'http://localhost:5175/@fs/var/www/html/src/Storefront/Resources/views/components/Sw/Custom/Test.js',
            },
        });
        document.body.appendChild(importMapScript);

        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const component = await Shopwell.getComponent('Sw:DevFs');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
        expect(errorSpy.mock.calls[0]?.[0]).toBe('Failed to import component Sw:DevFs:');
    });

    it('runs interceptors in descending priority order', () => {
        Shopwell.intercept('runtime:interceptor', (payload) => ({ ...payload, order: 'low' }), 1);
        Shopwell.intercept('runtime:interceptor', (payload) => ({ ...payload, order: 'high' }), 20);

        const result = Shopwell.emitInterception('runtime:interceptor', { order: 'initial' });

        expect(result).toEqual({ order: 'low' });
    });

    it('emits queued events asynchronously', async () => {
        const listener = vi.fn();
        Shopwell.on('runtime:queued', listener);

        Shopwell.emitQueued('runtime:queued', 'payload');
        expect(listener).not.toHaveBeenCalled();

        await Promise.resolve();

        expect(listener).toHaveBeenCalledOnce();
        expect(listener).toHaveBeenCalledWith('payload');
    });

    it('safely ignores callMethod invocations for missing methods', () => {
        const componentName = 'Sw:Lifecycle:Methods';
        const element = document.createElement('div');
        Shopwell.initializeComponentOnElement(componentName, LifecycleTestComponent, element);

        expect(() => Shopwell.callMethod(componentName, 'doesNotExist', 'value')).not.toThrow();
        expect(() => Shopwell.callMethod(componentName, 'ping', 'pong')).not.toThrow();
    });

    it('does not register observers and listeners on repeated construction', () => {
        const addEventListenerSpy = vi.spyOn(document, 'addEventListener');
        const observeSpy = vi.spyOn(MutationObserver.prototype, 'observe');
        const shopwellConstructor = Shopwell.constructor as { new (): unknown };

        const first = new shopwellConstructor();
        const second = new shopwellConstructor();

        expect(first).toBe(Shopwell);
        expect(second).toBe(Shopwell);
        expect(addEventListenerSpy).not.toHaveBeenCalled();
        expect(observeSpy).not.toHaveBeenCalled();
    });

    it('disconnect clears observers, listeners, registries, and instances', () => {
        const observerDisconnectSpy = vi.spyOn(MutationObserver.prototype, 'disconnect');
        const removeEventListenerSpy = vi.spyOn(document, 'removeEventListener');
        const emitterListener = vi.fn();
        const node = document.createElement('div');

        Shopwell.on('runtime:event', emitterListener);
        Shopwell.intercept('runtime:interceptor', (payload) => ({ ...payload, order: 'intercepted' }), 10);
        Shopwell.initializeComponentOnElement('Sw:Lifecycle:Disconnect', LifecycleTestComponent, node);

        Shopwell.disconnect();

        expect(observerDisconnectSpy).toHaveBeenCalledOnce();
        expect(removeEventListenerSpy).toHaveBeenCalledWith('DOMContentLoaded', expect.any(Function));
        expect(LifecycleTestComponent.destroyCount).toBe(1);
        expect(Shopwell.getComponentInstances('Sw:Lifecycle:Disconnect')).toHaveLength(0);
        expect(Shopwell.emitInterception('runtime:interceptor', { order: 'initial' })).toEqual({ order: 'initial' });

        Shopwell.emit('runtime:event');
        expect(emitterListener).not.toHaveBeenCalled();
    });
});
