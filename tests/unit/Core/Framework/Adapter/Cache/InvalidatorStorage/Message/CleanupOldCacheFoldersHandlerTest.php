<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Cache\InvalidatorStorage\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\CacheClearer;
use Shopwell\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders;
use Shopwell\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFoldersHandler;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupOldCacheFoldersHandler::class)]
class CleanupOldCacheFoldersHandlerTest extends TestCase
{
    public function testInvoke(): void
    {
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())->method('cleanupOldContainerCacheDirectories');

        $handler = new CleanupOldCacheFoldersHandler($cacheClearer);
        $handler(new CleanupOldCacheFolders());
    }
}
