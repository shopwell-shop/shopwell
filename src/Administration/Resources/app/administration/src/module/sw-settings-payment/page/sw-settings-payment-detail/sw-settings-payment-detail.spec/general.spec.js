/**
 * @sw-package checkout
 */
import { cleanUpWrappers, createWrapper } from './fixtures';

describe('payment method details', () => {
    afterEach(cleanUpWrappers);

    it('should be able to save the settings-payment', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const saveButton = wrapper.find('.sw-payment-detail__save-action');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should be able to edit payment fields', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const nameField = wrapper.find('.sw-settings-payment-detail__field-name');
        const positionField = wrapper.find('.sw-settings-payment-detail__field-position');
        const commentField = wrapper.find('.sw-settings-payment-detail__description');
        const uploadField = wrapper.find('.sw-settings-payment-detail__logo-image-upload');
        const description = wrapper.find('.sw-settings-payment-detail__description');
        const activeField = wrapper.find('.sw-settings-payment-detail__field-active');
        const afterOrderField = wrapper.find('.sw-settings-payment-detail__field-after-order');
        const ruleField = wrapper.find('.sw-settings-payment-detail__field-availability-rule');

        expect(nameField.attributes('disabled')).toBeUndefined();
        expect(positionField.attributes('disabled')).toBeUndefined();
        expect(commentField.attributes('disabled')).toBeUndefined();
        expect(uploadField.attributes('disabled')).toBeUndefined();
        expect(description.attributes('disabled')).toBeUndefined();
        expect(activeField.attributes('disabled')).toBeUndefined();
        expect(afterOrderField.attributes('disabled')).toBeUndefined();
        expect(ruleField.attributes('disabled')).toBeUndefined();
    });

    it('should only allow whole numbers for the position', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const positionField = wrapper.find('.sw-settings-payment-detail__field-position');

        expect(positionField.attributes('step')).toBe('1');
        expect(positionField.attributes('number-type')).toBe('int');
    });

    it('should add conditions association', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const criteria = wrapper.getComponent('.sw-settings-payment-detail__field-availability-rule').props('ruleFilter');

        expect(criteria.associations[0].association).toBe('conditions');
    });

    it('should disabled technical name input pluginId', async () => {
        const wrapper = await createWrapper([], {
            pluginId: '001',
        });

        await flushPromises();

        const technicalInput = wrapper.findComponent('.sw-settings-payment-detail__field-technical-name');

        expect(technicalInput.props().disabled).toBe(true);
    });

    it('should disabled technical name input appId', async () => {
        const wrapper = await createWrapper([], {
            appPaymentMethod: {
                id: '001',
            },
        });

        await flushPromises();

        const technicalInput = wrapper.findComponent('.sw-settings-payment-detail__field-technical-name');

        expect(technicalInput.props().disabled).toBe(true);
    });

    it('should not disabled technical name input', async () => {
        const wrapper = await createWrapper([], {
            pluginId: undefined,
        });

        await flushPromises();

        const technicalInput = wrapper.find('.sw-settings-payment-detail__field-technical-name');

        expect(technicalInput.attributes('disabled')).toBeUndefined();
    });
});
