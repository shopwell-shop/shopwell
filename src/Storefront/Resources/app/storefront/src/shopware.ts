/**
 * Shopwell runtime module.
 *
 * This is the entry point for the standalone Vite build (vite.shopware.config.mts).
 * It exports ShopwellComponent and Shopwell for ES module consumers and sets both
 * on window as a side effect for backward compatibility with legacy JS plugins.
 *
 * @sw-package framework
 */

// Sets window.ShopwellComponent as side effect.
export { default as ShopwellComponent } from './component-system/component';

// Sets window.Shopwell as side effect.
export { Shopwell } from './component-system/shopware';
