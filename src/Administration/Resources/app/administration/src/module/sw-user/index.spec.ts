/** @sw-package fundamentals@framework */
import './index';
jest.mock('./acl', () => ({}));

describe('sw-user module boundary', () => {
    type Meta = { privilege?: string };
    const module = Shopwell.Module.getModuleRegistry().get('sw-user')!;
    it('owns its entity and routes without registering the other responsibility', () => {
        expect(module.manifest.entity).toBe('user');
        expect((module.routes?.get('sw.user.index')?.meta as Meta)?.privilege).toBe('user.viewer');
        expect((module.routes?.get('sw.user.detail')?.meta as Meta)?.privilege).toBe('user.viewer');
        expect((module.routes?.get('sw.user.create')?.meta as Meta)?.privilege).toBe('user.creator');
        expect((module.routes?.get('sw.user.sso.detail')?.meta as Meta)?.privilege).toBe('user.viewer');
        expect(module.routes?.has('sw.permissions.role')).toBe(false);
    });
    it('keeps navigation within the shared system group', () => {
        expect(
            module.manifest.navigation?.filter((entry) => entry.path).every((entry) => entry.parent === 'sw-system'),
        ).toBe(true);
    });
});
