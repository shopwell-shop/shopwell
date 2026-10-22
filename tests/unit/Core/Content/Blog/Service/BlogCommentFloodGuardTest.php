<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\Service\BlogCommentFloodGuard;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * The guard is the only thing between an open comment form and a script that fills a thread. Two
 * details decide whether it works in practice: the window is counted downwards from the injected
 * clock (so the boundary is testable) and an empty address is not rate limited, because a guest that
 * never typed one would otherwise be locked out globally.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogCommentFloodGuard::class)]
class BlogCommentFloodGuardTest extends TestCase
{
    public function testAnEmptyAddressIsNotRateLimited(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('searchIds');

        $guard = new BlogCommentFloodGuard($repository, $this->clock('2026-01-01 10:00:00'));

        $guard->assertMayPost('blog-id', '   ', Context::createDefaultContext());
    }

    public function testAnAddressThatHasNotCommentedYetMayPost(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), Context::createDefaultContext()));

        $guard = new BlogCommentFloodGuard($repository, $this->clock('2026-01-01 10:00:00'));

        $guard->assertMayPost('blog-id', 'reader@example.com', Context::createDefaultContext());
    }

    public function testASecondCommentWithinTheWindowIsRefused(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds(['comment-id'], new Criteria(), Context::createDefaultContext()));

        $guard = new BlogCommentFloodGuard($repository, $this->clock('2026-01-01 10:00:00'));

        try {
            $guard->assertMayPost('blog-id', 'reader@example.com', Context::createDefaultContext());
            static::fail('The guard did not refuse a second comment inside the window.');
        } catch (BlogException $exception) {
            static::assertSame(BlogException::COMMENT_FLOOD, $exception->getErrorCode());
            static::assertSame(429, $exception->getStatusCode());
        }
    }

    public function testTheLookupIsScopedToTheEntryTheAddressAndTheLastSixtySeconds(): void
    {
        $captured = null;

        /** @var EntityRepository<BlogCommentCollection>&Stub $repository */
        $repository = static::createStub(EntityRepository::class);
        $repository->method('searchIds')->willReturnCallback(
            static function (Criteria $criteria) use (&$captured): IdSearchResult {
                $captured = $criteria;

                return IdSearchResult::fromIds([], $criteria, Context::createDefaultContext());
            }
        );

        $guard = new BlogCommentFloodGuard($repository, $this->clock('2026-01-01 10:00:00'));
        $guard->assertMayPost('blog-id', 'reader@example.com', Context::createDefaultContext());

        static::assertInstanceOf(Criteria::class, $captured);
        static::assertSame(1, $captured->getLimit());

        $filters = $captured->getFilters();
        static::assertCount(3, $filters);

        static::assertInstanceOf(EqualsFilter::class, $filters[0]);
        static::assertSame('blogId', $filters[0]->getField());
        static::assertSame('blog-id', $filters[0]->getValue());

        static::assertInstanceOf(EqualsFilter::class, $filters[1]);
        static::assertSame('externalEmail', $filters[1]->getField());
        static::assertSame('reader@example.com', $filters[1]->getValue());

        static::assertInstanceOf(RangeFilter::class, $filters[2]);
        static::assertSame('createdAt', $filters[2]->getField());
        static::assertSame('2026-01-01 09:59:00.000', $filters[2]->getParameter(RangeFilter::GTE));
    }

    private function clock(string $now): ClockInterface
    {
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable($now, new \DateTimeZone('UTC')));

        return $clock;
    }
}
