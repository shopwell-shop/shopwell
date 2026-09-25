<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Struct;

use Shopwell\Core\Framework\JWT\Struct\JWTStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ImitateCustomerToken extends JWTStruct
{
    public string $salesChannelId;

    public string $customerId;
}
