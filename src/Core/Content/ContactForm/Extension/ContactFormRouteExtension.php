<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ContactForm\Extension;

use Shopwell\Core\Content\ContactForm\SalesChannel\ContactFormRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContactFormRouteResponse>
 */
#[Package('discovery')]
final class ContactFormRouteExtension extends Extension
{
    public const NAME = 'contact-form-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $data,
        public readonly SalesChannelContext $context,
    ) {
    }
}
