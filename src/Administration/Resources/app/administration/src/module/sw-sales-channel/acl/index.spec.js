/**
 * @sw-package discovery
 */

const addPrivilegeMappingEntryMock = jest.fn();
const getPrivilegesMock = jest.fn((role) => [`${role}:privilege`]);

const originalShopwellService = Shopwell.Service;

describe('src/module/sw-sales-channel/acl/index.js', () => {
    beforeAll(() => {
        Shopwell.Service = () => {
            return {
                addPrivilegeMappingEntry: addPrivilegeMappingEntryMock,
                getPrivileges: getPrivilegesMock,
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
            parent: null,
            key: 'sales_channel',
            roles: expect.any(Object),
        });
    });

    it('should include sales channel file privileges for persisted agentic file settings', () => {
        expect(addPrivilegeMappingEntryMock).toHaveBeenCalledTimes(1);

        const registeredRoles = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        expect(registeredRoles.viewer.privileges).toEqual(expect.arrayContaining(['sales_channel_file:read']));
        expect(registeredRoles.editor.privileges).toEqual(
            expect.arrayContaining(['sales_channel_file:create', 'sales_channel_file:update']),
        );
    });
});
