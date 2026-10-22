/**
 * @sw-package discovery
 */
import 'src/module/sw-cms/service/cms.service';
import './index';

describe('src/module/sw-cms/elements/blog-listing/index.ts', () => {
    it('registers the component, the config and the preview', () => {
        const components = Shopwell.Component.getComponentRegistry();

        expect(components.has('sw-cms-el-blog-listing')).toBe(true);
        expect(components.has('sw-cms-el-config-blog-listing')).toBe(true);
        expect(components.has('sw-cms-el-preview-blog-listing')).toBe(true);
    });

    it('registers the element under the name the storefront resolves', () => {
        const registry = Shopwell.Service('cmsService').getCmsElementRegistry();

        expect(Object.keys(registry)).toContain('blog-listing');
        expect(registry['blog-listing'].label).toBe('sw-cms.elements.blogListing.label');
        expect(registry['blog-listing'].component).toBe('sw-cms-el-blog-listing');
        expect(registry['blog-listing'].configComponent).toBe('sw-cms-el-config-blog-listing');
        expect(registry['blog-listing'].previewComponent).toBe('sw-cms-el-preview-blog-listing');
    });

    it('is offered on the two blog layouts as well as on a shop page and a landing page', () => {
        const registry = Shopwell.Service('cmsService').getCmsElementRegistry();

        expect(registry['blog-listing'].allowedPageTypes).toEqual([
            Shopwell.Constants.CMS.PAGE_TYPES.BLOG_LISTING,
            Shopwell.Constants.CMS.PAGE_TYPES.BLOG_DETAIL,
            Shopwell.Constants.CMS.PAGE_TYPES.SHOP,
            Shopwell.Constants.CMS.PAGE_TYPES.LANDING,
        ]);
    });

    it('starts every new element with the classic arrangement', () => {
        const registry = Shopwell.Service('cmsService').getCmsElementRegistry();

        expect(registry['blog-listing'].defaultConfig).toEqual({
            layout: {
                source: 'static',
                value: 'classic',
            },
        });
    });
});
