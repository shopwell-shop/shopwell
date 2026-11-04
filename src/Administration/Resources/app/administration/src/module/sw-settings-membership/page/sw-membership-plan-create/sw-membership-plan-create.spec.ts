/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const membershipPlanRepository = {
    create: jest.fn(() => {
        return {
            id: '',
            name: '',
            technicalName: '',
            loyaltyTierId: '',
            currencyId: '',
            price: 0,
            durationDays: 0,
            active: false,
            isNew: () => true,
        };
    }),

    save: jest.fn((entity: { id: string }) => {
        entity.id = 'new-id';

        return Promise.resolve(entity);
    }),
};

const routerPush = jest.fn();

async function createWrapper(privileges: string[] = []): Promise<VueWrapper> {
    const wrapper = mount(
        await wrapTestComponent('sw-membership-plan-create', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: { query: '' },
                    $router: { push: routerPush },
                },
                stubs: {
                    'sw-page': {
                        template: '<div><slot name="smart-bar-actions"></slot><slot name="content"></slot><slot></slot></div>',
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
                    'sw-button-process': {
                        props: ['isLoading', 'disabled'],
                        // `emits` keeps the parent listener from also running as a native click handler on
                        // the button, which would call onSave twice
                        emits: ['click'],
                        template: '<button class="sw-membership-plan-detail__save" :disabled="disabled" @click="$emit(\'click\')" />',
                    },
                    'sw-entity-single-select': true,
                    'mt-text-field': true,
                    'mt-number-field': {
                        props: ['modelValue'],
                        template: '<input class="mt-number-field" :value="modelValue" />',
                    },
                    'mt-switch': {
                        props: ['modelValue'],
                        template: '<input class="mt-switch" type="checkbox" :checked="modelValue" />',
                    },
                    'mt-textarea': true,
                    'sw-skeleton': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (name: string) => {
                            if (name === 'membership_plan') {
                                return membershipPlanRepository;
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

    return wrapper;
}

describe('src/module/sw-settings-membership/page/sw-membership-plan-create', () => {
    beforeEach(() => {
        membershipPlanRepository.create.mockClear();
        membershipPlanRepository.save.mockClear();
        routerPush.mockClear();
    });

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-membership-plan-detail').exists()).toBe(true);
    });

    it('should create a new plan with sensible defaults', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);

        expect(membershipPlanRepository.create).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-membership-plan-detail__duration').attributes('value')).toBe('365');
        expect((wrapper.find('.sw-membership-plan-detail__active').element as HTMLInputElement).checked).toBe(true);
    });

    it('should allow save with creator permission', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);

        expect(wrapper.find('.sw-membership-plan-detail__save').attributes('disabled')).toBeUndefined();
    });

    it('should not allow save without creator permission', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-membership-plan-detail__save').attributes('disabled')).toBeDefined();
    });

    it('should persist the new plan and redirect to the detail page', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);

        await wrapper.find('.sw-membership-plan-detail__save').trigger('click');
        await flushPromises();

        expect(membershipPlanRepository.save).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.settings.membership.planDetail',
            params: { id: 'new-id' },
        });
    });
});
