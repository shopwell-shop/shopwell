/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import MtCard from '@shopwell-ag/meteor-component-library/dist/esm/MtCard';
import MtIcon from '@shopwell-ag/meteor-component-library/dist/esm/MtIcon';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

describe('return reason listing rendering', () => {
    let wrapper: VueWrapper;

    async function createWrapper(canEdit = true, search = jest.fn()) {
        const repository = Shopwell.Service('repositoryFactory').create('order_return_reason');
        const reasons = new EntityCollection(
            '/order-return-reason',
            'order_return_reason',
            Shopwell.Context.api,
            new Criteria(),
            [
                Object.assign(repository.create(), {
                    translated: { label: '商品破损或存在质量问题' },
                    position: 10,
                    active: true,
                }),
                Object.assign(repository.create(), {
                    translated: { label: '商品与描述不符' },
                    position: 20,
                    active: false,
                }),
            ],
            2,
        );

        return mount(await wrapTestComponent('sw-settings-order-return-reason-list', { sync: true }), {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: { query: {} },
                    $router: { replace: jest.fn() },
                },
                provide: {
                    repositoryFactory: { create: () => ({ search: search.mockResolvedValue(reasons) }) },
                    acl: { can: () => canEdit },
                    searchRankingService: { isValidTerm: () => false },
                },
                stubs: {
                    'sw-page': {
                        template:
                            '<div><slot name="smart-bar-header"/><slot name="smart-bar-actions"/><slot name="language-switch"/><slot name="content"/></div>',
                    },
                    'sw-card-view': { template: '<div><slot/></div>' },
                    'mt-card': MtCard,
                    'mt-icon': MtIcon,
                    'sw-entity-listing': await wrapTestComponent('sw-entity-listing', { sync: true }),
                    'sw-provide': { template: '<div><slot/></div>' },
                    'sw-context-button': { template: '<div><slot/></div>' },
                    'sw-context-menu-item': {
                        props: ['disabled'],
                        template: '<button :disabled="disabled"><slot/></button>',
                    },
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': await wrapTestComponent('sw-data-grid-column-boolean', { sync: true }),
                    'sw-pagination': true,
                    'sw-language-switch': await wrapTestComponent('sw-language-switch', { sync: true }),
                    'sw-entity-single-select': {
                        name: 'sw-entity-single-select',
                        props: ['value'],
                        template: '<div/>',
                    },
                    'sw-settings-order-return-reason-modal': true,
                },
            },
        });
    }

    afterEach(() => wrapper?.unmount());

    it('renders translated names inside the standard card grid', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.get('.mt-card__content').text()).toContain('商品破损或存在质量问题');
        expect(wrapper.text()).toContain('商品与描述不符');
    });

    it('renders active and inactive reasons with the standard boolean column', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('.sw-data-grid-column-boolean .is--active')).toHaveLength(1);
        expect(wrapper.findAll('.sw-data-grid-column-boolean .is--inactive')).toHaveLength(1);
    });

    it('lets editors open the edit modal from the row actions', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-settings-order-return-reason-list__action-edit').trigger('click');

        expect(wrapper.findComponent({ name: 'sw-settings-order-return-reason-modal' }).exists()).toBe(true);
    });

    it('synchronizes the language selector and reloads reasons before creating in the system language', async () => {
        const previousLanguageId = Shopwell.Context.api.languageId;
        Shopwell.Context.api.languageId = Shopwell.Utils.createId<EntitySchema.LanguageId>();
        const search = jest.fn();

        try {
            wrapper = await createWrapper(true, search);
            await flushPromises();

            await wrapper.get('.sw-settings-order-return-reason-list__create').trigger('click');
            await flushPromises();

            expect(wrapper.getComponent({ name: 'sw-entity-single-select' }).props('value')).toBe(
                Shopwell.Context.api.systemLanguageId,
            );
            expect(Shopwell.Context.api.languageId).toBe(Shopwell.Context.api.systemLanguageId);
            expect(search).toHaveBeenCalledTimes(2);
            expect(wrapper.findComponent({ name: 'sw-settings-order-return-reason-modal' }).exists()).toBe(true);
        } finally {
            wrapper?.unmount();
            Shopwell.Context.api.languageId = previousLanguageId;
        }
    });

    it('disables maintenance for viewers', async () => {
        wrapper = await createWrapper(false);
        await flushPromises();

        expect(wrapper.get('.sw-settings-order-return-reason-list__create').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-settings-order-return-reason-list__action-edit').exists()).toBe(false);
        expect(wrapper.getComponent({ ref: 'reasonListing' }).props('allowDelete')).toBe(false);
    });
});
