<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader;

use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface NodeTypeLoader
{
    /**
     * @return list<NodeTypeSpecification>
     */
    public function load(): array;
}
