import toolbarComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-toolbar', () => {
    const methods = (toolbarComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it('localizes a built-in layout title without replacing merchant names', () => {
        const computed = (toolbarComponent as unknown as { computed: Record<string, () => unknown> }).computed;
        const layout = { id: '91ddf2e3fd20050a47409eb9ebbdfff5', name: 'Default blog article' };
        const vm = { layout, $t: (key: string) => key };
        expect(computed.layoutName.call(vm)).toBe('sw-experience-studio.builtinLayouts.defaultBlogPost');
        layout.name = 'My article design';
        expect(computed.layoutName.call(vm)).toBe('My article design');
    });

    it('labels blog preview entries by their title', () => {
        const computed = (toolbarComponent as unknown as { computed: Record<string, () => unknown> }).computed;
        expect(computed.previewEntityLabelProperty.call({ previewEntityType: 'blog' })).toBe('title');
        expect(computed.previewEntityLabelProperty.call({ previewEntityType: 'product' })).toBe('name');
    });

    it('emits open-assignments when the assignments button is clicked', () => {
        const emit = jest.fn();

        methods.onOpenAssignments.call({ $emit: emit });

        expect(emit).toHaveBeenCalledWith('open-assignments');
    });
});
