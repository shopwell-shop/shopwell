<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Cookie;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
#[Package('discovery')]
class AppCookieProvider implements CookieProviderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CookieProviderInterface $inner,
    ) {
    }

    public function getCookieGroups(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use CookieGroupCollectEvent instead to introduce cookies')
        );

        return $this->inner->getCookieGroups();
    }
}
