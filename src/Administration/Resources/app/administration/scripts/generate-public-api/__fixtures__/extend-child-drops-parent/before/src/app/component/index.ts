/**
 * @sw-package framework
 * @private
 */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopwell.Component.register('sw-example-card', () => import('src/app/component/base/sw-example-card/index'));
    Shopwell.Component.extend('sw-example-tile', 'sw-example-card', () => import('src/app/component/base/sw-example-tile/index'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
