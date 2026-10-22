/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-blog-listing', {
            sync: true,
        }),
    );
}

describe('src/module/sw-cms/elements/blog-listing/preview', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/blog-listing');
    });

    it('renders a strip of three cards as the thumbnail of the element', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findAll('.sw-cms-el-preview-blog-listing__item')).toHaveLength(3);
        expect(wrapper.findAll('.sw-cms-el-preview-blog-listing__cover')).toHaveLength(3);
    });
});
