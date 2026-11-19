<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Release;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalReleaseEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalReleaseCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_release_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalReleaseEntity::class;
    }
}
