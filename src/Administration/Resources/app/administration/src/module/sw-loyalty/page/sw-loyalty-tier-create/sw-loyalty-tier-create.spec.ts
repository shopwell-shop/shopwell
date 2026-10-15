/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';

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

const routerPush = jest.fn();

async function createWrapper(privileges: string[] = []) {
    return mount(
        await wrapTestComponent('sw-loyalty-tier-create', {
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

describe('src/module/sw-loyalty/page/sw-loyalty-tier-create', () => {
    beforeEach(() => {
        loyaltyTierRepository.create.mockClear();
        loyaltyTierRepository.save.mockClear();
        routerPush.mockClear();
    });

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should create a new loyalty tier', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);
        await wrapper.vm.$nextTick();

        expect(loyaltyTierRepository.create).toHaveBeenCalledTimes(1);
        expect((wrapper.vm.loyaltyTier as { isNew: () => boolean }).isNew()).toBe(true);
        expect(wrapper.vm.allowSave).toBe(true);
    });

    it('should not allow save without creator permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.allowSave).toBe(false);
    });

    it('should persist the new loyalty tier and redirect to the detail page', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);
        await wrapper.vm.$nextTick();

        await wrapper.vm.onSave();
        await flushPromises();

        expect(loyaltyTierRepository.save).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.settings.loyalty.detail',
            params: { id: 'new-id' },
        });
    });
});
