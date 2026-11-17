<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Collects extension-owned snapshots before the first node is entered.
 * Also dispatched for previews: subscribers must only read, without external side effects.
 * Previews without an authenticated initiator carry a null initiator ID.
 * Each owner contributes JSON-serializable data under its own namespaced key.
 */
#[Package('framework')]
final class WorkflowInstanceMetadataEvent extends Event
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $metadata = [];

    public function __construct(
        public readonly ?string $initiatorId,
        public readonly Context $context,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function addMetadata(string $owner, array $metadata): void
    {
        if (\array_key_exists($owner, $this->metadata)) {
            throw WorkflowExecutionException::duplicateRegistration('instance metadata', $owner);
        }

        $this->metadata[$owner] = $metadata;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
