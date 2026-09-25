<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Command;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class ChangeShippingLocationCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_change-shipping-location';

    public function __construct(
        public readonly ?string $countryIso = null,
        public readonly ?string $countryStateIso = null,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
