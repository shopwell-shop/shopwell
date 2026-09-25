/**
 * @sw-package fundamentals@framework
 */
const swProfileStore = Shopwell.Store.register('swProfile', {
    state() {
        return {
            minSearchTermLength: 2,
            searchPreferences: [],
            userSearchPreferences: null,
        };
    },

    actions: {
        setMinSearchTermLength(minSearchTermLength: number) {
            this.minSearchTermLength = minSearchTermLength;
        },
    },
});

/**
 * @private
 */
export default swProfileStore;

/**
 * @private
 */
export type SwProfileStore = ReturnType<typeof swProfileStore>;
