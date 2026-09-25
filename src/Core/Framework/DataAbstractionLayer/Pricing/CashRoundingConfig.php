<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Pricing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('framework')]
class CashRoundingConfig extends Struct
{
    public function __construct(
        protected int $decimals,
        protected float $interval,
        protected bool $roundForNet
    ) {
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }

    public function setDecimals(int $decimals): void
    {
        $this->decimals = $decimals;
    }

    public function getInterval(): float
    {
        return $this->interval;
    }

    public function setInterval(float $interval): void
    {
        $this->interval = $interval;
    }

    public function roundForNet(): bool
    {
        return $this->roundForNet;
    }

    public function setRoundForNet(bool $roundForNet): void
    {
        $this->roundForNet = $roundForNet;
    }
}
