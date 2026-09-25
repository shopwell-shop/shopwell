<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Seo\MainCategory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(MainCategoryEntity::class)]
class MainCategoryEntityTest extends TestCase
{
    public function testGetCategoryFallsBackToAnEmptyCategory(): void
    {
        static::assertEquals(new CategoryEntity(), (new MainCategoryEntity())->getCategory());
    }

    public function testGetCategoryReturnsTheAssignedCategory(): void
    {
        $mainCategory = new MainCategoryEntity();
        $category = new CategoryEntity();
        $category->setId('category-id');
        $mainCategory->setCategory($category);

        static::assertSame($category, $mainCategory->getCategory());
    }
}
