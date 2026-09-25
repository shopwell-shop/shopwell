<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Order;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class IdStruct extends Struct
{
    public function __construct(
        protected string $id
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getApiAlias(): string
    {
        return 'cart_order_id';
    }
}
