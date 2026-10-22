/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(layout = 'classic') {
    return mount(await wrapTestComponent('sw-cms-el-config-blog-listing', { sync: true }), {
        props: {
            element: {
                id: 'element-1',
                type: 'blog-listing',
                config: {
                    layout: {
                        source: 'static',
                        value: layout,
                    },
                },
            },
        },
        global: {
            provide: {
                cmsService: Shopwell.Service('cmsService'),
            },
            stubs: {
                'sw-cms-inherit-wrapper': {
                    template: '<div><slot :isInherited="false"></slot></div>',
                },
                'mt-select': {
                    props: ['modelValue', 'options', 'disabled'],
                    emits: ['update:model-value'],
                    template: '<div class="mt-select"></div>',
                },
            },
            mocks: {
                $t: (key) => key,
            },
        },
    });
}

describe('src/module/sw-cms/elements/blog-listing/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/blog-listing');
    });

    it('offers the three arrangements as the only choice', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.layoutOptions.map((option) => option.value)).toEqual(['classic', 'grid', 'list']);
        expect(wrapper.vm.layoutOptions.map((option) => option.label)).toEqual([
            'sw-cms.elements.blogListing.config.label.layoutClassic',
            'sw-cms.elements.blogListing.config.label.layoutGrid',
            'sw-cms.elements.blogListing.config.label.layoutList',
        ]);
    });

    it('shows the arrangement the element was saved with', async () => {
        const wrapper = await createWrapper('list');

        expect(wrapper.vm.layout).toBe('list');
    });

    it('writes the picked arrangement into the element and asks the page to save it', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onLayoutChange('grid');

        expect(wrapper.vm.element.config.layout.value).toBe('grid');
        expect(wrapper.emitted('element-update')).toHaveLength(1);
    });
});
