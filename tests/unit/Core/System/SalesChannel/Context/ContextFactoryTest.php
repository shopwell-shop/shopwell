<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\ContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\Event\ContextCreatedEvent;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextFactory::class)]
class ContextFactoryTest extends TestCase
{
    public function testGetContext(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn([
            'sales_channel_default_language_id' => Uuid::randomBytes(),
            'sales_channel_currency_factor' => 1.0,
            'sales_channel_currency_id' => Uuid::randomBytes(),
            'sales_channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
        ]);

        $eventDispatcher = new CollectingEventDispatcher();
        $context = (new ContextFactory($connection, $eventDispatcher))->getContext(Uuid::randomHex(), [
            SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
        ]);

        $events = $eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(ContextCreatedEvent::class, $events[0]);

        static::assertSame(Defaults::LANGUAGE_SYSTEM, $context->getLanguageId());
    }
}
