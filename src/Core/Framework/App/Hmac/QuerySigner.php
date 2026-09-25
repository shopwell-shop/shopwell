<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Hmac;

use GuzzleHttp\Psr7\Uri;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\UriInterface;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Authentication\LocaleProvider;
use Shopwell\Core\Framework\Store\InAppPurchase;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class QuerySigner
{
    public function __construct(
        private readonly string $shopUrl,
        private readonly string $shopwareVersion,
        private readonly LocaleProvider $localeProvider,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly InAppPurchase $inAppPurchase,
        private readonly ClockInterface $clock,
    ) {
    }

    public function signUri(string $uri, AppEntity $app, Context $context): UriInterface
    {
        $secret = $app->getAppSecret();
        if ($secret === null) {
            throw AppException::appSecretMissing($app->getName());
        }

        $unsignedUri = Uri::withQueryValues(new Uri($uri), [
            'shop-id' => $this->shopIdProvider->getShopId()->id,
            'shop-url' => $this->shopUrl,
            'timestamp' => (string) $this->clock->now()->getTimestamp(),
            'sw-version' => $this->shopwareVersion,
            'app-version' => $app->getVersion(),
            'in-app-purchases' => \urlencode($this->inAppPurchase->getJWTByExtension($app->getName()) ?? ''),
            AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE => $context->getLanguageId(),
            AuthMiddleware::SHOPWARE_USER_LANGUAGE => $this->localeProvider->getLocaleFromContext($context),
            'sw-user-id' => $context->getSource() instanceof AdminApiSource ? ($context->getSource()->getUserId() ?? '') : '',
        ]);

        return Uri::withQueryValue(
            $unsignedUri,
            'shopware-shop-signature',
            (new RequestSigner())->signPayload($unsignedUri->getQuery(), $secret)
        );
    }
}
