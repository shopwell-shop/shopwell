<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Address\Error;

use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
interface AddressErrorInterface
{
    public function getAddressId(): ?string;
}
