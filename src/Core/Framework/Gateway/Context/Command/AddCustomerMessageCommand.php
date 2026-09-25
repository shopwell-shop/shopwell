<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Command;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class AddCustomerMessageCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_add-customer-message';

    /**
     * @internal
     */
    public function __construct(
        public readonly string $message,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
