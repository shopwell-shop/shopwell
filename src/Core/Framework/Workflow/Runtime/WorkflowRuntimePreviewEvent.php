<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Runtime;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Runtimes may contribute an advisory path for a business-authorized release and input.
 * Subscribers must remain read-only; a preview neither starts an instance nor authorizes execution.
 * A null result means the runtime does not provide previews.
 */
#[Package('framework')]
final class WorkflowRuntimePreviewEvent extends Event
{
    /**
     * @var list<array{nodeId: string, type: string, name: string, rule: string|null, assignees: list<array{id: string, name: string}>, branch: string|null}>|null
     */
    private ?array $steps = null;

    /**
     * @param array<string, mixed> $data Business-owned input, never an arbitrary client graph.
     */
    public function __construct(public readonly WorkflowReleaseEntity $release, public readonly array $data, public readonly Context $context)
    {
    }

    /**
     * @param list<array{nodeId: string, type: string, name: string, rule: string|null, assignees: list<array{id: string, name: string}>, branch: string|null}> $steps
     */
    public function setSteps(array $steps): void
    {
        $this->steps = $steps;
    }

    /**
     * @return list<array{nodeId: string, type: string, name: string, rule: string|null, assignees: list<array{id: string, name: string}>, branch: string|null}>|null
     */
    public function getSteps(): ?array
    {
        return $this->steps;
    }
}
