<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Exception;

use Shopwell\Core\Checkout\Promotion\PromotionException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PatternNotComplexEnoughException extends PromotionException
{
}
