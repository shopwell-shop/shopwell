/**
 * @sw-package discovery
 */
import 'src/module/sw-cms/service/cms.service';
import './index';

describe('src/module/sw-cms/elements/html/index.ts', () => {
    it('should register components correctly', () => {
        expect(Shopwell.Component.getComponentRegistry().has('sw-cms-el-html')).toBe(true);
        expect(Shopwell.Component.getComponentRegistry().has('sw-cms-el-preview-html')).toBe(true);
        expect(Shopwell.Component.getComponentRegistry().has('sw-cms-el-config-html')).toBe(true);
        expect(Object.keys(Shopwell.Service('cmsService').getCmsElementRegistry())).toContain('html');
    });
});
