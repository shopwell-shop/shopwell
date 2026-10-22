<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\SalesChannel\BlogListingStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * The layout is the one setting a merchant can get wrong without noticing: a theme that dropped a
 * layout, a config value carried over from an older version, a typo in a cms element. The struct is
 * the last place that can turn it into something valid, because the twig template would happily render
 * `blog-listing--mosaic` and the page would look unstyled on exactly one page.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogListingStruct::class)]
class BlogListingStructTest extends TestCase
{
    public function testAnElementWithoutASettingRendersTheClassicLayout(): void
    {
        static::assertSame(BlogListingStruct::LAYOUT_CLASSIC, (new BlogListingStruct())->getLayout());
    }

    public function testEveryLayoutTheCoreRendersIsKeptAsItIs(): void
    {
        foreach (BlogListingStruct::LAYOUTS as $layout) {
            $struct = new BlogListingStruct();
            $struct->setLayout($layout);

            static::assertSame($layout, $struct->getLayout());
        }
    }

    public function testAnUnknownLayoutFallsBackToClassicInsteadOfLeakingIntoTheMarkup(): void
    {
        $struct = new BlogListingStruct();
        $struct->setLayout('mosaic');

        static::assertSame(BlogListingStruct::LAYOUT_CLASSIC, $struct->getLayout());
    }

    public function testTheApiAliasIdentifiesTheElementInTheStoreApiPayload(): void
    {
        static::assertSame('cms_blog_listing', (new BlogListingStruct())->getApiAlias());
    }
}
