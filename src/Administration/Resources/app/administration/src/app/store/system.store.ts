/**
 * @sw-package framework
 * @private
 */
import useSystem from '../composables/use-system';

const system = Shopwell.Store.register('system', useSystem);

/**
 * @private
 */
export type System = ReturnType<typeof system>;

/**
 * @private
 */
export default system;
