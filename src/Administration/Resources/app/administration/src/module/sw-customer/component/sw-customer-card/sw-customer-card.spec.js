import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const customer = {
    id: '1',
    email: null,
    boundSalesChannelId: null,
    vatIds: ['9f8f091c-db81-4ef3-862c-9c554a34cdc4'],
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-customer-card', { sync: true }), {
        props: {
            customer: {},
            title: '',
        },
        global: {
            provide: {
                dictionaryService: {
                    getGenders: jest.fn(() =>
                        Promise.resolve([
                            { technicalName: 'female', translated: { label: 'Female' } },
                            { technicalName: 'male', translated: { label: 'Male' } },
                        ]),
                    ),
                },
                contextStoreService: {},
            },
            stubs: {
                'sw-avatar': true,
                'sw-entity-single-select': true,
                'sw-text-field': true,
                'sw-page': true,
                'sw-language-switch': true,
                'sw-customer-address-form': true,
                'sw-customer-base-form': true,
                'sw-card-view': true,
                'sw-button-process': true,
                'sw-email-field': true,
                'sw-entity-tag-select': true,
                'sw-card-section': await wrapTestComponent('sw-card-section'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-single-select': true,
                'sw-customer-imitate-customer-modal': true,
                'sw-label': true,
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-loader': true,
            },
        },
    });
}

describe('module/sw-customer/page/sw-customer-card', () => {
    it('should load the gender entries without the default entry for the select', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.genderOptions).toEqual([
            { value: 'female', label: 'Female' },
            { value: 'male', label: 'Male' },
        ]);
    });

    it('should display the account type switcher', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
        });
        const accountTypeSelect = wrapper.find('.sw-customer-card__account-type-select');
        expect(accountTypeSelect.exists()).toBeTruthy();
    });

    it('should display the phone number field in edit mode', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
            customer,
        });

        expect(wrapper.find('[aria-label="sw-customer.card.labelPhoneNumber"]').exists()).toBeTruthy();
    });

    it('should vat fields when switching to business type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
            customer: {
                ...customer,
                accountType: 'business',
            },
        });
        expect(wrapper.find('[aria-label="sw-customer.card.labelCompany"]').exists()).toBeTruthy();
        expect(wrapper.find('[aria-label="sw-customer.card.labelVatId"]').exists()).toBeTruthy();
    });

    it('should hide vat fields when switching to private type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
            customer: {
                ...customer,
                accountType: 'private',
            },
        });

        expect(wrapper.find('[label="sw-customer.card.labelVatId"]').exists()).toBeFalsy();
    });
});
