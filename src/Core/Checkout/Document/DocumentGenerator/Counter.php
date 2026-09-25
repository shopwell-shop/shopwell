<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\DocumentGenerator;

use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Log\Package;

#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    description: 'Part of the legacy document generation pipeline. DocumentV2 handles this concern internally and exposes no counterpart.',
)]
class Counter
{
    private int $counter = 0;

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function increment(): void
    {
        ++$this->counter;
    }
}
