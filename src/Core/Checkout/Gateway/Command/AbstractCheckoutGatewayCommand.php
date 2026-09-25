<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\Command;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('checkout')]
abstract class AbstractCheckoutGatewayCommand extends Struct
{
    abstract public static function getDefaultKeyName(): string;

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function createFromPayload(array $payload): static
    {
        /** @phpstan-ignore-next-line  */
        return new static(...$payload);
    }
}
