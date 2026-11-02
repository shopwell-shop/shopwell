/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type FieldOverrides = Record<string, unknown>;

/**
 * One field of an interface, the way the server sends it: `payment-provider.xml` turned into json.
 */
function buildField(overrides: FieldOverrides = {}): Record<string, unknown> {
    return {
        name: 'app_id',
        label: '支付宝应用 APPID',
        type: 'text',
        required: true,
        secret: false,
        options: {},
        ...overrides,
    };
}

/**
 * The text-like inputs of the library, stubbed down to the attributes the page sets on them.
 */
const TEXT_FIELD = {
    props: [
        'modelValue',
        'label',
        'placeholder',
        'helpText',
        'required',
        'disabled',
        'name',
    ],
    emits: ['update:modelValue'],
    template: `<input
        :data-label="label"
        :data-placeholder="placeholder"
        :data-help="helpText"
        :data-name="name"
        :disabled="disabled"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    />`,
};

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    overrides: FieldOverrides = {},
    mocks: Record<string, unknown> = { $te: () => false },
    modelValue = '',
): Promise<VueWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-payment-config-field', { sync: true }), {
        props: {
            field: buildField(overrides),
            modelValue,
        },
        global: {
            stubs: {
                'mt-text-field': TEXT_FIELD,
                'mt-password-field': TEXT_FIELD,
                'mt-textarea': TEXT_FIELD,
                'mt-select': {
                    name: 'MtSelect',
                    props: [
                        'modelValue',
                        'label',
                        'options',
                        'placeholder',
                        'helpText',
                    ],
                    template: '<select class="select"></select>',
                },
                'mt-switch': {
                    props: ['modelValue', 'label', 'helpText'],
                    emits: ['update:modelValue'],
                    template:
                        '<button class="switch" :data-checked="modelValue" @click="$emit(\'update:modelValue\', !modelValue)"></button>',
                },
                'mt-number-field': {
                    props: ['modelValue', 'label'],
                    emits: ['update:modelValue'],
                    template:
                        '<input class="number-field" :value="modelValue" @input="$emit(\'update:modelValue\', Number($event.target.value))" />',
                },
            },
            mocks,
        },
    });

    activeWrapper = wrapper;

    await flushPromises();

    return wrapper;
}

describe('module/sw-payment/component/sw-payment-config-field', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should render the label the interface declared', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('input').attributes('data-label')).toBe('支付宝应用 APPID');
    });

    it('should translate a label that is a snippet key', async () => {
        const wrapper = await createWrapper(
            { label: 'some.snippet.key' },
            {
                $te: (key: string) => key === 'some.snippet.key',
                $t: (key: string) => `translated:${key}`,
            },
        );

        expect(wrapper.find('input').attributes('data-label')).toBe('translated:some.snippet.key');
    });

    it('should fall back to the name of the field when it carries no label', async () => {
        const wrapper = await createWrapper({ name: 'mch_id', label: null });

        expect(wrapper.find('input').attributes('data-label')).toBe('mch_id');
    });

    it('should show the hint the interface declared next to the field', async () => {
        const wrapper = await createWrapper({ helpText: 'some.help.key' });

        expect(wrapper.find('input').attributes('data-help')).toBe('some.help.key');
    });

    it('should not invent a hint for a secret field that already carries a value', async () => {
        const wrapper = await createWrapper({ name: 'private_key', type: 'textarea', secret: true });

        // the value is part of the form, so "configured" would only repeat what the input shows
        expect(wrapper.find('input').attributes('data-help')).toBeUndefined();
    });

    it('should map the options of a select field to the shape of the component', async () => {
        const wrapper = await createWrapper({ name: 'sign_type', type: 'select', options: { RSA2: 'RSA2', RSA: 'RSA' } });

        expect(wrapper.findComponent({ name: 'MtSelect' }).props('options')).toEqual([
            { value: 'RSA2', label: 'RSA2' },
            { value: 'RSA', label: 'RSA' },
        ]);
    });

    it('should show the default the interface declared as a placeholder', async () => {
        const wrapper = await createWrapper({ name: 'sign_type', type: 'select', default: 'RSA2' });

        expect(wrapper.findComponent({ name: 'MtSelect' }).props('placeholder')).toBe('RSA2');
    });

    it('should not show a placeholder for a field without a default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('input').attributes('data-placeholder')).toBeUndefined();
    });

    it('should hand the typed value to the form', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('input').setValue('2021000000000000');

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['2021000000000000']);
    });

    it('should speak booleans for a switch and store them as a string', async () => {
        const wrapper = await createWrapper({ name: 'sandbox', type: 'bool' }, { $te: () => false }, '1');

        const toggle = wrapper.find('.switch');

        expect(toggle.attributes('data-checked')).toBe('true');

        await toggle.trigger('click');

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['']);
    });

    it('should speak numbers for a number field and store them as a string', async () => {
        const wrapper = await createWrapper({ name: 'timeout', type: 'number' }, { $te: () => false }, '30');

        await wrapper.find('.number-field').setValue(60);

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['60']);
    });

    /**
     * The fields hold api secrets, not login credentials: without the hint the password manager of the
     * browser would offer a saved user name for an unrelated input.
     */
    it('should keep the browser from treating the fields as a login form', async () => {
        const password = await createWrapper({ name: 'secret_key', type: 'password' });
        expect(password.find('input').attributes('autocomplete')).toBe('new-password');

        const plain = await createWrapper();
        expect(plain.find('input').attributes('autocomplete')).toBe('off');
    });

    it('should render the input the declared type asks for', async () => {
        const types: Record<string, string> = {
            text: 'input',
            password: 'input',
            textarea: 'input',
            select: '.select',
            bool: '.switch',
            number: '.number-field',
        };

        for (const [type, selector] of Object.entries(types)) {
            const wrapper = await createWrapper({ type });

            expect(wrapper.find(selector).exists()).toBe(true);
        }
    });
});
