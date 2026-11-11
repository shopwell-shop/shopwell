/** @sw-package fundamentals@framework */
import PrivilegesService from 'src/app/service/privileges.service';

describe('sw-user ACL boundary', () => {
    it('allows its own edits without granting writes to the other responsibility', async () => {
        const privileges = new PrivilegesService();
        /** @private */
        Shopwell.Service().register('privileges', () => privileges);
        await import('./index');
        const mapped = privileges.getPrivilegesForAdminPrivilegeKeys(['user.editor']);
        expect(mapped).toContain('user:update');
        expect(mapped).toEqual(
            expect.arrayContaining([
                'organization_unit:read',
                'position:read',
                'user_organization_unit:read',
                'user_organization_unit:create',
                'user_organization_unit:update',
                'user_organization_unit:delete',
            ]),
        );
        expect(mapped).not.toContain('organization_unit:update');
        expect(mapped).not.toContain('position:update');
        expect(mapped).not.toContain('acl_role:update');
    });
});
