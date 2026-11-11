/** @sw-package fundamentals@framework */
import PrivilegesService from 'src/app/service/privileges.service';

describe('sw-permissions ACL boundary', () => {
    it('allows its own edits without granting writes to the other responsibility', async () => {
        const privileges = new PrivilegesService();
        /** @private */
        Shopwell.Service().register('privileges', () => privileges);
        await import('./index');
        const mapped = privileges.getPrivilegesForAdminPrivilegeKeys(['permissions.editor']);
        expect(mapped).toContain('acl_role:update');
        expect(mapped).not.toContain('user:update');
    });
});
