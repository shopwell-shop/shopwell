<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class Required extends Flag
{
    public function parse(): \Generator
    {
        yield 'required' => true;
    }
}
