<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Request;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalRequestEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalRequestCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_request_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalRequestEntity::class;
    }
}
