<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Extension;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\SalesChannel\CountryRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CountryRouteResponse>
 */
#[Package('fundamentals@discovery')]
final class CountryRouteExtension extends Extension
{
    public const NAME = 'country-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $context,
    ) {
    }
}
