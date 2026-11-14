<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Type\Loader;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;

/**
 * @internal Experimental until the execution and editor contracts have been verified.
 */
#[Package('framework')]
interface WorkflowNodeTypeLoader
{
    /**
     * @return iterable<WorkflowNodeTypeSpecification>
     */
    public function load(): iterable;
}
