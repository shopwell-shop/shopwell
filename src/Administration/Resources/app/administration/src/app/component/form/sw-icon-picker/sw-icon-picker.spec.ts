/** @sw-package framework */
import MtButton from '@shopwell-ag/meteor-component-library/dist/esm/MtButton';
import MtSearch from '@shopwell-ag/meteor-component-library/dist/esm/MtSearch';
import { mount } from '@vue/test-utils';
import picker from './index';

function createWrapper() {
    return mount(picker, {
        props: { modelValue: 'regular-folder', label: 'Icon' },
        global: {
            stubs: {
                'mt-button': MtButton,
                'mt-icon': true,
                'mt-search': MtSearch,
                'mt-select': {
                    props: ['modelValue', 'options', 'label'],
                    template: `<select :value="modelValue" @change="$emit('update:model-value', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
                },
                'mt-modal-root': { props: ['isOpen'], template: '<div v-if="isOpen"><slot /></div>' },
                'mt-modal': { template: '<section><slot /><slot name="footer" /></section>' },
            },
        },
    });
}

describe('menu refinements', () => {
    beforeEach(() => {
        Object.assign(globalThis, {
            __METEOR_ICON_NAMES__: [
                'regular-folder',
                'regular-link',
                'solid-folder',
                ...Array.from({ length: 200 }, (_, index) => `regular-test-${index}`),
            ],
        });
    });

    it('opens the catalog, filters styles and emits a selected icon without mutating the value', async () => {
        const wrapper = createWrapper();
        await wrapper.get('.sw-icon-picker__trigger').trigger('click');
        expect(wrapper.findAll('.sw-icon-picker__icon')).toHaveLength(96);
        await wrapper.get('select').setValue('solid');
        expect(wrapper.findAll('.sw-icon-picker__icon')).toHaveLength(1);
        await wrapper.get('.sw-icon-picker__icon').trigger('click');
        expect(wrapper.emitted('update:model-value')).toEqual([['solid-folder']]);
        expect(wrapper.props('modelValue')).toBe('regular-folder');
        wrapper.unmount();
    });

    it('loads more icons and cancels without changing the selection', async () => {
        const wrapper = createWrapper();
        await wrapper.get('.sw-icon-picker__trigger').trigger('click');
        await wrapper.get('.sw-icon-picker__more').trigger('click');
        expect(wrapper.findAll('.sw-icon-picker__icon')).toHaveLength(192);

        await wrapper.get('.sw-icon-picker__footer .mt-button--secondary').trigger('click');
        expect(wrapper.find('.sw-icon-picker__grid').exists()).toBe(false);
        expect(wrapper.emitted('update:model-value')).toBeUndefined();
        wrapper.unmount();
    });

    it('searches the catalog and restores the default icon through the footer action', async () => {
        const wrapper = createWrapper();
        await wrapper.get('.sw-icon-picker__trigger').trigger('click');
        await wrapper.get('input[type="search"]').setValue('folder');
        expect(wrapper.findAll('.sw-icon-picker__icon').map((icon) => icon.attributes('aria-label'))).toEqual([
            'regular-folder',
            'solid-folder',
        ]);

        await wrapper.get('.sw-icon-picker__footer .mt-button--primary').trigger('click');
        expect(wrapper.emitted('update:model-value')).toEqual([['']]);
        expect(wrapper.find('.sw-icon-picker__grid').exists()).toBe(false);
        wrapper.unmount();
    });
});
