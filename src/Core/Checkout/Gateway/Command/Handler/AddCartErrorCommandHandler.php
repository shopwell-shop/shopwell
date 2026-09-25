<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\Command\Handler;

use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopwell\Core\Checkout\Gateway\Command\AddCartErrorCommand;
use Shopwell\Core\Checkout\Gateway\Error\CheckoutGatewayError;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AddCartErrorCommandHandler extends AbstractCheckoutGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [
            AddCartErrorCommand::class,
        ];
    }

    /**
     * @param AddCartErrorCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        $response->getCartErrors()->add(new CheckoutGatewayError($command->message, $command->level, $command->blocking));
    }
}
