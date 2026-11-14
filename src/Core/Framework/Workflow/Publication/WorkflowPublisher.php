<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;

/**
 * Business extensions register publication targets with the shopwell.workflow.publisher tag.
 */
#[Package('framework')]
interface WorkflowPublisher
{
    public function name(): string;

    public function labelKey(): string;

    /**
     * @return list<string> Additional privileges required by this publication target.
     */
    public function privileges(): array;

    /**
     * Validate executable capability and persist the target's immutable definition.
     * Runs in the publication transaction; do not start instances or perform external side effects here.
     */
    public function publish(WorkflowReleaseEntity $release, Context $context): void;
}
