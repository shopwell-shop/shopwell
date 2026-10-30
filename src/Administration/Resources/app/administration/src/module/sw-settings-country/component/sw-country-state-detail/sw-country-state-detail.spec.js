/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = [], level = 'state', countryState = { isNew: () => false }) {
    return mount(
        await wrapTestComponent('sw-country-state-detail', {
            sync: true,
        }),
        {
            props: {
                countryState,
                level,
            },

            global: {
                renderStubDefaultSlot: true,
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                },

                stubs: {
                    'sw-modal': {
                        template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                    },
                    'sw-container': true,
                    'sw-empty-state': true,
                },
            },
        },
    );
}

describe('module/sw-settings-country/component/sw-country-state-detail', () => {
    it('should be able to create a new country state', async () => {
        const wrapper = await createWrapper(['country.editor']);
        await flushPromises();

        const saveButton = wrapper.find('.sw-country-state-detail__save-button');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new country state', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.sw-country-state-detail__save-button');

        expect(saveButton.attributes('disabled')).toBeDefined();
    });

    it('should be able to edit a country state', async () => {
        const wrapper = await createWrapper(['country.editor']);
        await flushPromises();

        const saveButton = wrapper.find('.sw-country-state-detail__save-button');
        const countryStateNameField = wrapper.find('[aria-label="sw-country-state-detail.labelName"]');
        const countryStateShortCodeField = wrapper.find('[aria-label="sw-country-state-detail.labelShortCode"]');
        const countryStatePositionField = wrapper.findByLabel('sw-country-state-detail.labelPosition');

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(countryStateNameField.attributes('disabled')).toBeUndefined();
        expect(countryStateShortCodeField.attributes('disabled')).toBeUndefined();
        expect(countryStatePositionField.attributes('disabled')).toBeUndefined();
    });

    it('should not be able to edit a country state', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.sw-country-state-detail__save-button');
        const countryStateNameField = wrapper.find('[aria-label="sw-country-state-detail.labelName"]');
        const countryStateShortCodeField = wrapper.find('[aria-label="sw-country-state-detail.labelShortCode"]');
        const countryStatePositionField = wrapper.findByLabel('sw-country-state-detail.labelPosition');

        expect(saveButton.attributes('disabled')).toBeDefined();
        expect(countryStateNameField.attributes('disabled')).toBeDefined();
        expect(countryStateShortCodeField.attributes('disabled')).toBeDefined();
        expect(countryStatePositionField.attributes('disabled')).toBeDefined();
    });

    it('edits the administrative division code on the city and district level', async () => {
        const city = await createWrapper(['country.editor'], 'city');

        expect(city.vm.codeProperty).toBe('code');
        expect(city.vm.codeLabel).toBe('sw-country-state-detail.labelCode');

        const district = await createWrapper(['country.editor'], 'district');

        expect(district.vm.codeProperty).toBe('code');
        expect(district.vm.codeLabel).toBe('sw-country-state-detail.labelCode');
    });

    it('edits the ISO short code on the state level', async () => {
        const wrapper = await createWrapper(['country.editor'], 'state');

        expect(wrapper.vm.codeProperty).toBe('shortCode');
        expect(wrapper.vm.codeLabel).toBe('sw-country-state-detail.labelShortCode');
    });

    it('names the dialog after the level that is edited', async () => {
        const newCity = await createWrapper(['country.editor'], 'city', { isNew: () => true });

        expect(newCity.vm.modalTitle).toBe('sw-country-state-detail.titleNewCity');

        const existingDistrict = await createWrapper(['country.editor'], 'district', { isNew: () => false });

        expect(existingDistrict.vm.modalTitle).toBe('sw-country-state-detail.titleEditDistrict');
    });

    it('emits the region on save and cancel', async () => {
        const countryState = { id: 'region-id', isNew: () => false };
        const wrapper = await createWrapper(['country.editor'], 'state', countryState);

        wrapper.vm.onSave();
        wrapper.vm.onCancel();

        expect(wrapper.emitted('attribute-edit-save')).toEqual([[countryState]]);
        expect(wrapper.emitted('attribute-edit-cancel')).toEqual([[countryState]]);
    });
});
