<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Struct;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('after-sales')]
class ActionSequence extends Sequence
{
    public string $action;

    /**
     * @var array<string, mixed>
     */
    public array $config = [];

    public ?Sequence $nextAction = null;

    public ?string $appFlowActionId = null;
}
