<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalBindingHeadEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalBindingHeadCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_binding_head_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalBindingHeadEntity::class;
    }
}
