/** @sw-package framework */
import './index';
import PrivilegesService from 'src/app/service/privileges.service';

jest.mock('./acl', () => jest.fn());

describe('approval flow module registration', () => {
    it('connects all personal inboxes to the approval menu with the same access boundary', () => {
        const module = Shopwell.Module.getModuleRegistry().get('sw-approval-flow');
        for (const view of ['todo', 'done', 'started']) {
            const route = module?.routes.get(`sw.approval.flow.${view}`);
            expect(route?.components).toEqual({ default: 'sw-approval-flow-list' });
            expect((route?.meta as { privilege?: string } | undefined)?.privilege).toBe('approval_flow.viewer');
            expect(module?.manifest.navigation).toContainEqual(
                expect.objectContaining({
                    id: `sw-approval-flow-${view}`,
                    path: `sw.approval.flow.${view}`,
                    privilege: 'approval_flow.viewer',
                }),
            );
        }
        expect(module?.manifest.navigation).toContainEqual({ id: 'sw-approval-flow', moduleType: 'core' });
        expect((module?.routes.get('sw.approval.flow.detail')?.meta as { privilege?: string } | undefined)?.privilege).toBe(
            'approval_flow.viewer',
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
            expect(privileges.getPrivilegeRole('approval_flow.viewer')?.privileges).toEqual(['approval_flow:access']);
        } finally {
            service.mockRestore();
        }
    });
});
