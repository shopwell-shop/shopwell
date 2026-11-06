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
                        props: ['title'],
                        template:
                            '<div class="sw-modal"><h2>{{ title }}</h2><slot></slot><slot name="modal-footer"></slot></div>',
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

    it.each(['city', 'district'])('edits the administrative division code on the %s level', async (level) => {
        const region = { code: '1301', shortCode: 'CN-13', isNew: () => false };
        const wrapper = await createWrapper(['country.editor'], level, region);
        await flushPromises();

        const codeField = wrapper.find('[aria-label="sw-country-state-detail.labelCode"]');
        expect(codeField.element.value).toBe('1301');
        expect(wrapper.find('[aria-label="sw-country-state-detail.labelShortCode"]').exists()).toBe(false);
        await codeField.setValue('1302');
        await wrapper.get('.sw-country-state-detail__save-button').trigger('click');

        expect(wrapper.emitted('attribute-edit-save')[0][0]).toEqual(
            expect.objectContaining({
                code: '1302',
                shortCode: 'CN-13',
            }),
        );
    });

    it('edits the ISO short code on the state level', async () => {
        const region = { code: '1301', shortCode: 'CN-13', isNew: () => false };
        const wrapper = await createWrapper(['country.editor'], 'state', region);
        await flushPromises();

        const codeField = wrapper.find('[aria-label="sw-country-state-detail.labelShortCode"]');
        expect(codeField.element.value).toBe('CN-13');
        expect(wrapper.find('[aria-label="sw-country-state-detail.labelCode"]').exists()).toBe(false);
        await codeField.setValue('CN-14');
        await wrapper.get('.sw-country-state-detail__save-button').trigger('click');
        expect(wrapper.emitted('attribute-edit-save')[0][0]).toEqual(
            expect.objectContaining({
                code: '1301',
                shortCode: 'CN-14',
            }),
        );
    });

    it('names the dialog after the level that is edited', async () => {
        const newCity = await createWrapper(['country.editor'], 'city', { isNew: () => true });
        expect(newCity.get('h2').text()).toBe('sw-country-state-detail.titleNewCity');

        const existingDistrict = await createWrapper(['country.editor'], 'district', { isNew: () => false });
        expect(existingDistrict.get('h2').text()).toBe('sw-country-state-detail.titleEditDistrict');
    });

    it('emits the region on save and cancel', async () => {
        const countryState = { id: 'region-id', isNew: () => false };
        const wrapper = await createWrapper(['country.editor'], 'state', countryState);
        await wrapper.get('.sw-country-state-detail__save-button').trigger('click');
        const cancel = wrapper.findAll('button').find((button) => button.text() === 'global.default.cancel');
        await cancel.trigger('click');

        expect(wrapper.emitted('attribute-edit-save')).toEqual([[countryState]]);
        expect(wrapper.emitted('attribute-edit-cancel')).toEqual([[countryState]]);
    });
});
