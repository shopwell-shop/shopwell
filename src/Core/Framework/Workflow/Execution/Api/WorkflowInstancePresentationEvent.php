<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Adds extension-owned details to an already authorized instance response.
 * Subscribers retain responsibility for authorizing any additional business data.
 */
#[Package('framework')]
final class WorkflowInstancePresentationEvent extends Event
{
    /**
     * @var list<\Closure(array<string, mixed>): array<string, mixed>>
     */
    private array $filters = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly WorkflowInstanceEntity $instance,
        public readonly Context $context,
        private array $data,
        public readonly bool $withDetail,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addData(array $data): void
    {
        foreach (array_keys($data) as $key) {
            if (\array_key_exists($key, $this->data)) {
                throw WorkflowExecutionException::duplicateRegistration('presentation field', $key);
            }
        }

        $this->data = [...$this->data, ...$data];
    }

    /**
     * Filters run after subscribers have assembled the response, including extension fields.
     *
     * @param \Closure(array<string, mixed>): array<string, mixed> $filter
     */
    public function filterData(\Closure $filter): void
    {
        $this->filters[] = $filter;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $data = $this->data;
        foreach ($this->filters as $filter) {
            $data = $filter($data);
        }

        return $data;
    }
}
