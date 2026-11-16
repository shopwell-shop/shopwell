/** @sw-package framework */

describe('XML menu route bindings', () => {
    it.each(['core', 'plugin'] as const)('registers a %s route binding without duplicating XML metadata', (type) => {
        const name = `xml-${type}`;
        const register = Shopwell.Module.register;
        register(name, {
            type,
            name,
            title: name,
            routes: { index: { path: 'index', component: 'example-page', meta: { privilege: 'example.viewer' } } },
            navigation: [{ id: name, path: `${name.replaceAll('-', '.')}.index`, privilege: 'example.viewer' }],
        });

        expect(Shopwell.Module.getModuleRegistry().get(name)?.navigation).toEqual([
            expect.objectContaining({ id: name, privilege: 'example.viewer' }),
        ]);
        Shopwell.Module.getModuleRegistry().delete(name);
    });
});
