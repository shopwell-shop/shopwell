/** @sw-package fundamentals@framework */
import './index';
jest.mock('./acl', () => ({}));

describe('sw-permissions module boundary', () => {
    type Meta = { privilege?: string };
    const module = Shopwell.Module.getModuleRegistry().get('sw-permissions')!;
    it('owns its entity and routes without registering the other responsibility', () => {
        expect(module.manifest.entity).toBe('acl_role');
        expect((module.routes?.get('sw.permissions.role')?.meta as Meta)?.privilege).toBe('permissions.viewer');
        expect((module.routes?.get('sw.permissions.role.detail')?.meta as Meta)?.privilege).toBe('permissions.viewer');
        expect((module.routes?.get('sw.permissions.menu')?.meta as Meta)?.privilege).toBe('admin_menu.viewer');
        expect(module.routes?.has('sw.user.index')).toBe(false);
    });
    it('routes creation to the list dialog and existing roles to permissions', () => {
        const redirect = module.routes?.get('sw.permissions.role.detail')?.redirect as (route: {
            params: Record<string, string>;
            query: Record<string, string>;
        }) => unknown;
        expect(redirect({ params: {}, query: { copyFrom: 'source' } })).toEqual({
            name: 'sw.permissions.role',
            params: {},
            query: { create: 'true', copyFrom: 'source' },
        });
        expect(redirect({ params: { id: 'role' }, query: {} })).toEqual({
            name: 'sw.permissions.role.detail.menus',
            params: { id: 'role' },
            query: {},
        });
    });

    it('keeps old basic information links usable through a redirect', () => {
        const redirect = module.routes?.get('sw.permissions.role.detail.general')?.redirect as (route: {
            params: Record<string, string>;
            query: Record<string, string>;
        }) => unknown;
        expect(redirect({ params: { id: 'role' }, query: {} })).toEqual({
            name: 'sw.permissions.role.detail.menus',
            params: { id: 'role' },
            query: {},
        });
    });

    it('keeps roles and menus under the original System root', () => {
        const navigation = module.manifest.navigation;
        expect(navigation?.find((entry) => entry.id === 'sw-permissions-role')?.parent).toBe('sw-system');
        expect(navigation?.find((entry) => entry.id === 'sw-permissions-menu')?.parent).toBe('sw-system');
    });
});
