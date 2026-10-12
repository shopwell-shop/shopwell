<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Extension;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\SalesChannel\CountryCityRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CountryCityRouteResponse>
 */
#[Package('fundamentals@discovery')]
final class CountryCityRouteExtension extends Extension
{
    public const NAME = 'country-city-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $countryStateId,
        public readonly Request $request,
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $context,
    ) {
    }
}
