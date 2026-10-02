<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LegalGuaranteeNotice\Extension;

use Shopwell\Core\Content\LegalGuaranteeNotice\SalesChannel\LegalGuaranteeNoticeRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<LegalGuaranteeNoticeRouteResponse>
 */
#[Package('inventory')]
final class LegalGuaranteeNoticeRouteExtension extends Extension
{
    public const NAME = 'legal-guarantee-notice-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly SalesChannelContext $context,
    ) {
    }
}
