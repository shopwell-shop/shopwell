<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Cache\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupOldCacheFolders::class)]
class CleanupOldCacheFoldersTest extends TestCase
{
    public function testDeduplicationId(): void
    {
        $message = new CleanupOldCacheFolders();
        static::assertSame('cleanup-old-cache-folders', $message->deduplicationId());
    }
}
