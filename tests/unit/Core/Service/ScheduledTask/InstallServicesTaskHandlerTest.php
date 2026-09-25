<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\LifecycleManager;
use Shopwell\Core\Service\ScheduledTask\InstallServicesTaskHandler;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InstallServicesTaskHandler::class)]
class InstallServicesTaskHandlerTest extends TestCase
{
    public function testAlwaysDelegatesToManager(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->expects($this->once())
            ->method('reconcile');

        $handler = new InstallServicesTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $manager,
        );

        $handler->run();
    }
}
