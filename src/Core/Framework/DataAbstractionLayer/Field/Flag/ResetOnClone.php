<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopwell\Core\Framework\Log\Package;

/**
 * Omits the field's current value when an entity is cloned, allowing its normal default to apply.
 */
#[Package('framework')]
class ResetOnClone extends Flag
{
    public function parse(): \Generator
    {
        yield 'reset_on_clone' => true;
    }
}
