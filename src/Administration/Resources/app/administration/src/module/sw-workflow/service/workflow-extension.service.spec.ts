/** @sw-package framework */
import WorkflowExtensionService from './workflow-extension.service';
import { createWorkflowDraft } from 'src/core/workflow/workflow-document';
import type { WorkflowExtensionContext } from 'src/core/workflow/workflow-extension.types';

describe('workflow extension service', () => {
    it('installs registered extensions independently in each session and cleans up owned resources', () => {
        const service = new WorkflowExtensionService();
        const contexts: WorkflowExtensionContext[] = [];
        const cleanup = jest.fn();
        const unregister = service.register({
            name: 'example:tools',
            setup(context) {
                contexts.push(context);
                context.commands.register({
                    name: 'example:rename',
                    execute(document) {
                        document.policy = 'example:changed';
                        return document;
                    },
                });
                return cleanup;
            },
        });
        const first = service.createSession(createWorkflowDraft());
        const second = service.createSession(createWorkflowDraft());
        expect(contexts).toHaveLength(2);
        first.execute('example:rename');
        expect(second.getState().document.policy).toBe('core:structured');
        first.dispose();
        expect(cleanup).toHaveBeenCalledTimes(1);
        expect(() => contexts[0].getState()).toThrow();
        expect(contexts[1].getState().canEdit).toBe(true);
        unregister();
        const third = service.createSession(createWorkflowDraft());
        expect(third.canExecute('example:rename')).toBe(false);
        second.dispose();
        third.dispose();
        expect(cleanup).toHaveBeenCalledTimes(2);
    });

    it('rejects duplicate extension names and reports failed setup without breaking other extensions', () => {
        const service = new WorkflowExtensionService();
        service.register({
            name: 'example:broken',
            setup() {
                throw new Error('broken');
            },
        });
        expect(() => service.register({ name: 'example:broken', setup() {} })).toThrow();
        service.register({
            name: 'example:healthy',
            setup(context) {
                context.commands.register({
                    name: 'example:inspect',
                    mutatesDocument: false,
                    execute: (document) => document,
                });
            },
        });
        const onError = jest.fn();
        const session = service.createSession(createWorkflowDraft(), { onError });
        expect(onError).toHaveBeenCalledWith(new Error('broken'));
        expect(session.execute('example:inspect')).toBe(true);
        session.dispose();
    });
});

describe('workflow extension scenarios', () => {
    it('rolls back live installation if an editor already owns the extension identity', () => {
        const service = new WorkflowExtensionService();
        const first = service.createSession(createWorkflowDraft());
        const second = service.createSession(createWorkflowDraft(), {}, [{ name: 'example:conflict', setup() {} }]);

        expect(() =>
            service.register({
                name: 'example:conflict',
                setup(context) {
                    context.commands.register({
                        name: 'example:probe',
                        mutatesDocument: false,
                        execute: (document) => document,
                    });
                },
            }),
        ).toThrow('already installed');
        expect(first.canExecute('example:probe')).toBe(false);

        const third = service.createSession(createWorkflowDraft());
        expect(third.canExecute('example:probe')).toBe(false);
        first.dispose();
        second.dispose();
        third.dispose();
    });
});
