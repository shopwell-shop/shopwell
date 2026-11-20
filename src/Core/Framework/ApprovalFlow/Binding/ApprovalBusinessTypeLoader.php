<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface ApprovalBusinessTypeLoader
{
    /**
     * @return list<ApprovalBusinessTypeSpecification>
     */
    public function load(): array;
}
