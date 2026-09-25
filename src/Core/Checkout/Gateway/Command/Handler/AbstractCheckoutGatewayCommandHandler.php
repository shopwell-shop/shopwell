<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\Command\Handler;

use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractCheckoutGatewayCommandHandler
{
    abstract public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void;

    /**
     * @return array<class-string<AbstractCheckoutGatewayCommand>>
     */
    abstract public static function supportedCommands(): array;
}
