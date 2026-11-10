/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import type CriteriaType from 'src/core/data/criteria.data';
import BlogApiService from '../../../service/blog.api.service';

type Category = {
    id: string;
    name: string;
    parentId: string | null;
    afterCategoryId: string | null;
    active: boolean;
    path: string | null;
    childCount: number;
};

/** @private */
export const categories: Category[] = [
    { id: 'guides', name: 'Guides', parentId: null, afterCategoryId: null, active: true, path: null, childCount: 1 },
    { id: 'news', name: 'News', parentId: null, afterCategoryId: 'guides', active: true, path: null, childCount: 0 },
    {
        id: 'care',
        name: 'Product care',
        parentId: 'guides',
        afterCategoryId: null,
        active: true,
        path: '|guides|',
        childCount: 1,
    },
    {
        id: 'washing',
        name: 'Washing',
        parentId: 'care',
        afterCategoryId: null,
        active: true,
        path: '|guides|care|',
        childCount: 0,
    },
];

/** @private */
export async function createWrapper(
    options: {
        selectedId?: string;
        canEdit?: boolean;
        beforeStructureChange?: () => Promise<boolean>;
        allCategories?: Category[];
    } = {},
) {
    const allCategories = options.allCategories ?? categories;
    const search = jest.fn((criteria: CriteriaType) =>
        Promise.resolve(
            Object.assign(
                allCategories
                    .slice(((criteria.page ?? 1) - 1) * 100, (criteria.page ?? 1) * 100)
                    .map((category) => ({ ...category })),
                { total: allCategories.length },
            ),
        ),
    );
    const save = jest.fn<Promise<void>, [unknown, unknown]>(() => Promise.resolve());
    const moveCategory = jest.fn<Promise<void>, [string, string | null, string | null]>(() => Promise.resolve());
    const selectedId = options.selectedId ?? 'washing';
    /** @private */
    Shopwell.Component.register('sw-blog-category-navigation', () => import('../index'));
    /** @private */
    Shopwell.Component.register('sw-blog-category-tree', () => import('../tree'));
    const wrapper = mount(await Shopwell.Component.build('sw-blog-category-navigation'), {
        props: { selectedId, beforeStructureChange: options.beforeStructureChange ?? (() => Promise.resolve(true)) },
        global: {
            provide: {
                repositoryFactory: { create: () => ({ search, save, create: () => ({ id: 'created' }) }) },
                acl: { can: () => options.canEdit ?? true },
                blogApiService: Object.assign(new BlogApiService(), { moveCategory }),
                feature: { isActive: () => false },
            },
            stubs: {
                'sw-blog-category-tree': await Shopwell.Component.build('sw-blog-category-tree'),
                'mt-text-field': {
                    props: ['modelValue', 'size', 'disabled'],
                    template:
                        '<section><slot name="prefix"/><input :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)"><slot name="suffix"/></section>',
                },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-modal-root': { emits: ['change'], template: '<div><slot /></div>' },
                'mt-modal': { template: '<div><slot /><slot name="footer" /></div>' },
                'mt-dropdown-menu-root': { template: '<div><slot /></div>' },
                'mt-dropdown-menu-trigger': { template: '<span><slot /></span>' },
                'mt-dropdown-menu-portal': { template: '<div><slot /></div>' },
                'mt-action-menu': { template: '<div><slot /></div>' },
                'mt-action-menu-item': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-select': {
                    props: ['modelValue', 'options'],
                    template:
                        '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
                },
                'mt-loader': true,
                'mt-icon': true,
            },
            mocks: {
                $route: { params: { id: selectedId } },
                $t: (key: string, params?: { name?: string }) => `${key}${params?.name ? ` ${params.name}` : ''}`,
                $te: () => true,
            },
        },
    });
    await flushPromises();
    return { wrapper, search, save, moveCategory };
}
