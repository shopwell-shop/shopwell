<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Content\Blog\Service\BlogCommentNode;
use Shopwell\Core\Content\Blog\Service\BlogCommentTreeResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * The result is what the pagination renders, and the page count is the number a visitor acts on.
 * Counting replies as well as roots would offer a page 4 that holds nothing, so the cases below pin
 * down that only top level comments are counted and that a limit of zero can not divide by zero.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogCommentTreeResult::class)]
class BlogCommentTreeResultTest extends TestCase
{
    public function testAnEmptyThreadReportsNoPages(): void
    {
        $result = new BlogCommentTreeResult();

        static::assertTrue($result->isEmpty());
        static::assertSame(0, $result->getTotal());
        static::assertSame(0, $result->getTotalPages());
        static::assertSame(1, $result->getPage());
        static::assertSame(10, $result->getLimit());
        static::assertFalse($result->isTruncated());
    }

    public function testAnEmptyThreadHasNeitherAPreviousNorANextPage(): void
    {
        $result = new BlogCommentTreeResult();

        static::assertFalse($result->hasPreviousPage());
        static::assertFalse($result->hasNextPage());
    }

    public function testTheTotalIsRoundedUpToWholePages(): void
    {
        $result = new BlogCommentTreeResult(total: 25, limit: 10);

        static::assertSame(3, $result->getTotalPages());
    }

    public function testTheNextPageExistsUntilTheLastPageIsReached(): void
    {
        $first = new BlogCommentTreeResult(total: 25, page: 1, limit: 10);
        $last = new BlogCommentTreeResult(total: 25, page: 3, limit: 10);

        static::assertTrue($first->hasNextPage());
        static::assertFalse($first->hasPreviousPage());

        static::assertFalse($last->hasNextPage());
        static::assertTrue($last->hasPreviousPage());
    }

    public function testALimitOfZeroDoesNotDivideByZero(): void
    {
        $result = new BlogCommentTreeResult(total: 25, limit: 0);

        static::assertSame(0, $result->getTotalPages());
        static::assertFalse($result->hasNextPage());
    }

    public function testATruncatedThreadSaysSo(): void
    {
        $result = new BlogCommentTreeResult(total: 2000, truncated: true);

        static::assertTrue($result->isTruncated());
    }

    public function testTheRootsAreHandedOutAsTheyWereBuilt(): void
    {
        $root = new BlogCommentNode((new BlogCommentEntity())->assign(['id' => 'a1']));

        $result = new BlogCommentTreeResult([$root], 1);

        static::assertSame([$root], $result->getRoots());
        static::assertFalse($result->isEmpty());
    }
}
