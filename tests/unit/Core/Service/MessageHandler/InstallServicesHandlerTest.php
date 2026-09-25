<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\LifecycleManager;
use Shopwell\Core\Service\Message\InstallServicesMessage;
use Shopwell\Core\Service\MessageHandler\InstallServicesHandler;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InstallServicesHandler::class)]
class InstallServicesHandlerTest extends TestCase
{
    public function testHandlerDelegatesToServiceLifecycle(): void
    {
        $lifecycleManager = $this->createMock(LifecycleManager::class);
        $lifecycleManager->expects($this->once())
            ->method('reconcile')
            ->with(static::isInstanceOf(Context::class));

        $handler = new InstallServicesHandler($lifecycleManager);
        $handler->__invoke(new InstallServicesMessage());
    }
}
