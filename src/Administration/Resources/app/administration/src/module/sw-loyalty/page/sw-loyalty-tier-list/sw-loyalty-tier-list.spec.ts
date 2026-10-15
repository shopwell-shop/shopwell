/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

type SearchRankingPoint = {
    HIGH_SEARCH_RANKING: number;
};

const searchRankingServiceMock = {
    getSearchFieldsByEntity: jest.fn(),
    buildSearchQueriesForEntity: jest.fn(),
    isValidTerm: jest.fn(),
};

async function createWrapper(privileges: string[] = []) {
    return mount(
        await wrapTestComponent('sw-loyalty-tier-list', {
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
                        meta: {
                            $module: {
                                icon: 'regular-star',
                            },
                        },
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
                        </template>
                    </div>`,
                    },
                    'router-link': true,
                    'sw-search-bar': true,
                    'sw-language-info': true,
                    'sw-language-switch': true,
                    'mt-icon': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve([
                                    {
                                        id: '1',
                                        name: 'Gold',
                                        level: 2,
                                        minGrowthValue: 1000,
                                        pointsMultiplier: 1.5,
                                        active: true,
                                    },
                                ]);
                            },
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
}

describe('src/module/sw-loyalty/page/sw-loyalty-tier-list', () => {
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

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to create without creator permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-loyalty-tier-list__create');

        expect(createButton.attributes('disabled')).toBeDefined();
    });

    it('should be able to create with creator permission', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-loyalty-tier-list__create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit without editor permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');

        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit with editor permission', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');

        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to inline edit without editor permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-loyalty-tier-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to inline edit with editor permission', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-loyalty-tier-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to delete without deleter permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-loyalty-tier-list-grid__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete with deleter permission', async () => {
        const wrapper = await createWrapper(['loyalty.deleter']);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-loyalty-tier-list-grid__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should hide item selection without deleter permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-loyalty-tier-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeFalsy();
    });

    it('should show item selection with deleter permission', async () => {
        const wrapper = await createWrapper(['loyalty.deleter']);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-loyalty-tier-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeTruthy();
    });

    it('should add query score to the criteria when a term is set', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'gold',
        });
        await wrapper.vm.$nextTick();

        searchRankingServiceMock.buildSearchQueriesForEntity.mockReturnValue(new Criteria(1, 25));
        searchRankingServiceMock.getSearchFieldsByEntity.mockResolvedValue({ name: 500 });

        await wrapper.vm.getList();

        expect(searchRankingServiceMock.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(searchRankingServiceMock.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
    });

    it('should show the empty state when a searchable term has no result', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({
            isLoading: false,
            term: 'gold',
            entitySearchable: true,
            total: 0,
        });

        expect(wrapper.vm.showEmptyState).toBe(true);
        expect(wrapper.find('.sw-loyalty-tier-list-grid').exists()).toBe(false);

        const emptyState = wrapper.find('.mt-empty-state');
        expect(emptyState.exists()).toBe(true);
        expect(emptyState.text()).toContain('sw-empty-state.messageNoResultTitle');
    });

    it('should show the grid when the search has results', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({
            isLoading: false,
            term: 'gold',
            entitySearchable: true,
            total: 1,
        });

        expect(wrapper.vm.showEmptyState).toBe(false);
        expect(wrapper.find('.sw-loyalty-tier-list-grid').exists()).toBe(true);
        expect(wrapper.find('.mt-empty-state').exists()).toBe(false);
    });
});
