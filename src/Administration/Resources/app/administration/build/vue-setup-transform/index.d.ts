/**
 * @sw-package framework
 */

/**
 * Types for the CommonJS bridge (`index.js`), which loads `index.ts` through jiti.
 *
 * Everything here is derived from the TypeScript implementation rather than restated, so the two
 * cannot drift: the previous hand-written copy of `ShopwellSetupTransformResult` had already fallen
 * behind the `ownedBlockNames` / `extendedBlockNames` fields the transform returns.
 */

export { ShopwellSetupTransformError, transformShopwellSetupSfc, validateShopwellSetupSfc } from './index';

export type { ShopwellSetupTransformResult } from './index';

/**
 * Names the filename-inferred transform path used by one Shopwell setup SFC.
 */
export type ShopwellSetupTransformMode = import('./index').ShopwellSetupTransformResult['mode'];
