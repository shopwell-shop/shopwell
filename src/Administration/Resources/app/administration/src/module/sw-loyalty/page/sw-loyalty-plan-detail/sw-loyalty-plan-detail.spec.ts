/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type TypedWrapper<T> = { vm: T } & VueWrapper;

type PlanDetailVm = {
    plan: { technicalName: string } | null;
    isLoading: boolean;
    allowSave: boolean;
    tooltipSave: Record<string, unknown>;
    loadPlan: () => Promise<void>;
    onCancel: () => void;
};

const membershipPlanRepository = {
    create: () => {
        return {
            id: '',
            name: '',
            technicalName: '',
            loyaltyTierId: '',
            currencyId: '',
            price: 0,
            durationDays: 365,
            active: true,
            description: null,
            isNew: () => true,
        };
    },

    get: () => {
        return Promise.resolve({
            id: '1',
            name: 'Gold, one year',
            technicalName: 'gold_year',
            loyaltyTierId: 'tier-1',
            currencyId: 'currency-1',
            price: 199,
            durationDays: 365,
            active: true,
            description: 'One year of Gold membership.',
            isNew: () => false,
        });
    },

    save: jest.fn(() => Promise.resolve({})),
};

const routerPushMock = jest.fn(() => Promise.resolve());

async function createWrapper(privileges: string[] = [], planId: string | null = '1'): Promise<TypedWrapper<PlanDetailVm>> {
    const wrapper = mount(
        await wrapTestComponent('sw-loyalty-plan-detail', {
            sync: true,
        }),
        {
            props: {
                planId,
            },
            global: {
                mocks: {
                    $route: { query: '' },
                    $router: { push: routerPushMock },
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

    return wrapper as unknown as TypedWrapper<PlanDetailVm>;
}

describe('src/module/sw-loyalty/page/sw-loyalty-plan-detail', () => {
    beforeEach(() => {
        membershipPlanRepository.save.mockClear();
    });

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should load the plan with its tier and currency on creation', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.plan).toBeTruthy();
        expect(wrapper.vm.plan?.technicalName).toBe('gold_year');
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should not be able to save without editor permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-loyalty-plan-detail__save').attributes()).toHaveProperty('disabled');
        expect(wrapper.vm.allowSave).toBe(false);
    });

    it('should be able to save with editor permission', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        expect(wrapper.find('.sw-loyalty-plan-detail__save').attributes()).not.toHaveProperty('disabled');
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

    it('should persist the plan and reload it on save', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        const loadPlanSpy = jest.spyOn(wrapper.vm, 'loadPlan');
        await wrapper.find('.sw-loyalty-plan-detail__save').trigger('click');
        await flushPromises();

        expect(membershipPlanRepository.save).toHaveBeenCalledTimes(1);
        expect(loadPlanSpy).toHaveBeenCalled();
    });

    it('should notify the user when no plan id was given', async () => {
        const wrapper = await createWrapper([], null);
        await flushPromises();

        expect(wrapper.vm.plan).toBeNull();
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should navigate back to the plan list on cancel', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        wrapper.vm.onCancel();

        expect(routerPushMock).toHaveBeenCalledWith({ name: 'sw.settings.loyalty.plans' });
    });
});
