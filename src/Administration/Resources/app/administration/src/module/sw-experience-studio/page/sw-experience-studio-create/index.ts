const utils = Shopwell.Utils;

/**
 * @private
 * @sw-package discovery
 */
export default Shopwell.Component.wrapComponentConfig({
    beforeRouteEnter(to, _from, next) {
        if (!to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },
});
