<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching;

use Shopwell\Core\Content\Flow\Dispatching\Struct\Sequence;
use Shopwell\Core\Framework\Log\Package;

#[Package('after-sales')]
class FlowState
{
    public string $flowId;

    public bool $stop = false;

    public Sequence $currentSequence;

    public bool $delayed = false;

    public function getSequenceId(): string
    {
        return $this->currentSequence->sequenceId;
    }
}
