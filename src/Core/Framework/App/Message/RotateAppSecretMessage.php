<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Message;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopwell\Core\Framework\MessageQueue\DeduplicatableMessageInterface;

/**
 * @codeCoverageIgnore
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
class RotateAppSecretMessage implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    public function __construct(
        private readonly string $appId,
        private readonly string $trigger
    ) {
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getTrigger(): string
    {
        return $this->trigger;
    }

    public function deduplicationId(): ?string
    {
        return $this->appId;
    }
}
