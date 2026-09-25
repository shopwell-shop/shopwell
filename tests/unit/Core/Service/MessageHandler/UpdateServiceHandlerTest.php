<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\Message\UpdateServiceMessage;
use Shopwell\Core\Service\MessageHandler\UpdateServiceHandler;
use Shopwell\Core\Service\ServiceLifecycle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdateServiceHandler::class)]
class UpdateServiceHandlerTest extends TestCase
{
    public function testHandlerUpdatesThenReevaluatesInstalledServices(): void
    {
        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle->expects($this->once())->method('update')->with('MyCoolService');
        $serviceLifecycle->expects($this->once())->method('reevaluateInstalled');

        $handler = new UpdateServiceHandler($serviceLifecycle);
        $handler->__invoke(new UpdateServiceMessage('MyCoolService'));
    }
}
