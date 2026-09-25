<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Cache\Http\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends Extension<bool>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CacheHashRequiredExtension extends Extension
{
    public const NAME = 'cache-hash.required';

    /**
     * @internal Shopwell owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The HTTP request object
         */
        public readonly Request $request,

        /**
         * @public
         *
         * @description The sales channel context
         */
        public readonly SalesChannelContext $salesChannelContext,

        /**
         * @public
         *
         * @description The current cart
         */
        public readonly Cart $cart,
    ) {
    }
}
