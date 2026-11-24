<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Cleanup\CleanupUnusedProductDocumentMediaTask;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CleanupUnusedProductDocumentMediaTask::class)]
class CleanupUnusedProductDocumentMediaTaskTest extends TestCase
{
    public function testTaskMetadata(): void
    {
        static::assertSame('product_document.media.cleanup', CleanupUnusedProductDocumentMediaTask::getTaskName());
        static::assertSame(2628000, CleanupUnusedProductDocumentMediaTask::getDefaultInterval());
        static::assertTrue(CleanupUnusedProductDocumentMediaTask::shouldRescheduleOnFailure());
    }
}
