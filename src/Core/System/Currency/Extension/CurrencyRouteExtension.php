<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency\Extension;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\SalesChannel\CurrencyRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CurrencyRouteResponse>
 */
#[Package('fundamentals@framework')]
final class CurrencyRouteExtension extends Extension
{
    public const NAME = 'currency-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly Criteria $criteria,
    ) {
    }
}
