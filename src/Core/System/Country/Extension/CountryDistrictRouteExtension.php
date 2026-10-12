<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Extension;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\SalesChannel\CountryDistrictRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CountryDistrictRouteResponse>
 */
#[Package('fundamentals@discovery')]
final class CountryDistrictRouteExtension extends Extension
{
    public const NAME = 'country-district-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $countryCityId,
        public readonly Request $request,
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $context,
    ) {
    }
}
