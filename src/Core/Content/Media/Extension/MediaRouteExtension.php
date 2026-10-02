<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Extension;

use Shopwell\Core\Content\Media\SalesChannel\MediaRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<MediaRouteResponse>
 */
#[Package('discovery')]
final class MediaRouteExtension extends Extension
{
    public const NAME = 'media-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
