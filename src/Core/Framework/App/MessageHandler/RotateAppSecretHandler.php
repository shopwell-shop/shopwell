<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\MessageHandler;

use Shopwell\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopwell\Core\Framework\App\Message\RotateAppSecretMessage;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
#[AsMessageHandler]
final class RotateAppSecretHandler
{
    public function __construct(
        private readonly AppSecretRotationService $rotationService
    ) {
    }

    public function __invoke(RotateAppSecretMessage $message): void
    {
        $context = Context::createDefaultContext();

        $this->rotationService->rotateNow($message->getAppId(), $context, $message->getTrigger());
    }
}
