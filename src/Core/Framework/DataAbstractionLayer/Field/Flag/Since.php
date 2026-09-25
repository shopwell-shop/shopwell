<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class Since extends Flag
{
    public function __construct(private readonly string $since)
    {
    }

    public function parse(): \Generator
    {
        yield 'since' => $this->since;
    }

    public function getSince(): string
    {
        return $this->since;
    }
}
