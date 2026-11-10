<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Aggregate\BlogContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutDefinition;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogDetailDataLoader;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogContentLayoutDefinition::class)]
class BlogContentLayoutDefinitionTest extends TestCase
{
    public function testBlogRootProvidesBothCuratedEntityAndPreparedDetail(): void
    {
        $definition = new BlogContentLayoutDefinition();
        $requirements = $definition->getPageDataRequirements();

        static::assertSame('blog', $definition->getContentLayoutEntityType());
        static::assertSame(['blog', 'blogDetail'], array_column($requirements, 'key'));
        static::assertSame(BlogDetailDataLoader::SOURCE, $requirements[1]->source);
    }
}
