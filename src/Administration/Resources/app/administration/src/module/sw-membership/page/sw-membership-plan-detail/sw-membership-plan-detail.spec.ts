/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

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

    get: jest.fn(() => {
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
    }),

    save: jest.fn(() => Promise.resolve({})),
};

const routerPushMock = jest.fn(() => Promise.resolve());

// the tooltip of the save button follows the privilege of the operator, so the element is rewritten
// on every update and not only on mount
function writeTooltip(el: HTMLElement, binding: { value: unknown }): void {
    el.setAttribute('data-tooltip', JSON.stringify(binding.value));
}

async function createWrapper(privileges: string[] = [], planId: string | null = '1'): Promise<VueWrapper> {
    const wrapper = mount(
        await wrapTestComponent('sw-membership-plan-detail', {
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
                    // the platform of the machine that runs the test decides the real modifier, which
                    // would make the expected tooltip depend on the developer's laptop
                    $device: { getSystemKey: () => 'CTRL' },
                },
                directives: {
                    // the real tooltip directive paints nothing the test could read, so the stub writes
                    // the config it was given onto the element the operator hovers
                    tooltip: {
                        mounted: writeTooltip,
                        updated: writeTooltip,
                    },
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
                    'sw-button-process': {
                        props: ['isLoading', 'disabled'],
                        // `emits` keeps the parent listener from also running as a native click handler on
                        // the button, which would call onSave twice
                        emits: ['click'],
                        template: '<button class="sw-membership-plan-detail__save" :disabled="disabled" @click="$emit(\'click\')" />',
                    },
                    'sw-entity-single-select': true,
                    'mt-text-field': {
                        props: ['modelValue'],
                        template: '<input class="mt-text-field" :value="modelValue" />',
                    },
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

function tooltipOf(wrapper: VueWrapper, selector: string): unknown {
    return JSON.parse(wrapper.find(selector).attributes('data-tooltip') ?? 'null');
}

describe('src/module/sw-membership/page/sw-membership-plan-detail', () => {
    beforeEach(() => {
        membershipPlanRepository.save.mockClear();
        membershipPlanRepository.get.mockClear();
        routerPushMock.mockClear();
    });

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-membership-plan-detail').exists()).toBe(true);
    });

    it('should load the plan with its tier and currency on creation', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-membership-plan-detail__technical-name').attributes('value')).toBe('gold_year');
    });

    it('should not be able to save without editor permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-membership-plan-detail__save').attributes()).toHaveProperty('disabled');
    });

    it('should be able to save with editor permission', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        expect(wrapper.find('.sw-membership-plan-detail__save').attributes()).not.toHaveProperty('disabled');
    });

    it('should show the save shortcut tooltip with editor permission', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        expect(tooltipOf(wrapper, '.sw-membership-plan-detail__save')).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light',
        });
    });

    it('should show the permission warning tooltip without editor permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(tooltipOf(wrapper, '.sw-membership-plan-detail__save')).toStrictEqual({
            message: 'sw-privileges.tooltip.warning',
            disabled: false,
            showOnDisabledElements: true,
        });
    });

    it('should persist the plan and reload it on save', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        await wrapper.find('.sw-membership-plan-detail__save').trigger('click');
        await flushPromises();

        expect(membershipPlanRepository.save).toHaveBeenCalledTimes(1);
        // the page reads the plan once when it opens and once after the save, so what the operator
        // sees afterwards is what was stored and not what the form happened to hold
        expect(membershipPlanRepository.get).toHaveBeenCalledTimes(2);
    });

    it('should not request a plan when no plan id was given', async () => {
        const wrapper = await createWrapper([], null);
        await flushPromises();

        expect(membershipPlanRepository.get).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-membership-plan-detail__technical-name').exists()).toBe(false);
    });

    it('should navigate back to the plan list on cancel', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        await wrapper.find('.sw-membership-plan-detail__cancel').trigger('click');

        expect(routerPushMock).toHaveBeenCalledWith({ name: 'sw.settings.membership.plans' });
    });
});
