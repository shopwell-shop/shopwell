<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\Extension;

use Shopwell\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NewsletterSubscribeRouteResponse>
 */
#[Package('after-sales')]
final class NewsletterSubscribeRouteExtension extends Extension
{
    public const NAME = 'newsletter-subscribe-route.subscribe';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $dataBag,
        public readonly SalesChannelContext $context,
        public readonly bool $validateStorefrontUrl,
    ) {
    }
}
