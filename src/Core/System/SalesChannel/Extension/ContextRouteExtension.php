<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannel\ContextLoadRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextLoadRouteResponse>
 */
#[Package('framework')]
final class ContextRouteExtension extends Extension
{
    public const NAME = 'context-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly SalesChannelContext $context,
    ) {
    }
}
