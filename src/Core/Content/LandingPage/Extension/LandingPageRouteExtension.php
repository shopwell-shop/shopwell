<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LandingPage\Extension;

use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<LandingPageRouteResponse>
 */
#[Package('discovery')]
final class LandingPageRouteExtension extends Extension
{
    public const NAME = 'landing-page-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $landingPageId,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
