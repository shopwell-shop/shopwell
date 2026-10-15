/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';

const loyaltyTierRepository = {
    create: () => {
        return {
            id: '',
            name: '',
            technicalName: '',
            level: 1,
            minGrowthValue: 0,
            pointsMultiplier: 1,
            discountPercent: 0,
            freeShipping: false,
            active: true,
            isNew: () => true,
        };
    },

    get: () => {
        return Promise.resolve({
            id: '1',
            name: 'Gold',
            technicalName: 'gold',
            level: 2,
            minGrowthValue: 1000,
            pointsMultiplier: 1.5,
            discountPercent: 5,
            freeShipping: true,
            active: true,
            isNew: () => false,
        });
    },

    save: jest.fn(() => Promise.resolve({})),
};

async function createWrapper(privileges: string[] = []) {
    return mount(
        await wrapTestComponent('sw-loyalty-tier-detail', {
            sync: true,
        }),
        {
            props: {
                loyaltyTierId: '1',
            },
            global: {
                mocks: {
                    $route: { query: '' },
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`,
                    },
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'mt-card': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-language-info': true,
                    'sw-language-switch': true,
                    'sw-button-process': true,
                    'sw-entity-single-select': true,
                    'mt-text-field': true,
                    'mt-number-field': true,
                    'mt-switch': true,
                    'mt-textarea': true,
                    'sw-skeleton': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (name: string) => {
                            if (name === 'loyalty_tier') {
                                return loyaltyTierRepository;
                            }

                            throw new Error(`No repository for ${name} configured`);
                        },
                    },
                    acl: {
                        can: (identifier: string) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                },
            },
        },
    );
}

describe('src/module/sw-loyalty/page/sw-loyalty-tier-detail', () => {
    beforeEach(() => {
        loyaltyTierRepository.save.mockClear();
    });

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to save without editor permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.sw-loyalty-tier-detail__save');

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(wrapper.vm.allowSave).toBe(false);
    });

    it('should be able to save with editor permission', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        const saveButton = wrapper.find('.sw-loyalty-tier-detail__save');

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(wrapper.vm.allowSave).toBe(true);
    });

    it('should show the save shortcut tooltip with editor permission', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light',
        });
    });

    it('should show the permission warning tooltip without editor permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'sw-privileges.tooltip.warning',
            disabled: false,
            showOnDisabledElements: true,
        });
    });

    it('should load the loyalty tier on creation', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.loyaltyTier).toBeTruthy();
        expect(wrapper.vm.loyaltyTier.technicalName).toBe('gold');
    });

    it('should persist the loyalty tier and reload it on save', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        const onLoadLoyaltyTierSpy = jest.spyOn(wrapper.vm, 'loadLoyaltyTier');
        await wrapper.find('.sw-loyalty-tier-detail__save').trigger('click');
        await flushPromises();

        expect(loyaltyTierRepository.save).toHaveBeenCalledTimes(1);
        expect(onLoadLoyaltyTierSpy).toHaveBeenCalled();
    });
});
