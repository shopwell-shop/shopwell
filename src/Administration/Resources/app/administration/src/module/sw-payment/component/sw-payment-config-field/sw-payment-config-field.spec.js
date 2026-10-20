/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(props = {}, mocks = { $te: () => false }) {
    return mount(await wrapTestComponent('sw-payment-config-field', { sync: true }), {
        props: {
            field: {
                name: 'app_id',
                label: '支付宝应用 APPID',
                type: 'text',
                required: true,
                secret: false,
                options: {},
                configured: false,
            },
            modelValue: '',
            ...props,
        },
        global: {
            stubs: {
                'mt-text-field': true,
                'mt-password-field': true,
                'mt-textarea': true,
                'mt-select': true,
                'mt-switch': true,
                'mt-number-field': true,
            },
            mocks,
        },
    });
}

describe('module/sw-payment/component/sw-payment-config-field', () => {
    it('should render the label the interface declared', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.label).toBe('支付宝应用 APPID');
    });

    it('should translate a label that is a snippet key', async () => {
        const wrapper = await createWrapper(
            {
                field: { name: 'app_id', label: 'some.snippet.key', type: 'text', options: {} },
            },
            {
                $te: (key) => key === 'some.snippet.key',
                $t: (key) => `translated:${key}`,
            },
        );

        expect(wrapper.vm.label).toBe('translated:some.snippet.key');
    });

    it('should fall back to the name of the field when it carries no label', async () => {
        const wrapper = await createWrapper({
            field: { name: 'mch_id', label: null, type: 'text', options: {} },
        });

        expect(wrapper.vm.label).toBe('mch_id');
    });

    it('should show the hint the interface declared next to the field', async () => {
        const wrapper = await createWrapper({
            field: { name: 'app_id', label: 'APPID', type: 'text', helpText: 'some.help.key', options: {} },
        });

        expect(wrapper.vm.helpText).toBe('some.help.key');
    });

    it('should not invent a hint for a secret field that already carries a value', async () => {
        const wrapper = await createWrapper({
            field: { name: 'private_key', label: '应用私钥', type: 'textarea', secret: true, configured: true, options: {} },
        });

        // the value is part of the form, so "configured" would only repeat what the input shows
        expect(wrapper.vm.helpText).toBeNull();
    });

    it('should map the options of a select field to the shape of the component', async () => {
        const wrapper = await createWrapper({
            field: { name: 'sign_type', label: '签名算法', type: 'select', options: { RSA2: 'RSA2', RSA: 'RSA' } },
        });

        expect(wrapper.vm.options).toEqual([
            { value: 'RSA2', label: 'RSA2' },
            { value: 'RSA', label: 'RSA' },
        ]);
    });

    it('should show the default the interface declared as a placeholder', async () => {
        const wrapper = await createWrapper({
            field: { name: 'sign_type', label: '签名算法', type: 'select', options: { RSA2: 'RSA2' }, default: 'RSA2' },
        });

        expect(wrapper.vm.placeholder).toBe('RSA2');
    });

    it('should not show a placeholder for a field without a default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.placeholder).toBeUndefined();
    });

    it('should emit the value the input carries', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.currentValue = '2021000000000000';

        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['2021000000000000']);
    });

    it('should speak booleans for a switch and store them as a string', async () => {
        const wrapper = await createWrapper({
            field: { name: 'sandbox', label: '沙箱模式', type: 'bool', options: {} },
            modelValue: '1',
        });

        expect(wrapper.vm.currentBoolean).toBe(true);

        wrapper.vm.currentBoolean = false;
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['']);
    });

    it('should speak numbers for a number field and store them as a string', async () => {
        const wrapper = await createWrapper({
            field: { name: 'timeout', label: '超时', type: 'number', options: {} },
            modelValue: '30',
        });

        expect(wrapper.vm.currentNumber).toBe(30);

        wrapper.vm.currentNumber = 60;
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['60']);
    });

    it('should keep the browser from treating a secret field as a login password', async () => {
        const wrapper = await createWrapper({
            field: { name: 'secret_key', label: 'Secret Key', type: 'password', options: {} },
        });

        expect(wrapper.find('mt-password-field-stub').attributes('autocomplete')).toBe('new-password');
    });

    it('should opt the plain fields out of the browser autofill', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('mt-text-field-stub').attributes('autocomplete')).toBe('off');
    });
});
