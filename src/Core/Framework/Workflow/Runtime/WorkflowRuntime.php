<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Runtime;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;

/**
 * Business extensions register execution adapters with the shopwell.workflow.runtime tag.
 * Callers authorize their business resource and keep the release/instance binding server-side.
 */
#[Package('framework')]
interface WorkflowRuntime
{
    /**
     * The publication target whose immutable releases this runtime executes.
     */
    public function name(): string;

    /**
     * Commit the business binding and runtime writes atomically, then dispatch runtime events.
     * Must own the outer transaction; the callback can be retried and must not perform external I/O.
     *
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    public function transactional(\Closure $operation): mixed;

    /**
     * @param array<string, mixed> $data Business-owned input, never arbitrary request form data.
     */
    public function start(WorkflowReleaseEntity $release, array $data, string $title, Context $context): string;

    /**
     * Read only the instance already bound to the caller's authorized business resource.
     */
    public function status(WorkflowReleaseEntity $release, string $instanceId, Context $context): WorkflowRuntimeStatus;

    /**
     * Preserve the authenticated actor and runtime ownership checks.
     */
    public function cancel(WorkflowReleaseEntity $release, string $instanceId, string $reason, Context $context): void;
}
