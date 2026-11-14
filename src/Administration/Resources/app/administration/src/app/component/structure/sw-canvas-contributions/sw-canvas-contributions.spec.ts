/** @sw-package framework */
import { mount } from '@vue/test-utils';
import { CanvasEditorSession } from 'src/core/canvas-editor/canvas-editor-session';

const document = { title: 'Board', nodes: [{ id: 'card' }], connections: [] };

describe('canvas contribution host', () => {
    it('renders ordered node decorations with detached snapshots and a guarded context', async () => {
        const session = new CanvasEditorSession(document);
        session.install({
            name: 'example:badges',
            setup(context) {
                context.ui.registerComponent({
                    name: 'example:badge',
                    position: 'node-decoration',
                    component: 'example-canvas-badge',
                    when: (_state, target) => target.nodeId === 'card',
                });
            },
        });
        const wrapper = mount(await wrapTestComponent('sw-canvas-contributions', { sync: true }), {
            props: { session, componentPosition: 'node-decoration', target: { nodeId: 'card' } },
            global: {
                stubs: {
                    'example-canvas-badge': {
                        props: ['context', 'node', 'target'],
                        template:
                            '<span class="badge">{{ node.id }}:{{ target.nodeId }}:{{ context.getState().canEdit }}</span>',
                    },
                },
            },
        });
        expect(wrapper.get('.badge').text()).toBe('card:card:true');
        session.uninstall('example:badges');
        await flushPromises();
        expect(wrapper.find('.badge').exists()).toBe(false);
        wrapper.unmount();
        session.dispose();
    });
    it('opens an extension dialog and closes it through the public context', async () => {
        const session = new CanvasEditorSession(document);
        session.install({
            name: 'example:dialog',
            setup(context) {
                context.ui.registerComponent({
                    name: 'example:settings',
                    position: 'dialog',
                    component: 'example-canvas-dialog',
                    titleKey: 'example.settings',
                });
            },
        });
        const wrapper = mount(await wrapTestComponent('sw-canvas-contributions', { sync: true }), {
            props: { session, componentPosition: 'dialog' },
            global: {
                stubs: {
                    'sw-modal': { template: '<section role="dialog"><slot/></section>' },
                    'example-canvas-dialog': {
                        props: ['context', 'message'],
                        template: '<button class="close" @click="context.ui.closeDialog()">{{ message }}</button>',
                    },
                },
            },
        });
        session.context.ui.openDialog('example:settings', { message: 'Inspect' });
        await flushPromises();
        expect(wrapper.get('.close').text()).toBe('Inspect');
        await wrapper.get('.close').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        wrapper.unmount();
        session.dispose();
    });
    it('isolates a broken payload factory and keeps healthy actions usable', async () => {
        const onError = jest.fn();
        const session = new CanvasEditorSession(document, { onError });
        session.install({
            name: 'example:actions',
            setup(context) {
                context.commands.register({
                    name: 'example:rename',
                    execute(draft) {
                        draft.title = 'healthy';
                        return draft;
                    },
                });
                context.ui.registerAction({
                    name: 'example:broken',
                    position: 'toolbar',
                    labelKey: 'example.broken',
                    command: 'example:rename',
                    payload: () => {
                        throw new Error('payload failed');
                    },
                });
                context.ui.registerAction({
                    name: 'example:healthy',
                    position: 'toolbar',
                    labelKey: 'example.healthy',
                    command: 'example:rename',
                });
            },
        });
        const wrapper = mount(await wrapTestComponent('sw-canvas-contributions', { sync: true }), {
            props: { session, actionPosition: 'toolbar' },
        });
        expect(wrapper.get('[data-canvas-action="example:broken"]').attributes('disabled')).toBeDefined();
        expect(session.runAction('example:broken')).toBe(false);
        await wrapper.get('[data-canvas-action="example:healthy"]').trigger('click');
        expect(session.getState().document.title).toBe('healthy');
        expect(onError).toHaveBeenCalledWith(new Error('payload failed'));
        wrapper.unmount();
        session.dispose();
    });
});
