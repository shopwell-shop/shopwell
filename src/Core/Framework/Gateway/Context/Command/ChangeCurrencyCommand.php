<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Command;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class ChangeCurrencyCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_change-currency';

    public function __construct(
        public readonly string $iso,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
