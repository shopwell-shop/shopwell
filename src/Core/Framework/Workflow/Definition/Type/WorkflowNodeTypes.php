<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Type;

use Shopwell\Core\Framework\Log\Package;

/**
 * Built-in designer document identities; extensions may register additional types.
 * Publishers translate these types into the distinct execution graph vocabulary.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class WorkflowNodeTypes
{
    public const START = 'core:start';
    public const END = 'core:end';
    public const COPY = 'core:copy';
    public const APPROVAL = 'core:approval';
    public const CONDITION = 'core:condition';
}
