/**
 * @sw-package fundamentals@framework
 */

const addPrivilegeMappingEntryMock = jest.fn();

const originalShopwellService = Shopwell.Service;

describe('src/module/sw-system/acl/index.js', () => {
    beforeAll(() => {
        Shopwell.Service = () => {
            return {
                addPrivilegeMappingEntry: addPrivilegeMappingEntryMock,
            };
        };
    });

    beforeEach(async () => {
        jest.resetAllMocks();
        jest.resetModules();

        await import('./index');
    });

    afterAll(() => {
        Shopwell.Service = originalShopwellService;
    });

    it('should register privilege mapping entry', () => {
        expect(addPrivilegeMappingEntryMock).toHaveBeenNthCalledWith(1, {
            category: 'permissions',
            parent: 'system',
            key: 'users_and_permissions',
            roles: expect.any(Object),
        });
    });

    it('should include read privileges needed by the users and permissions viewer pages', () => {
        expect(addPrivilegeMappingEntryMock).toHaveBeenCalledTimes(2);

        const registeredRoles = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        expect(registeredRoles.viewer.privileges).toEqual(
            expect.arrayContaining(['api_acl_privileges_additional_get', 'media_folder:read']),
        );
    });

    it('should register the navigation group next to the user group', () => {
        expect(addPrivilegeMappingEntryMock).toHaveBeenNthCalledWith(2, {
            category: 'permissions',
            parent: 'system',
            key: 'admin_menu',
            roles: expect.any(Object),
        });
    });

    it('should gate every navigation role behind the read privilege', () => {
        const roles = addPrivilegeMappingEntryMock.mock.calls[1][0].roles;

        expect(roles.viewer.privileges).toEqual(['admin_menu_item:read', 'acl_role:read']);
        expect(roles.editor.privileges).toEqual([
            'admin_menu_item:update',
            'admin_menu_item_role:create',
            'admin_menu_item_role:delete',
        ]);
        expect(roles.creator.privileges).toEqual(['admin_menu_item:create']);
        expect(roles.deleter.privileges).toEqual(['admin_menu_item:delete']);
        expect(roles.editor.dependencies).toEqual(['admin_menu.viewer']);
        expect(roles.creator.dependencies).toEqual(['admin_menu.viewer', 'admin_menu.editor']);
        expect(roles.deleter.dependencies).toEqual(['admin_menu.viewer']);
    });
});
