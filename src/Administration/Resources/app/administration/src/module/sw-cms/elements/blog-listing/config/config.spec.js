/**
 * @sw-package discovery
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(layout = 'classic') {
    return shallowMount(await wrapTestComponent('sw-cms-el-config-blog-listing', { sync: true }), {
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
                    template: `<select :value="modelValue" :disabled="disabled" @change="$emit('update:model-value', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
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

        expect(wrapper.findAll('option').map((option) => option.attributes('value'))).toEqual(['classic', 'grid', 'list']);
        expect(wrapper.findAll('option').map((option) => option.text())).toEqual([
            'sw-cms.elements.blogListing.config.label.layoutClassic',
            'sw-cms.elements.blogListing.config.label.layoutGrid',
            'sw-cms.elements.blogListing.config.label.layoutList',
        ]);
    });

    it('shows the arrangement the element was saved with', async () => {
        const wrapper = await createWrapper('list');

        expect(wrapper.get('select').element.value).toBe('list');
    });

    it('writes the picked arrangement into the element and asks the page to save it', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('select').setValue('grid');

        expect(wrapper.get('select').element.value).toBe('grid');
        expect(wrapper.emitted('element-update')[0][0].config.layout.value).toBe('grid');
        expect(wrapper.emitted('element-update')).toHaveLength(1);
    });
});
