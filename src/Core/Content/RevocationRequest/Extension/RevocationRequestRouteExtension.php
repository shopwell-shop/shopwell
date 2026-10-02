<?php declare(strict_types=1);

namespace Shopwell\Core\Content\RevocationRequest\Extension;

use Shopwell\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<RevocationRequestRouteResponse>
 */
#[Package('after-sales')]
final class RevocationRequestRouteExtension extends Extension
{
    public const NAME = 'revocation-request-route.request';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $dataBag,
        public readonly SalesChannelContext $context,
    ) {
    }
}
