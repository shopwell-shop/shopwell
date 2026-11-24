<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Content\Media\UnusedMediaPurger;
use Shopwell\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopwell\Core\Content\Product\Cleanup\CleanupUnusedProductDocumentMediaTaskHandler;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CleanupUnusedProductDocumentMediaTaskHandler::class)]
class CleanupUnusedProductDocumentMediaTaskHandlerTest extends TestCase
{
    public function testRunPurgesTheDocumentsFolderWithAOneDayGracePeriod(): void
    {
        $purger = $this->createMock(UnusedMediaPurger::class);
        $purger
            ->expects($this->once())
            ->method('deleteNotUsedMedia')
            ->with(50, null, 1, ProductDocumentDefinition::ENTITY_NAME);

        $handler = new CleanupUnusedProductDocumentMediaTaskHandler(
            StaticEntityRepository::of(ScheduledTaskCollection::class),
            static::createStub(LoggerInterface::class),
            $purger
        );

        $handler->run();
    }
}
