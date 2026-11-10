/**
 * @sw-package discovery
 */
import { getBlogDefaultLayoutId } from './blog-layout.util';

describe('Blog layout presentation', () => {
    it('prefers the per-type default and falls back to the generic Blog default', () => {
        const values = {
            'core.content_system.default_blog_post_content_layout': 'post-layout',
            'core.content_system.default_blog_content_layout': 'generic-layout',
            'core.content_system.default_blog_listing_content_layout': 'listing-layout',
        };
        expect(getBlogDefaultLayoutId(values, 'post')).toBe('post-layout');
        expect(getBlogDefaultLayoutId(values, 'video')).toBe('generic-layout');
        expect(getBlogDefaultLayoutId(values, 'listing')).toBe('listing-layout');
        expect(getBlogDefaultLayoutId({}, 'post')).toBeNull();
    });
});
