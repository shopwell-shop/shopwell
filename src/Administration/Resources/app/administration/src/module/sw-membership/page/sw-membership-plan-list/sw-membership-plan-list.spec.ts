/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import type Criteria from 'src/core/data/criteria.data';

type SearchRankingPoint = {
    HIGH_SEARCH_RANKING: number;
};

const plan = {
    id: 'plan-1',
    name: 'Gold, one year',
    technicalName: 'gold_year',
    price: 199.0,
    durationDays: 365,
    active: true,
    loyaltyTier: { id: 'tier-1', name: 'Gold' },
    currency: { id: 'currency-1', isoCode: 'EUR', name: 'Euro' },
};

const searchRankingServiceMock = {
    getSearchFieldsByEntity: jest.fn(),
    buildSearchQueriesForEntity: jest.fn(),
    isValidTerm: jest.fn(),
};

async function createWrapper(privileges: string[] = [], plans: unknown[] = [plan]): Promise<VueWrapper> {
    const wrapper = mount(
        await wrapTestComponent('sw-membership-plan-list', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-header"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`,
                    },
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'mt-card': {
                        template: '<div><slot name="grid"></slot></div>',
                    },
                    'sw-context-menu-item': true,
                    'sw-entity-listing': {
                        props: [
                            'items',
                            'dataSource',
                            'allowEdit',
                            'allowDelete',
                            'detailRoute',
                        ],
                        template: `
                    <div>
                        <template v-for="item in (dataSource || items)">
                            <slot name="actions" v-bind="{ item }">
                                <slot name="detail-action" v-bind="{ item }">
                                    <div class="sw-entity-listing__context-menu-edit-action"
                                         v-if="detailRoute"
                                         :disabled="!allowEdit || undefined"
                                         :routerLink="{ name: detailRoute, params: { id: item.id } }"
                                    >
                                    </div>
                                </slot>

                                <slot name="delete-action" v-bind="{ item }"></slot>
                            </slot>

                            <span class="sw-entity-listing__column-price">
                                <slot name="column-price" v-bind="{ item }"></slot>
                            </span>
                        </template>
                    </div>`,
                    },
                    'router-link': true,
                    'sw-search-bar': true,
                    'sw-language-switch': true,
                    'mt-icon': true,
                    'mt-button': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve(Object.assign([...plans], { total: plans.length }));
                            },
                            delete: () => Promise.resolve(),
                        }),
                    },
                    acl: {
                        can: (identifier: string) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    searchRankingService: searchRankingServiceMock,
                },
            },
        },
    );

    return wrapper;
}

describe('src/module/sw-membership/page/sw-membership-plan-list', () => {
    beforeEach(() => {
        searchRankingServiceMock.getSearchFieldsByEntity.mockResolvedValue({
            name: (searchRankingPoint as SearchRankingPoint).HIGH_SEARCH_RANKING,
        });
        searchRankingServiceMock.buildSearchQueriesForEntity.mockImplementation(
            (searchFields: unknown, term: string, criteria: Criteria) => {
                return criteria;
            },
        );
        searchRankingServiceMock.isValidTerm.mockImplementation((term: string) => {
            return Boolean(term && term.trim().length >= 1);
        });
    });

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-membership-plan-list').exists()).toBe(true);
    });

    it('should load the plans with their tier and currency', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__column-price').exists()).toBe(true);
        expect(wrapper.find('.sw-page__smart-bar-amount').text()).toBe('(1)');
    });

    it('should not be able to create without creator permission', async () => {
        const wrapper = await createWrapper();
        await nextTick();

        expect(wrapper.find('.sw-membership-plan-list__create').attributes('disabled')).toBeDefined();
    });

    it('should be able to create with creator permission', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);
        await nextTick();

        expect(wrapper.find('.sw-membership-plan-list__create').attributes('disabled')).toBeFalsy();
    });

    it('should not be able to edit without editor permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__context-menu-edit-action').attributes().disabled).toBeTruthy();
    });

    it('should be able to edit with editor permission', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__context-menu-edit-action').attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete without deleter permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-membership-plan-list-grid__delete-action').attributes().disabled).toBeTruthy();
    });

    it('should be able to delete with deleter permission', async () => {
        const wrapper = await createWrapper(['loyalty.deleter']);
        await flushPromises();

        expect(wrapper.find('.sw-membership-plan-list-grid__delete-action').attributes().disabled).toBeFalsy();
    });

    it('should format the price in the currency of the plan', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__column-price').text()).toContain('199');
    });
});
