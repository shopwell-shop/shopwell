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
    it('keeps navigation within the shared system group', () => {
        expect(
            module.manifest.navigation?.filter((entry) => entry.path).every((entry) => entry.parent === 'sw-system'),
        ).toBe(true);
    });
});
