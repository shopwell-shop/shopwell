/**
 * @sw-package framework
 */

// @ts-expect-error
import vClickOutside from 'v-click-outside';

const { Directive } = Shopwell;

// eslint-disable-next-line @typescript-eslint/no-unsafe-argument
Directive.register('click-outside', vClickOutside);
