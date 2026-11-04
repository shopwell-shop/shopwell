/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

type SearchRankingPoint = {
    HIGH_SEARCH_RANKING: number;
};

type BenefitRow = { type: string; value: number };

/**
 * The association as the repository hands it over: a collection, not a plain array. The list reads the
 * multiplier out of it, so the mock has to answer `getElements()` like the real one does.
 */
function benefits(...rows: BenefitRow[]) {
    return { getElements: () => rows };
}

type TierRow = {
    id: string;
    name: string;
    level: number;
    minTierPoints: number;
    benefits: ReturnType<typeof benefits> | null;
    active: boolean;
};

function tierRow(overrides: Partial<TierRow> = {}): TierRow {
    return {
        id: '1',
        name: 'Gold',
        level: 2,
        minTierPoints: 1000,
        benefits: benefits({ type: 'earn_multiplier', value: 1.5 }),
        active: true,
        ...overrides,
    };
}

const searchRankingServiceMock = {
    getSearchFieldsByEntity: jest.fn(),
    buildSearchQueriesForEntity: jest.fn(),
    isValidTerm: jest.fn(),
};

async function createWrapper(
    privileges: string[] = [],
    tiers: TierRow[] = [tierRow()],
    query: Record<string, string | number> = { page: 1, limit: 25 },
): Promise<VueWrapper> {
    const wrapper = mount(
        await wrapTestComponent('sw-membership-tier-list', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: {
                        query,
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
                        <slot name="search-bar"></slot>
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

                            <span class="sw-entity-listing__column-benefits">
                                <slot name="column-benefits" v-bind="{ item }"></slot>
                            </span>
                        </template>
                    </div>`,
                    },
                    'router-link': true,
                    'sw-search-bar': {
                        props: ['initialSearch'],
                        template: '<input class="sw-search-bar" :value="initialSearch" @keyup.enter="$emit(\'search\', $event.target.value)" />',
                    },
                    'sw-language-info': true,
                    'sw-language-switch': true,
                    'mt-icon': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve(tiers),
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

describe('src/module/sw-settings-membership/page/sw-membership-tier-list', () => {
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

        expect(wrapper.find('.sw-membership-tier-list').exists()).toBe(true);
    });

    it('should not be able to create without creator permission', async () => {
        const wrapper = await createWrapper();
        await nextTick();

        const createButton = wrapper.find('.sw-membership-tier-list__create');

        expect(createButton.attributes('disabled')).toBeDefined();
    });

    it('should be able to create with creator permission', async () => {
        const wrapper = await createWrapper(['loyalty.creator']);
        await nextTick();

        const createButton = wrapper.find('.sw-membership-tier-list__create');

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
        await nextTick();

        const entityList = wrapper.find('.sw-membership-tier-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to inline edit with editor permission', async () => {
        const wrapper = await createWrapper(['loyalty.editor']);
        await nextTick();

        const entityList = wrapper.find('.sw-membership-tier-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to delete without deleter permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-membership-tier-list-grid__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete with deleter permission', async () => {
        const wrapper = await createWrapper(['loyalty.deleter']);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-membership-tier-list-grid__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should hide item selection without deleter permission', async () => {
        const wrapper = await createWrapper();
        await nextTick();

        const entityList = wrapper.find('.sw-membership-tier-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeFalsy();
    });

    it('should show item selection with deleter permission', async () => {
        const wrapper = await createWrapper(['loyalty.deleter']);
        await nextTick();

        const entityList = wrapper.find('.sw-membership-tier-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeTruthy();
    });

    it('should add query score to the criteria when the page is opened with a term', async () => {
        searchRankingServiceMock.buildSearchQueriesForEntity.mockReturnValue(new Criteria(1, 25));
        searchRankingServiceMock.getSearchFieldsByEntity.mockResolvedValue({ name: 500 });

        // a bookmarked search URL is how a term reaches the page without anybody typing it
        await createWrapper([], [tierRow()], { page: 1, limit: 25, term: 'gold' });
        await flushPromises();

        expect(searchRankingServiceMock.buildSearchQueriesForEntity).toHaveBeenCalledWith(
            expect.anything(),
            'gold',
            expect.anything(),
        );
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

        expect(wrapper.find('.sw-membership-tier-list-grid').exists()).toBe(false);

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

        expect(wrapper.find('.sw-membership-tier-list-grid').exists()).toBe(true);
        expect(wrapper.find('.mt-empty-state').exists()).toBe(false);
    });

    it('prints the multiplier the tier grants', async () => {
        const wrapper = await createWrapper(['loyalty.viewer']);
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__column-benefits').text()).toBe('1.5');
    });

    it('prints the base rate for a tier that grants no multiplier', async () => {
        const wrapper = await createWrapper(['loyalty.viewer'], [tierRow({ benefits: benefits() })]);
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__column-benefits').text()).toBe('1');
    });

    it('prints the base rate for a tier whose benefits were not loaded', async () => {
        const wrapper = await createWrapper(['loyalty.viewer'], [tierRow({ benefits: null })]);
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__column-benefits').text()).toBe('1');
    });
});
