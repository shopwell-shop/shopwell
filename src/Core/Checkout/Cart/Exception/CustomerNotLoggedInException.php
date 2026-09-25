<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Exception;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerNotLoggedInException extends CartException
{
}
