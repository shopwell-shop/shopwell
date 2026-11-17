/** @sw-package framework */
import PrivilegesService from 'src/app/service/privileges.service';

describe('workflow user read privilege', () => {
    it('allows workflow viewers to resolve approvers without granting user writes', async () => {
        /** @private */
        Shopwell.Service().register('privileges', () => new PrivilegesService());
        await import('./index');
        const privileges = Shopwell.Service('privileges');
        expect(privileges.getPrivilegeRole('workflow.viewer')?.privileges).toContain('user:read');
        expect(privileges.getPrivilegeRole('workflow.viewer')?.privileges).not.toContain('user:update');
        expect(privileges.getPrivilegeRole('workflow.editor')?.dependencies).toContain('workflow.viewer');
        expect(privileges.getPrivilegeRole('workflow_participant.viewer')?.privileges).toEqual(['workflow_instance:access']);
        expect(privileges.getPrivilegeRole('workflow_participant.creator')?.privileges).toEqual([
            'workflow_instance:start',
            'workflow:read',
        ]);
        expect(privileges.getPrivilegeRole('workflow_participant.approver')?.privileges).toEqual([
            'workflow_task:decide',
            'user:read',
        ]);
    });
});
