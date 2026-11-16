import canvasComponent from './index';

describe('Experience Studio canvas Blog labels', () => {
    it('localizes a built-in layout name and preserves a merchant rename', () => {
        const methods = (canvasComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
        const layout = { id: '91ddf2e3fd20050a47409eb9ebbdfff5', name: 'Default blog article' };
        const vm = { $t: (key: string) => key };

        expect(methods.getLayoutName.call(vm, layout)).toBe('sw-experience-studio.builtinLayouts.defaultBlogPost');
        layout.name = 'My article design';
        expect(methods.getLayoutName.call(vm, layout)).toBe('My article design');
    });
});
