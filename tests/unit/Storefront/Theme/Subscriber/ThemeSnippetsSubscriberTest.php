<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Event\SnippetsThemeResolveEvent;
use Shopwell\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Shopwell\Storefront\Theme\Subscriber\ThemeSnippetsSubscriber;
use Shopwell\Storefront\Theme\ThemeRuntimeConfigService;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeSnippetsSubscriber::class)]
class ThemeSnippetsSubscriberTest extends TestCase
{
    private ThemeRuntimeConfigService&Stub $themeRuntimeConfigService;

    private DatabaseSalesChannelThemeLoader&Stub $salesChannelThemeLoader;

    protected function setUp(): void
    {
        $this->themeRuntimeConfigService = static::createStub(ThemeRuntimeConfigService::class);
        $this->salesChannelThemeLoader = static::createStub(DatabaseSalesChannelThemeLoader::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ThemeSnippetsSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(SnippetsThemeResolveEvent::class, $events);
        static::assertSame('onSnippetsThemeResolve', $events[SnippetsThemeResolveEvent::class]);
    }

    public function testOnSnippetsThemeResolveWithSalesChannel(): void
    {
        $salesChannelId = 'test-sales-channel';
        $event = new SnippetsThemeResolveEvent($salesChannelId);

        $usedThemes = ['theme1', 'theme2'];
        $allThemes = ['theme1', 'theme2', 'theme3', 'theme4'];

        $salesChannelThemeLoader = $this->createMock(DatabaseSalesChannelThemeLoader::class);
        $salesChannelThemeLoader->expects($this->once())
            ->method('load')
            ->with($salesChannelId)
            ->willReturn($usedThemes);

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->once())
            ->method('getActiveThemeNames')
            ->willReturn($allThemes);

        $subscriber = $this->createSubscriber($themeRuntimeConfigService, $salesChannelThemeLoader);
        $subscriber->onSnippetsThemeResolve($event);

        static::assertSame(
            ['theme1', 'theme2', StorefrontPluginRegistry::BASE_THEME_NAME],
            $event->getUsedThemes()
        );

        static::assertEquals(
            ['theme3', 'theme4'],
            $event->getUnusedThemes()
        );
    }

    public function testOnSnippetsThemeResolveWithoutSalesChannel(): void
    {
        $event = new SnippetsThemeResolveEvent(null);

        $allThemes = ['theme1', 'theme2', 'theme3', 'theme4'];

        $salesChannelThemeLoader = $this->createMock(DatabaseSalesChannelThemeLoader::class);
        $salesChannelThemeLoader->expects($this->never())
            ->method('load');

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->once())
            ->method('getActiveThemeNames')
            ->willReturn($allThemes);

        $subscriber = $this->createSubscriber($themeRuntimeConfigService, $salesChannelThemeLoader);
        $subscriber->onSnippetsThemeResolve($event);

        static::assertSame(
            [StorefrontPluginRegistry::BASE_THEME_NAME],
            $event->getUsedThemes()
        );

        static::assertSame(
            $allThemes,
            $event->getUnusedThemes()
        );
    }

    private function createSubscriber(
        ?ThemeRuntimeConfigService $themeRuntimeConfigService = null,
        ?DatabaseSalesChannelThemeLoader $salesChannelThemeLoader = null
    ): ThemeSnippetsSubscriber {
        return new ThemeSnippetsSubscriber(
            $themeRuntimeConfigService ?? $this->themeRuntimeConfigService,
            $salesChannelThemeLoader ?? $this->salesChannelThemeLoader
        );
    }
}
