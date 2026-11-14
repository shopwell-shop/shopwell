<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final class WorkflowPublisherRegistry
{
    /**
     * @var array<string, WorkflowPublisher>
     */
    private array $publishers = [];

    /**
     * @param iterable<WorkflowPublisher> $publishers
     */
    public function __construct(iterable $publishers)
    {
        foreach ($publishers as $publisher) {
            if (isset($this->publishers[$publisher->name()])) {
                throw WorkflowException::duplicatePublisher($publisher->name());
            }
            $this->publishers[$publisher->name()] = $publisher;
        }
    }

    /**
     * @return list<WorkflowPublisher>
     */
    public function all(): array
    {
        return array_values($this->publishers);
    }

    public function get(string $name): WorkflowPublisher
    {
        return $this->publishers[$name] ?? throw WorkflowException::publisherUnavailable($name);
    }
}
