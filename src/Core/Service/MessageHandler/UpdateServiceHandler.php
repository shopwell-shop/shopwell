<?php declare(strict_types=1);

namespace Shopwell\Core\Service\MessageHandler;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\Message\UpdateServiceMessage;
use Shopwell\Core\Service\ServiceLifecycle;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final readonly class UpdateServiceHandler
{
    public function __construct(private ServiceLifecycle $serviceLifecycle)
    {
    }

    public function __invoke(UpdateServiceMessage $updateServiceMessage): void
    {
        $context = Context::createDefaultContext();

        $this->serviceLifecycle->update($updateServiceMessage->name, $context);
        $this->serviceLifecycle->reevaluateInstalled($context);
    }
}
