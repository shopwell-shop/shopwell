/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const tier = {
    id: '1',
    name: 'Gold',
    technicalName: 'gold',
    level: 2,
    minTierPoints: 1000,
    active: true,
    isNew: () => false,
};

const loyaltyTierRepository = {
    get: jest.fn(() => Promise.resolve({ ...tier })),
    save: jest.fn(() => Promise.resolve()),
};

const benefitRepository = {
    search: jest.fn<Promise<ReturnType<typeof benefitRow>[]>, [BenefitCriteria]>(() => Promise.resolve([])),
    create: jest.fn(() => {
        return { id: 'new-benefit' };
    }),
    save: jest.fn<Promise<void>, [Record<string, unknown>]>(() => Promise.resolve()),
    delete: jest.fn(() => Promise.resolve()),
};

type BenefitCriteria = { filters: { field: string; value: unknown }[] };

let capturedBenefitCriteria: BenefitCriteria | null = null;

function benefitRow(id: string, type: string, value: number): { id: string; type: string; value: number } {
    return { id, type, value };
}

async function createWrapper(privileges: string[] = ['loyalty.editor']): Promise<VueWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-membership-tier-detail', { sync: true }), {
        props: {
            loyaltyTierId: '1',
        },
        global: {
            mocks: {
                $route: { query: '' },
                // the wording is not under test, only which key the page looks up
                $t: (key: string) => key,
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': { template: '<div><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-button-process': {
                    props: ['isLoading', 'disabled'],
                    // `emits` keeps the parent listener from also running as a native click handler on the
                    // button, which would call onSave twice
                    emits: ['click'],
                    template:
                        '<button class="sw-membership-tier-detail__save" :disabled="disabled" @click="$emit(\'click\')" />',
                },
                'mt-button': { template: '<button><slot></slot></button>' },
                'mt-text-field': {
                    props: ['modelValue'],
                    template:
                        '<input class="mt-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
                'mt-number-field': {
                    props: ['modelValue'],
                    template: `<input
                        class="mt-number-field"
                        :value="modelValue === null ? '' : modelValue"
                        @input="$emit('update:modelValue', $event.target.value === '' ? null : Number($event.target.value))"
                    />`,
                },
                'mt-switch': {
                    props: ['modelValue'],
                    template:
                        '<input class="mt-switch" type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
                },
                'mt-textarea': { template: '<textarea class="mt-textarea" />' },
                'sw-language-info': true,
                'sw-language-switch': true,
                'sw-entity-single-select': true,
                'sw-skeleton': true,
            },
            provide: {
                repositoryFactory: {
                    create: (name: string) => {
                        if (name === 'loyalty_tier') {
                            return loyaltyTierRepository;
                        }

                        if (name === 'loyalty_tier_benefit') {
                            return benefitRepository;
                        }

                        throw new Error(`No repository for ${name} configured`);
                    },
                },
                acl: {
                    can: (identifier: string) => privileges.includes(identifier),
                },
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('src/module/sw-settings-membership/page/sw-membership-tier-detail', () => {
    beforeEach(() => {
        capturedBenefitCriteria = null;

        loyaltyTierRepository.get.mockClear();
        loyaltyTierRepository.get.mockImplementation(() => Promise.resolve({ ...tier }));
        loyaltyTierRepository.save.mockClear();

        benefitRepository.create.mockClear();
        benefitRepository.save.mockClear();
        benefitRepository.delete.mockClear();
        benefitRepository.search.mockReset();
        benefitRepository.search.mockImplementation((criteria: BenefitCriteria) => {
            capturedBenefitCriteria = criteria;

            return Promise.resolve([]);
        });
    });

    it('shows the tier that was loaded', async () => {
        const wrapper = await createWrapper();

        expect(loyaltyTierRepository.get).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-membership-tier-detail__name').attributes('value')).toBe('Gold');
        expect(wrapper.find('.sw-membership-tier-detail__technical-name').attributes('value')).toBe('gold');
    });

    it('does not let an operator without the editor privilege save', async () => {
        const wrapper = await createWrapper([]);

        expect(wrapper.find('.sw-membership-tier-detail__save').attributes('disabled')).toBeDefined();
    });

    it('lets an operator with the editor privilege save', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);

        expect(wrapper.find('.sw-membership-tier-detail__save').attributes('disabled')).toBeUndefined();
    });

    it('asks for the benefits of the tier it loaded', async () => {
        await createWrapper();

        const filter = (capturedBenefitCriteria?.filters ?? []).find((entry) => entry.field === 'loyaltyTierId');

        expect(filter?.value).toBe('1');
    });

    it('shows what the tier grants', async () => {
        benefitRepository.search.mockImplementation((criteria: BenefitCriteria) => {
            capturedBenefitCriteria = criteria;

            return Promise.resolve([
                benefitRow('benefit-1', 'earn_multiplier', 1.5),
                benefitRow('benefit-2', 'cart_discount', 5),
                benefitRow('benefit-3', 'free_shipping', 1),
            ]);
        });

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-membership-tier-detail__earn-multiplier').attributes('value')).toBe('1.5');
        expect(wrapper.find('.sw-membership-tier-detail__cart-discount').attributes('value')).toBe('5');
        expect((wrapper.find('.sw-membership-tier-detail__free-shipping').element as HTMLInputElement).checked).toBe(true);
        expect(wrapper.find('.sw-membership-tier-detail__points-expiry-days').attributes('value')).toBe('');
    });

    it('writes the tier before it writes what the tier grants', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-membership-tier-detail__earn-multiplier').setValue('2');
        await wrapper.find('.sw-membership-tier-detail__save').trigger('click');
        await flushPromises();

        expect(loyaltyTierRepository.save).toHaveBeenCalledTimes(1);
        expect(benefitRepository.save).toHaveBeenCalledTimes(1);
        expect(loyaltyTierRepository.save.mock.invocationCallOrder[0]).toBeLessThan(
            benefitRepository.save.mock.invocationCallOrder[0],
        );
    });

    it('stores a granted benefit as a row of the benefit table', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-membership-tier-detail__earn-multiplier').setValue('2');
        await wrapper.find('.sw-membership-tier-detail__save').trigger('click');
        await flushPromises();

        const entity = benefitRepository.save.mock.calls[0][0];

        expect(entity).toMatchObject({
            loyaltyTierId: '1',
            type: 'earn_multiplier',
            value: 2,
            position: 10,
        });
    });

    it('updates the row of a benefit that is already granted instead of adding a second one', async () => {
        benefitRepository.search.mockImplementation(() => {
            return Promise.resolve([benefitRow('benefit-1', 'earn_multiplier', 1.5)]);
        });

        const wrapper = await createWrapper();

        await wrapper.find('.sw-membership-tier-detail__earn-multiplier').setValue('2');
        await wrapper.find('.sw-membership-tier-detail__save').trigger('click');
        await flushPromises();

        expect(benefitRepository.create).not.toHaveBeenCalled();
        expect(benefitRepository.save.mock.calls[0][0]).toMatchObject({ id: 'benefit-1', value: 2 });
    });

    it('removes the row when the operator sets a benefit back to its neutral value', async () => {
        benefitRepository.search.mockImplementation(() => {
            return Promise.resolve([benefitRow('benefit-2', 'cart_discount', 5)]);
        });

        const wrapper = await createWrapper();

        await wrapper.find('.sw-membership-tier-detail__cart-discount').setValue('0');
        await wrapper.find('.sw-membership-tier-detail__save').trigger('click');
        await flushPromises();

        expect(benefitRepository.delete).toHaveBeenCalledWith('benefit-2');
        expect(benefitRepository.save).not.toHaveBeenCalled();
    });

    it('never touches a benefit type the shop does not know', async () => {
        benefitRepository.search.mockImplementation(() => {
            return Promise.resolve([benefitRow('benefit-9', 'early_access', 3)]);
        });

        const wrapper = await createWrapper();

        await wrapper.find('.sw-membership-tier-detail__save').trigger('click');
        await flushPromises();

        expect(benefitRepository.delete).not.toHaveBeenCalled();
        expect(benefitRepository.save).not.toHaveBeenCalled();
    });

    it('reloads the tier after saving', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-membership-tier-detail__save').trigger('click');
        await flushPromises();

        expect(loyaltyTierRepository.get).toHaveBeenCalledTimes(2);
    });
});
