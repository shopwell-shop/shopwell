<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Subscriber;

use Shopwell\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopwell\Core\Framework\App\ShopId\ShopIdChangedEvent;
use Shopwell\Core\Framework\App\Url\AppUrlVerifier;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @internal
 */
#[Package('framework')]
#[AsEventListener]
class VerifyAppUrlListener
{
    public function __construct(private readonly AppUrlVerifier $appUrlVerifier)
    {
    }

    public function __invoke(ShopIdChangedEvent $event): void
    {
        $newUrl = $event->newShopId->getFingerprint(AppUrl::IDENTIFIER);
        $oldUrl = $event->oldShopId?->getFingerprint(AppUrl::IDENTIFIER);

        if ($newUrl && $newUrl !== $oldUrl) {
            $this->appUrlVerifier->forceVerify($event->newShopId);
        }
    }
}
