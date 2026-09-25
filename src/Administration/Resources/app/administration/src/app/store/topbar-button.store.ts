/**
 * @sw-package innovation
 * @private
 * @description Apply for upselling service only, no public usage
 */

const topBarButtonStore = Shopwell.Store.register({
    id: 'topBarButton',

    state: () => ({
        buttons: [] as unknown[],
    }),
});

/**
 * @private
 */
export type TopBarButtonStore = ReturnType<typeof topBarButtonStore>;

/**
 * @private
 */
export default topBarButtonStore;
