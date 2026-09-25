<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\MessageQueue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopwell\Core\Framework\App\Message\RotateAppSecretMessage;
use Shopwell\Core\Framework\App\MessageHandler\RotateAppSecretHandler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RotateAppSecretHandler::class)]
class RotateAppSecretHandlerTest extends TestCase
{
    private RotateAppSecretHandler $handler;

    private AppSecretRotationService&MockObject $rotationService;

    protected function setUp(): void
    {
        $this->rotationService = $this->createMock(AppSecretRotationService::class);
        $this->handler = new RotateAppSecretHandler($this->rotationService);
    }

    public function testHandlerInvokesRotationService(): void
    {
        $appId = Uuid::randomHex();
        $trigger = AppSecretRotationService::TRIGGER_API;
        $message = new RotateAppSecretMessage($appId, $trigger);

        $this->rotationService->expects($this->once())
            ->method('rotateNow')
            ->with(
                $appId,
                static::isInstanceOf(Context::class),
                $trigger
            );

        ($this->handler)($message);
    }
}
