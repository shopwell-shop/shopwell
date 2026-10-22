<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

/**
 * The guard is the only place that decides whether an entry may be read, and it has exactly one
 * dimension: the date. Every case of that dimension is pinned here, including the boundary - a
 * scheduled entry that leaks one second early is the bug this class exists to prevent.
 *
 * There is no "switched off" case because there is no switch. An article that must not be read yet
 * carries a date in the future, and an article that must disappear is deleted.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogVisibilityGuard::class)]
class BlogVisibilityGuardTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testAnUndatedEntryIsVisibleImmediately(): void
    {
        $blog = $this->blog(publishedAt: null);

        static::assertTrue($this->guard('2026-01-01 10:00:00')->isVisible($blog, $this->context));
    }

    public function testAnEntryDatedInThePastIsVisible(): void
    {
        $blog = $this->blog(publishedAt: $this->moment('2025-12-24 08:00:00'));

        static::assertTrue($this->guard('2026-01-01 10:00:00')->isVisible($blog, $this->context));
    }

    public function testAnEntryDatedInTheFutureIsInvisible(): void
    {
        $blog = $this->blog(publishedAt: $this->moment('2026-01-01 10:00:01'));

        static::assertFalse($this->guard('2026-01-01 10:00:00')->isVisible($blog, $this->context));
    }

    public function testAnEntryDatedExactlyNowIsVisible(): void
    {
        $blog = $this->blog(publishedAt: $this->moment('2026-01-01 10:00:00'));

        static::assertTrue($this->guard('2026-01-01 10:00:00')->isVisible($blog, $this->context));
    }

    public function testTheFilterKeepsUndatedAndDatedEntriesInTheirOrder(): void
    {
        $undated = $this->blog(publishedAt: null);
        $future = $this->blog(publishedAt: $this->moment('2027-06-01 00:00:00'));
        $past = $this->blog(publishedAt: $this->moment('2025-06-01 00:00:00'));

        $visible = $this->guard('2026-01-01 10:00:00')
            ->filterVisible(new BlogCollection([$undated, $future, $past]), $this->context);

        static::assertSame(
            [$undated->id, $past->id],
            array_values($visible->getIds())
        );
    }

    private function guard(string $now): BlogVisibilityGuard
    {
        return new BlogVisibilityGuard(new MockClock($now, 'UTC'));
    }

    private function moment(string $moment): \DateTimeImmutable
    {
        return new \DateTimeImmutable($moment, new \DateTimeZone('UTC'));
    }

    private function blog(?\DateTimeImmutable $publishedAt): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->id = Uuid::randomHex();
        // the collection indexes by unique identifier, and the dal sets it on hydrate normally
        $blog->setUniqueIdentifier($blog->id);
        $blog->title = 'An entry';
        $blog->slug = 'an-entry';
        $blog->publishedAt = $publishedAt;

        return $blog;
    }
}
