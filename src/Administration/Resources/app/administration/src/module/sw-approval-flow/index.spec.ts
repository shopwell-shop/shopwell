/** @sw-package framework */
import './index';
import PrivilegesService from 'src/app/service/privileges.service';

jest.mock('./acl', () => jest.fn());

describe('approval flow module registration', () => {
    it('keeps one personal approval menu active for all three views', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-approval-flow');
        const routes = { todo: undefined, done: 'sw.approval.flow.todo', started: 'sw.approval.flow.todo' };
        for (const [view, parentPath] of Object.entries(routes)) {
            const route = module?.routes.get(`sw.approval.flow.${view}`);
            const meta = route?.meta as { privilege?: string; parentPath?: string } | undefined;
            expect(route?.components).toEqual({ default: 'sw-approval-flow-list' });
            expect(meta?.privilege).toBe('approval_flow.viewer');
            expect(meta?.parentPath).toBe(parentPath);
        }
        expect(module?.manifest.navigation?.filter((entry) => entry.privilege === 'approval_flow.viewer')).toEqual([
            {
                id: 'sw-approval-flow-todo',
                path: 'sw.approval.flow.todo',
                privilege: 'approval_flow.viewer',
                moduleType: 'core',
            },
        ]);
        expect(module?.manifest.navigation).toContainEqual({ id: 'sw-approval-flow', moduleType: 'core' });
        expect((module?.routes.get('sw.approval.flow.detail')?.meta as { privilege?: string } | undefined)?.privilege).toBe(
            'approval_flow.viewer',
        );
    });

    it('puts definition management under approvals with its own read privilege', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-approval-flow');
        expect(module?.manifest.navigation).toContainEqual(
            expect.objectContaining({
                id: 'sw-approval-flow-definitions',
                path: 'sw.approval.flow.definitions',
                privilege: 'approval_flow_definition.viewer',
            }),
        );
        expect((module?.routes.get('sw.approval.flow.definition')?.meta as { privilege: string }).privilege).toBe(
            'approval_flow_definition.viewer',
        );
    });

    it('grants personal access without opening generic approval or user CRUD', () => {
        const privileges = new PrivilegesService();
        const service = jest
            .spyOn(Shopwell, 'Service')
            .mockImplementation((() => privileges) as unknown as typeof Shopwell.Service);
        try {
            jest.isolateModules(() => {
                jest.requireActual('./acl');
            });
            expect(privileges.getPrivilegeRole('approval_flow_definition.viewer')?.privileges).toEqual([
                'approval_flow_definition:read',
                'approval_flow_draft:read',
                'approval_flow_release:read',
            ]);
            expect(privileges.getPrivilegeRole('approval_flow.viewer')?.privileges).toEqual(['approval_flow:access']);
            expect(privileges.getPrivilegeRole('approval_flow.editor')).toMatchObject({
                privileges: ['approval_flow_task:decide'],
                dependencies: ['approval_flow.viewer'],
            });
        } finally {
            service.mockRestore();
        }
    });
});
