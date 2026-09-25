<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Routing;

use Shopwell\Core\Framework\Event\BeforeSendResponseEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\SalesChannelRequest;

/**
 * @internal
 */
#[Package('discovery')]
class CanonicalLinkListener
{
    public function __invoke(BeforeSendResponseEvent $event): void
    {
        if (!$event->getResponse()->isSuccessful()) {
            return;
        }

        if ($canonical = $event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK)) {
            \assert(\is_string($canonical));
            $canonical = \sprintf('<%s>; rel="canonical"', $canonical);
            $event->getResponse()->headers->set('Link', $canonical);
        }
    }
}
