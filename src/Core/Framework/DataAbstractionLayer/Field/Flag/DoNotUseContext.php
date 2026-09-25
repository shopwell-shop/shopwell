<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopwell\Core\Framework\Log\Package;

/**
 * Prevents FkFieldSerializer from auto-filling required FK values from the WriteContext.
 */
#[Package('framework')]
class DoNotUseContext extends Flag
{
    public function parse(): \Generator
    {
        yield 'do_not_use_context' => true;
    }
}
