import { mount, type VueWrapper } from '@vue/test-utils';

async function createWrapper(allowEdit = true) {
    return mount(await wrapTestComponent('sw-experience-studio-settings-fields', { sync: true }), {
        props: {
            fields: [
                {
                    key: 'visibleFilterCount',
                    property: {
                        title: 'Visible Filter Count',
                        type: 'integer',
                        adminUI: { props: { numberType: 'int', min: 0, max: 10 } },
                    },
                },
            ],
            values: { visibleFilterCount: 3 },
            allowEdit,
        },
        global: {
            stubs: {
                'sw-collapse': { template: '<div><slot name="content" /></div>' },
            },
        },
    });
}

describe('Experience Studio real number field contract', () => {
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('commits typed input on native change instead of raw input', async () => {
        wrapper = await createWrapper();
        expect(wrapper.findComponent({ name: 'MtNumberField' }).exists()).toBe(true);
        const input = wrapper.get('input');

        await input.trigger('focus');
        input.element.value = '6';
        await input.trigger('input');
        expect(wrapper.emitted('update-field')).toBeUndefined();

        await input.trigger('keydown', { key: 'Tab' });
        await input.trigger('change');
        await input.trigger('blur');

        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'visibleFilterCount', value: 6 }]]);
    });

    it('commits numeric stepper changes immediately', async () => {
        wrapper = await createWrapper();

        await wrapper.get('[data-testid="mt-number-field-increase-button"]').trigger('click');

        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'visibleFilterCount', value: 4 }]]);
    });

    it('clamps typed input at the declared maximum before committing', async () => {
        wrapper = await createWrapper();
        const input = wrapper.get('input');

        input.element.value = '99';
        await input.trigger('input');
        await input.trigger('change');

        expect(wrapper.emitted('update-field')).toEqual([[{ key: 'visibleFilterCount', value: 10 }]]);
        expect(input.element.value).toBe('10');
    });

    it('disables native input and steppers without edit permission', async () => {
        wrapper = await createWrapper(false);

        expect(wrapper.get('input').attributes('disabled')).toBeDefined();
        const increase = wrapper.get('[data-testid="mt-number-field-increase-button"]');
        expect(increase.attributes('disabled')).toBeDefined();
        await increase.trigger('click');

        expect(wrapper.emitted('update-field')).toBeUndefined();
    });
});
