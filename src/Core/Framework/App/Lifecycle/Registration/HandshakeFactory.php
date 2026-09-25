<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Registration;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Services\StoreClient;

/**
 * @internal only for use by the app-system
 *
 * @final
 */
#[Package('framework')]
readonly class HandshakeFactory
{
    public function __construct(
        private string $shopUrl,
        private ShopIdProvider $shopIdProvider,
        private StoreClient $storeClient,
        private string $shopwellVersion,
        private ClockInterface $clock,
    ) {
    }

    public function create(Manifest $manifest, AppEntity $app, #[\SensitiveParameter] ?string $currentSecret = null): AppHandshakeInterface
    {
        $setup = $manifest->getSetup();
        $metadata = $manifest->getMetadata();
        $appName = $metadata->getName();

        if (!$setup) {
            throw AppException::registrationFailed(
                $appName,
                \sprintf('No setup for registration provided in manifest for app "%s".', $metadata->getName())
            );
        }

        $privateSecret = $setup->getSecret();

        try {
            $shopId = $this->shopIdProvider->getShopId()->id;
        } catch (ShopIdChangeSuggestedException $e) {
            throw AppException::registrationFailed(
                $appName,
                $e->getMessage(),
            );
        }

        // The secret the app currently holds, used to sign the re-registration's previous-signature.
        // Normally this is the stored app_secret; recovery passes in the unconfirmed secret the app may
        // already have switched to.
        $currentAppSecret = $currentSecret ?? $app->getAppSecret();

        if ($privateSecret) {
            return new PrivateHandshake(
                $this->shopUrl,
                $privateSecret,
                $setup->getRegistrationUrl(),
                $metadata->getName(),
                $shopId,
                $this->shopwellVersion,
                $this->clock,
                $currentAppSecret,
            );
        }

        return new StoreHandshake(
            $this->shopUrl,
            $setup->getRegistrationUrl(),
            $metadata->getName(),
            $shopId,
            $this->storeClient,
            $this->shopwellVersion,
            $this->clock,
            $currentAppSecret,
        );
    }
}
