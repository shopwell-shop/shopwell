<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Service;

use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
interface PromotionDateTimeServiceInterface
{
    public function getNow(): string;
}
