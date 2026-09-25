<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\Store;

use GuzzleHttp\Client;
use Shopwell\Core\Framework\JWT\JWTDecoder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopwell\Core\Framework\Store\InAppPurchase;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpKernel\Log\Logger;

/**
 * @internal
 */
#[Package('checkout')]
class StaticInAppPurchaseFactory
{
    /**
     * @param array<string, array<int, string>> $activePurchases ['extensionName' => ...purchases]
     */
    public static function createWithFeatures(array $activePurchases = []): InAppPurchase
    {
        $inAppPurchase = new InAppPurchase(
            new InAppPurchaseProvider(
                new StaticSystemConfigService([
                    InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => \json_encode(self::purchasesToJWTS($activePurchases), \JSON_THROW_ON_ERROR),
                ]),
                new JWTDecoder(),
                new KeyFetcher(
                    new Client(),
                    new class extends StoreRequestOptionsProvider {
                        public function __construct()
                        {
                        }
                    },
                    new StaticSystemConfigService(),
                    new class extends Logger {
                        public function __construct()
                        {
                        }
                    }
                ),
                new class extends Logger {
                    public function __construct()
                    {
                    }
                },
                new NativeClock(),
            )
        );

        $reflection = new \ReflectionProperty(InAppPurchase::class, 'activePurchases');
        $reflection->setValue($inAppPurchase, $activePurchases);

        return $inAppPurchase;
    }

    /**
     * @param array<string, array<int, string>> $activePurchases
     *
     * @return array<string, string>
     */
    private static function purchasesToJWTS(array $activePurchases): array
    {
        return \array_map(
            /** @var array<int, string> $purchases */
            static fn (array $purchases) => \md5(\json_encode($purchases, \JSON_THROW_ON_ERROR)),
            $activePurchases
        );
    }
}
