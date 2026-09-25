<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Exception;

use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class CustomerDeletedException extends \Exception
{
    public function __construct(string $orderId)
    {
        $message = \sprintf('The customer of the order with id %s has been deleted.', $orderId);

        parent::__construct($message);
    }
}
