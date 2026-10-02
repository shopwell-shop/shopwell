<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SuccessResponse;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SuccessResponse>
 */
#[Package('after-sales')]
final class NewsletterConfirmRouteExtension extends Extension
{
    public const NAME = 'newsletter-confirm-route.confirm';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $dataBag,
        public readonly SalesChannelContext $context,
    ) {
    }
}
