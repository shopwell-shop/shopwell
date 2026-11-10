/**
 * @sw-package discovery
 */
import PrivilegesService from 'src/app/service/privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

describe('Blog layout permissions', () => {
    beforeEach(() => {
        jest.resetModules();
        jest.requireActual('./index');
    });

    it('lets viewers resolve default layouts without granting configuration writes', () => {
        const viewer = Shopwell.Service('privileges').getPrivilegeRole('blog.viewer');

        expect(viewer?.privileges).toContain('system_config:read');
        expect(viewer?.privileges).not.toContain('system_config:update');
    });
});
