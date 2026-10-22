<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * The shop configuration ships as an empty array, so every key an installation never saved reads as
 * null. The defaults in this class are therefore the values that actually render a fresh shop: a
 * missing page size must not produce an empty listing, and a missing moderation switch must not turn
 * an unread comment thread into a published one.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogSettings::class)]
class BlogSettingsTest extends TestCase
{
    public function testUnconfiguredShopUsesTheDefaultsThatKeepAPageRenderable(): void
    {
        $settings = new BlogSettings($this->systemConfig([]));

        static::assertSame(12, $settings->getPostsPerPage());
        static::assertTrue($settings->showAuthor());
        static::assertTrue($settings->showReadingTime());
        static::assertSame(3, $settings->getRelatedPostsCount());
        static::assertTrue($settings->isTocEnabled());
        static::assertTrue($settings->areCommentsEnabled());
        static::assertFalse($settings->autoPublishComments());
        static::assertSame(10, $settings->getCommentsPerPage());
    }

    public function testTheConfiguredValuesWinOverTheDefaults(): void
    {
        $settings = new BlogSettings($this->systemConfig([
            BlogSettings::CONFIG_KEY_POSTS_PER_PAGE => 24,
            BlogSettings::CONFIG_KEY_SHOW_AUTHOR => false,
            BlogSettings::CONFIG_KEY_SHOW_READING_TIME => false,
            BlogSettings::CONFIG_KEY_RELATED_POSTS_COUNT => 0,
            BlogSettings::CONFIG_KEY_TOC_ENABLED => false,
            BlogSettings::CONFIG_KEY_COMMENTS_ENABLED => false,
            BlogSettings::CONFIG_KEY_AUTO_PUBLISH_COMMENTS => true,
            BlogSettings::CONFIG_KEY_COMMENTS_PER_PAGE => 25,
        ]));

        static::assertSame(24, $settings->getPostsPerPage());
        static::assertFalse($settings->showAuthor());
        static::assertFalse($settings->showReadingTime());
        static::assertSame(0, $settings->getRelatedPostsCount());
        static::assertFalse($settings->isTocEnabled());
        static::assertFalse($settings->areCommentsEnabled());
        static::assertTrue($settings->autoPublishComments());
        static::assertSame(25, $settings->getCommentsPerPage());
    }

    public function testAPageSizeBelowOneFallsBackToTheDefaultInsteadOfRenderingAnEmptyFirstPage(): void
    {
        $settings = new BlogSettings($this->systemConfig([
            BlogSettings::CONFIG_KEY_POSTS_PER_PAGE => 0,
            BlogSettings::CONFIG_KEY_COMMENTS_PER_PAGE => -5,
        ]));

        static::assertSame(BlogSettings::DEFAULT_POSTS_PER_PAGE, $settings->getPostsPerPage());
        static::assertSame(BlogSettings::DEFAULT_COMMENTS_PER_PAGE, $settings->getCommentsPerPage());
    }

    public function testANegativeRelatedCountIsRejectedWhileZeroDeliberatelySwitchesTheBlockOff(): void
    {
        static::assertSame(
            BlogSettings::DEFAULT_RELATED_POSTS_COUNT,
            (new BlogSettings($this->systemConfig([BlogSettings::CONFIG_KEY_RELATED_POSTS_COUNT => -1])))->getRelatedPostsCount()
        );

        static::assertSame(
            0,
            (new BlogSettings($this->systemConfig([BlogSettings::CONFIG_KEY_RELATED_POSTS_COUNT => 0])))->getRelatedPostsCount()
        );
    }

    public function testANonNumericPageSizeIsTreatedAsUnconfigured(): void
    {
        $settings = new BlogSettings($this->systemConfig([
            BlogSettings::CONFIG_KEY_POSTS_PER_PAGE => 'twelve',
        ]));

        static::assertSame(BlogSettings::DEFAULT_POSTS_PER_PAGE, $settings->getPostsPerPage());
    }

    public function testTheSalesChannelIsPassedToEveryLookup(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->exactly(2))
            ->method('get')
            ->with(static::anything(), 'sales-channel-id')
            ->willReturn(6);

        $settings = new BlogSettings($systemConfig);

        $settings->getPostsPerPage('sales-channel-id');
        $settings->getCommentsPerPage('sales-channel-id');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function systemConfig(array $values): SystemConfigService
    {
        $systemConfig = static::createStub(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(
            static fn (string $key): mixed => $values[$key] ?? null
        );

        return $systemConfig;
    }
}
