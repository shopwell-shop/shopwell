<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Storefront\Framework\Routing\CachedDomainLoader;
use Shopwell\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopwell\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopwell\Storefront\Theme\ThemeConfigCacheInvalidator;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeConfigCacheInvalidator::class)]
class ThemeConfigCacheInvalidatorTest extends TestCase
{
    private ThemeConfigCacheInvalidator $themeConfigCacheInvalidator;

    private MockedCacheInvalidator $cacheInvalidator;

    protected function setUp(): void
    {
        $this->cacheInvalidator = new MockedCacheInvalidator();
        $this->themeConfigCacheInvalidator = new ThemeConfigCacheInvalidator($this->cacheInvalidator);
    }

    public function testAssigned(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $event = new ThemeAssignedEvent($themeId, $salesChannelId, Context::createDefaultContext());
        $name = 'theme-config-' . $themeId;

        $this->themeConfigCacheInvalidator->assigned($event);

        $expectedInvalidatedTags = [
            $name,
            CachedDomainLoader::CACHE_KEY,
            CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY,
            Translator::tag($salesChannelId),
        ];

        static::assertSame(
            $expectedInvalidatedTags,
            $this->cacheInvalidator->getInvalidatedTags()
        );
    }

    public function testInvalidate(): void
    {
        $themeId = Uuid::randomHex();
        $event = new ThemeConfigChangedEvent($themeId, ['test' => 'test'], Context::createDefaultContext());

        $this->themeConfigCacheInvalidator->invalidate($event);

        $expectedInvalidatedTags = ['theme-config-' . $themeId];

        static::assertSame(
            $expectedInvalidatedTags,
            $this->cacheInvalidator->getInvalidatedTags()
        );
    }
}
