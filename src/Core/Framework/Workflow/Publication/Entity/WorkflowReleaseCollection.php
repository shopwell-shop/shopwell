<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Entity;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<WorkflowReleaseEntity>
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\WorkflowPublicationTest
 */
#[Package('framework')]
class WorkflowReleaseCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WorkflowReleaseEntity::class;
    }
}
