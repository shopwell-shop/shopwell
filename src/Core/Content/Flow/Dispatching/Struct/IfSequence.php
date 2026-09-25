<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Struct;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('after-sales')]
class IfSequence extends Sequence
{
    public string $ruleId;

    public ?Sequence $falseCase = null;

    public ?Sequence $trueCase = null;
}
