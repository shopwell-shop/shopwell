<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Maintenance\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Update\Event\UpdatePostPrepareEvent;
use Shopwell\Core\Framework\Update\Event\UpdatePrePrepareEvent;
use Shopwell\Core\Maintenance\System\Command\SystemUpdatePrepareCommand;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemUpdatePrepareCommand::class)]
class SystemUpdatePrepareCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $container = new ContainerBuilder();

        $eventDispatcher = new CollectingEventDispatcher();
        $container->set('event_dispatcher', $eventDispatcher);

        $command = new SystemUpdatePrepareCommand($container, '6.5.0.0');

        $tester = new CommandTester($command);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $events = $eventDispatcher->getEvents();

        static::assertCount(2, $events);

        $preEvent = $events[0];

        static::assertInstanceOf(UpdatePrePrepareEvent::class, $preEvent);

        $postEvent = $events[1];

        static::assertInstanceOf(UpdatePostPrepareEvent::class, $postEvent);
    }
}
