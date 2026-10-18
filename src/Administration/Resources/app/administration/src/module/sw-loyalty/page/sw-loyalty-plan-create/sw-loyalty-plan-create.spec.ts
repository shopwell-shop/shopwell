/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type TypedWrapper<T> = { vm: T } & VueWrapper;

type PlanCreateVm = {
    plan: { active: boolean; durationDays: number; isNew: () => boolean };
    allowSave: boolean;
    onSave: () => Promise<void>;
};

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

async function createWrapper(privileges: string[] = []): Promise<TypedWrapper<PlanCreateVm>> {
    const wrapper = mount(
        await wrapTestComponent('sw-loyalty-plan-create', {
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
                        template: '<div><slot name="content"></slot><slot></slot></div>',
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

    return wrapper as unknown as TypedWrapper<PlanCreateVm>;
}

describe('src/module/sw-loyalty/page/sw-loyalty-plan-create', () => {
    beforeEach(() => {
        membershipPlanRepository.create.mockClear();
        membershipPlanRepository.save.mockClear();
        routerPush.mockClear();
    });

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should create a new plan with sensible defaults', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);
        await wrapper.vm.$nextTick();

        expect(membershipPlanRepository.create).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.plan.isNew()).toBe(true);
        expect(wrapper.vm.plan.active).toBe(true);
        expect(wrapper.vm.plan.durationDays).toBe(365);
    });

    it('should allow save with creator permission', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.allowSave).toBe(true);
    });

    it('should not allow save without creator permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.allowSave).toBe(false);
    });

    it('should persist the new plan and redirect to the detail page', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);
        await wrapper.vm.$nextTick();

        await wrapper.vm.onSave();
        await flushPromises();

        expect(membershipPlanRepository.save).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.settings.loyalty.planDetail',
            params: { id: 'new-id' },
        });
    });
});
