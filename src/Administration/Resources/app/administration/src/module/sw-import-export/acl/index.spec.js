/**
 * @sw-package fundamentals@after-sales
 */

const addPrivilegeMappingEntryMock = jest.fn();

const originalShopwellService = Shopwell.Service;

describe('src/module/sw-import-export/acl/index.js', () => {
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
        const basicInformation = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
        };

        expect(addPrivilegeMappingEntryMock).toHaveBeenNthCalledWith(1, {
            ...basicInformation,
            roles: expect.any(Object),
        });
    });

    it('should register privilege roles', () => {
        const roles = {
            import_export: {
                privileges: [
                    'import_export_log:read',
                    'import_export_file:read',
                    'import_export_file:create',
                    'import_export_file:update',
                    'user:read',
                    'import_export_profile:read',
                    'import_export_profile:create',
                    'import_export_profile:delete',
                    'currency:read',
                ],
                dependencies: [],
            },
        };

        expect(addPrivilegeMappingEntryMock).toHaveBeenNthCalledWith(
            1,
            expect.objectContaining({
                roles,
            }),
        );
    });
});
