/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper({ layout = 'classic', data = {} } = {}) {
    return mount(await wrapTestComponent('sw-cms-el-blog-listing', { sync: true }), {
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
                data,
            },
        },
        global: {
            provide: {
                cmsService: Shopwell.Service('cmsService'),
                // the element must not read from the api, so a factory that refuses to hand out a
                // repository is the honest environment to mount it in
                repositoryFactory: {
                    create: () => {
                        throw new Error('the element must not create a repository');
                    },
                },
            },
            mocks: {
                $t: (key) => key,
            },
        },
    });
}

describe('src/module/sw-cms/elements/blog-listing/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/blog-listing');
    });

    it('draws schematic cards while the page has not been rendered yet', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findAll('.sw-cms-el-blog-listing__item')).toHaveLength(3);
        expect(wrapper.findAll('.sw-cms-el-blog-listing__item--placeholder')).toHaveLength(3);
    });

    it('draws as many cards as the arrangement has room for', async () => {
        const classic = await createWrapper({ layout: 'classic' });
        const grid = await createWrapper({ layout: 'grid' });
        const list = await createWrapper({ layout: 'list' });

        expect(classic.findAll('.sw-cms-el-blog-listing__item')).toHaveLength(3);
        expect(grid.findAll('.sw-cms-el-blog-listing__item')).toHaveLength(6);
        expect(list.findAll('.sw-cms-el-blog-listing__item')).toHaveLength(3);
    });

    it('names the arrangement on the wrapper so the scss can switch on it', async () => {
        const wrapper = await createWrapper({ layout: 'grid' });

        expect(wrapper.classes()).toContain('sw-cms-el-blog-listing--grid');
    });

    it('shows the entries of a rendered page instead of placeholders', async () => {
        const wrapper = await createWrapper({
            data: {
                blogs: [
                    { id: 'blog-1', title: 'Autumn guide', teaser: 'What to read this season' },
                    { id: 'blog-2', title: 'Winter guide', teaser: null },
                ],
            },
        });

        expect(wrapper.findAll('.sw-cms-el-blog-listing__item')).toHaveLength(2);
        expect(wrapper.findAll('.sw-cms-el-blog-listing__item--placeholder')).toHaveLength(0);
        expect(wrapper.findAll('.sw-cms-el-blog-listing__title').map((title) => title.text())).toEqual([
            'Autumn guide',
            'Winter guide',
        ]);
    });

    it('leaves the teaser out when an entry has none', async () => {
        const wrapper = await createWrapper({
            data: {
                blogs: [{ id: 'blog-1', title: 'Autumn guide', teaser: null }],
            },
        });

        expect(wrapper.find('.sw-cms-el-blog-listing__teaser').exists()).toBe(false);
    });

    it('falls back to the classic arrangement when the element was saved without one', async () => {
        const wrapper = await createWrapper({ layout: null });

        expect(wrapper.vm.layout).toBe('classic');
        expect(wrapper.classes()).toContain('sw-cms-el-blog-listing--classic');
    });
});
