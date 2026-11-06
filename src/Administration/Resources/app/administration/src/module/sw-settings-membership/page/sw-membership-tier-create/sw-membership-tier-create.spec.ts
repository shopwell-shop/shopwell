/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const loyaltyTierRepository = {
    create: jest.fn(() => {
        return {
            id: '',
            name: '',
            technicalName: '',
            level: 1,
            isNew: () => true,
        };
    }),

    save: jest.fn((entity: { id: string }) => {
        entity.id = 'new-id';

        return Promise.resolve(entity);
    }),
};

const benefitRepository = {
    search: jest.fn(() => Promise.resolve([])),
    create: jest.fn(() => {
        return { id: 'new-benefit' };
    }),
    save: jest.fn<Promise<void>, [Record<string, unknown>]>(() => Promise.resolve()),
    delete: jest.fn(() => Promise.resolve()),
};

const routerPush = jest.fn();

async function createWrapper(privileges: string[] = []): Promise<VueWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-membership-tier-create', { sync: true }), {
        global: {
            mocks: {
                $route: { query: '' },
                $router: { push: routerPush },
                // the wording is not under test, only which key the page looks up
                $t: (key: string) => key,
            },
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"></slot><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
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
                'mt-switch': { props: ['modelValue'], template: '<input class="mt-switch" type="checkbox" />' },
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

describe('src/module/sw-settings-membership/page/sw-membership-tier-create', () => {
    beforeEach(() => {
        loyaltyTierRepository.create.mockClear();
        loyaltyTierRepository.save.mockClear();
        benefitRepository.save.mockClear();
        routerPush.mockClear();
    });

    it('opens an empty tier instead of loading one', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);

        expect(loyaltyTierRepository.create).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-membership-tier-detail__name').attributes('value')).toBe('');
    });

    it('does not let an operator without the creator privilege save', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-membership-tier-detail__save').attributes('disabled')).toBeDefined();
    });

    it('lets an operator with the creator privilege save', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);

        expect(wrapper.find('.sw-membership-tier-detail__save').attributes('disabled')).toBeUndefined();
    });

    it('persists the new tier and opens it on the detail page', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);

        await wrapper.find('.sw-membership-tier-detail__save').trigger('click');
        await flushPromises();

        expect(loyaltyTierRepository.save).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.settings.membership.detail',
            params: { id: 'new-id' },
        });
    });

    it('stores the benefits a new tier was created with', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);

        await wrapper.find('.sw-membership-tier-detail__earn-multiplier').setValue('2');
        await wrapper.find('.sw-membership-tier-detail__save').trigger('click');
        await flushPromises();

        expect(benefitRepository.create).toHaveBeenCalledTimes(1);
        expect(benefitRepository.save).toHaveBeenCalledTimes(1);
        expect(benefitRepository.save.mock.calls[0][0]).toMatchObject({
            loyaltyTierId: 'new-id',
            type: 'earn_multiplier',
            value: 2,
        });
    });
});
