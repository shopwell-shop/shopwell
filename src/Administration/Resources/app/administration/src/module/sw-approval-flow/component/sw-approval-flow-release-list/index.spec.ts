/** @sw-package framework */
import { flushPromises, shallowMount } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import MtPagination from '@shopwell-ag/meteor-component-library/dist/esm/MtPagination';
import type { VueWrapper } from '@vue/test-utils';
import type Criteria from 'src/core/data/criteria.data';

const release = { id: 'release', draftRevision: 2, createdAt: '2026-11-25T00:00:00Z', contentHash: 'hash' };
function page(items = [release], total = 11) {
    return Object.assign(items, { total });
}
async function createWrapper(
    releases = jest.fn<Promise<ReturnType<typeof page>>, [Criteria, apiContext]>().mockResolvedValue(page()),
) {
    const wrapper = shallowMount(await wrapTestComponent('sw-approval-flow-release-list', { sync: true }), {
        props: { definitionId: 'first' },
        global: {
            provide: { repositoryFactory: { create: () => ({ search: releases }) } },
            stubs: {
                'mt-data-table': {
                    props: [
                        'dataSource',
                        'currentPage',
                        'paginationLimit',
                        'paginationTotalItems',
                    ],
                    template: `<section class="meteor-table"><span v-for="item in dataSource" :key="item.id">{{ item.contentHash }}</span><slot v-if="!dataSource.length" name="empty-state"/><mt-pagination :current-page="currentPage" :limit="paginationLimit" :total-items="paginationTotalItems" @change-current-page="$emit('pagination-current-page-change', $event)"/><button class="next" @click="$emit('pagination-current-page-change', 2)">Next</button></section>`,
                },
                'mt-banner': { template: '<div role="alert"><slot/></div>' },
                'mt-button': { template: '<button><slot/></button>' },
                'sw-loader': true,
                'mt-pagination': MtPagination,
            },
        },
    });
    await flushPromises();
    return { wrapper: wrapper as unknown as VueWrapper<ComponentPublicInstance<{ definitionId: string }>>, releases };
}

describe('approval release history', () => {
    it('shows published revisions as history and loads the next page without implying activation', async () => {
        const { wrapper, releases } = await createWrapper();
        expect(wrapper.find('.meteor-table').exists()).toBe(true);
        expect(wrapper.text()).toContain('hash');
        expect(wrapper.text()).not.toContain('enabled');
        await wrapper.find('.next').trigger('click');
        await flushPromises();
        expect(releases.mock.lastCall?.[0].getPage()).toBe(2);
        expect(releases.mock.lastCall?.[0].getLimit()).toBe(10);
    });

    it('keeps a valid page when Meteor pagination emits zero from its page input', async () => {
        const { wrapper, releases } = await createWrapper();
        await wrapper.find('input').setValue('0');
        await wrapper.find('input').trigger('change');
        await flushPromises();

        expect(releases.mock.lastCall?.[0].getPage()).toBe(1);
    });

    it('resets paging and discards a delayed history from the previous definition', async () => {
        let resolve!: (value: ReturnType<typeof page>) => void;
        const releases = jest
            .fn<Promise<ReturnType<typeof page>>, [Criteria, apiContext]>()
            .mockReturnValueOnce(
                new Promise((done) => {
                    resolve = done;
                }),
            )
            .mockResolvedValueOnce(page([{ ...release, id: 'new', draftRevision: 3, contentHash: 'new hash' }], 1));
        const { wrapper } = await createWrapper(releases);
        await wrapper.setProps({ definitionId: 'second' });
        await flushPromises();
        resolve(page([{ ...release, id: 'old', draftRevision: 1, contentHash: 'old hash' }], 1));
        await flushPromises();
        expect(wrapper.text()).toContain('new hash');
        expect(wrapper.text()).not.toContain('old hash');
    });

    it('retries a failed history without treating it as unpublished', async () => {
        const { wrapper } = await createWrapper(
            jest
                .fn<Promise<ReturnType<typeof page>>, [Criteria, apiContext]>()
                .mockRejectedValueOnce(new Error('offline'))
                .mockResolvedValueOnce(page([], 0)),
        );
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        await wrapper.find('[role="alert"] button').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('sw-approval-flow.design.noReleases');
    });
});
