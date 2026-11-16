<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;

/**
 * One independently registered and testable graph validation rule.
 *
 * @internal Experimental execution extension point; not covered by the backward compatibility promise.
 */
#[Package('framework')]
interface GraphRuleInterface
{
    /**
     * @return list<Violation>
     */
    public function validate(GraphData $graph, ValidationContext $context): array;
}
