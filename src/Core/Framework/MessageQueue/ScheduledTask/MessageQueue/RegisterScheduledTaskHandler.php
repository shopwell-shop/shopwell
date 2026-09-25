<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\MessageQueue\ScheduledTask\MessageQueue;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @final
 *
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed as the message was not dispatched anymore, call TaskRegistry synchronously
 */
#[Package('framework')]
#[AsMessageHandler]
class RegisterScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(private readonly TaskRegistry $registry)
    {
    }

    public function __invoke(RegisterScheduledTaskMessage $message): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'Dispatching RegisterScheduledTaskMessage is deprecated and will be removed in v6.8.0.0, call TaskRegistry synchronously instead.'
        );

        $this->registry->registerTasks();
    }
}
