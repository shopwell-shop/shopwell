/**
 * @sw-package framework
 * @private
 */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopwell.Component.register('sw-example-panel', () => import('src/app/component/base/sw-example-panel/index'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
